<?php

declare(strict_types=1);

namespace AnimeDekhoApi\Utils;

use AnimeDekhoApi\Core\Config;
use AnimeDekhoApi\Core\ScrapeException;

/**
 * Browser-like HTTP client with retry, gzip, a persistent cookie jar and a
 * parallel mode for fetching many pages at once.
 *
 * The cookie jar is required by animedekho.app: episode pages are gated
 * behind an ad-skip wall whose verify URL sets a `toronites_server` cookie
 * (~24h validity). With the jar enabled the client passes that gate after a
 * single extra round-trip and stays unlocked afterwards.
 */
final class HttpClient
{
    public function get(string $url): string
    {
        $lastError = null;
        for ($attempt = 0; $attempt <= Config::RETRIES; $attempt++) {
            $body = $this->request($url, $status, $error);
            if ($body !== null && $status === 200) {
                return $body;
            }
            $lastError = $error !== '' ? $error : 'HTTP ' . $status;
            if ($status >= 400 && $status < 500) {
                // Never retry 4xx — the URL itself is wrong or blocked.
                break;
            }
            usleep(300_000 * ($attempt + 1));
        }

        throw new ScrapeException(
            'Failed to fetch ' . $url . ': ' . $lastError,
            $this->statusCode($lastError)
        );
    }

    /**
     * Fetch several URLs, batching in parallel. On hosts whose PHP curl
     * build lacks the multi interface (e.g. InfinityFree), falls back to
     * sequential fetches with the same contract. Returns a map of url => html
     * (only successful 200 responses; failures are simply absent).
     *
     * @param string[] $urls
     * @return array<string, string>
     */
    public function getMany(array $urls, int $concurrency = 6): array
    {
        $multiFns = [
            'curl_multi_init',
            'curl_multi_add_handle',
            'curl_multi_exec',
            'curl_multi_select',
            'curl_multi_getcontent',
            'curl_multi_remove_handle',
            'curl_multi_close',
        ];
        $hasMulti = true;
        foreach ($multiFns as $fn) {
            if (!function_exists($fn)) {
                $hasMulti = false;
                break;
            }
        }
        if (!$hasMulti) {
            $result = [];
            foreach ($urls as $url) {
                try {
                    $result[$url] = $this->get($url);
                } catch (ScrapeException $e) {
                    continue; // failures are simply absent
                }
            }
            return $result;
        }
        $result = [];
        foreach (array_chunk($urls, $concurrency, true) as $batch) {
            $multi = curl_multi_init();
            $handles = [];
            foreach ($batch as $url) {
                $ch = $this->handle($url);
                curl_multi_add_handle($multi, $ch);
                $handles[$url] = $ch;
            }
            do {
                curl_multi_exec($multi, $running);
                if ($running > 0) {
                    curl_multi_select($multi, 0.3);
                }
            } while ($running > 0);

            foreach ($handles as $url => $ch) {
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $body = curl_multi_getcontent($ch);
                curl_multi_remove_handle($multi, $ch);
                if ($status === 200 && is_string($body) && $body !== '') {
                    $result[$url] = $body;
                }
            }
            curl_multi_close($multi);
        }

        return $result;
    }

    /**
     * POST a JSON body and return the decoded response. Used for the site's
     * dgt REST API (wp-json/dgt/v1/...). Returns null on any non-2xx reply
     * or when the body is not valid JSON.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers extra request headers
     * @return array<string, mixed>|null
     */
    public function postJson(string $url, array $body, array $headers = []): ?array
    {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return null;
        }

        $response = $this->requestPost($url, $payload, $headers, $status);
        if ($response === null || $status < 200 || $status >= 300) {
            return null;
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /** @param array<string, string> $headers */
    private function requestPost(string $url, string $payload, array $headers, ?int &$status): ?string
    {
        if (!function_exists('curl_init') || !function_exists('curl_setopt_array')) {
            return $this->requestPostViaStream($url, $payload, $headers, $status);
        }

        $ch = $this->handle($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
            ], $headers),
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $body === '') {
            return null;
        }
        return $body;
    }

    /** @param array<string, string> $headers */
    private function requestPostViaStream(string $url, string $payload, array $headers, ?int &$status): ?string
    {
        $headerLines = array_merge([
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'User-Agent: ' . Config::USER_AGENT,
            'Connection: close',
        ], array_map(static fn ($k, $v): string => $k . ': ' . $v, array_keys($headers), array_values($headers)));

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => Config::timeout(),
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $payload,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        if ($body === false || $body === '') {
            return null;
        }
        return $body;
    }

    /**
     * Drop every stored cookie (deletes the jar file). Used by the /token
     * endpoint so it always observes the site's real gated state and hands
     * out fresh verify links; all other endpoints keep the shared jar and
     * its transparent bypass.
     */
    public function clearCookieJar(): void
    {
        $jar = Config::COOKIE_FILE;
        if (is_file($jar)) {
            @unlink($jar);
        }
    }

    /** @return array{0: string|null, 1: int, 2: string} [body, status, error] */
    private function request(string $url, ?int &$status, ?string &$error): ?string
    {
        // Guard: without ext-curl curl_init() and the CURLOPT_* constants
        // are undefined and referencing them is a fatal Error.
        if (!function_exists('curl_init') || !function_exists('curl_setopt_array')) {
            return $this->requestViaStream($url, $status, $error);
        }

        $ch = $this->handle($url);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($body === false || $body === '') {
            return null;
        }
        return $body;
    }

    /**
     * Fallback transport when ext-curl is missing: PHP's http stream wrapper.
     * Same contract as the curl path — sets $status/$error by reference and
     * returns null on failure (never throws an Error). Cookie jar is not
     * available on this path, so gated pages may need curl.
     */
    private function requestViaStream(string $url, ?int &$status, ?string &$error): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => Config::timeout(),
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true, // expose the body; status parsed below
                'header' => implode("\r\n", [
                    'User-Agent: ' . Config::USER_AGENT,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    // No Accept-Encoding: the stream wrapper does not decode it.
                    'Connection: close',
                ]) . "\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        // $http_response_header may contain the blocks of every followed
        // redirect; the final status is the last HTTP/ line.
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        $error = '';
        if ($body === false || $body === '') {
            $error = error_get_last()['message'] ?? 'stream request failed';
            return null;
        }
        return $body;
    }

    /** @return \CurlHandle */
    private function handle(string $url)
    {
        $jar = Config::COOKIE_FILE;
        $dir = dirname($jar);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => Config::timeout(),
            CURLOPT_USERAGENT => Config::USER_AGENT,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            // Prefer IPv4: dual-stack DNS resolution can stall past the
            // connect timeout on some networks (SCRAPE_ERROR timeouts).
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        return $ch;
    }

    private function statusCode(?string $error): int
    {
        if ($error !== null && $error !== '') {
            return 502;
        }
        return 500;
    }
}
