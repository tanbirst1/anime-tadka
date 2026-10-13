<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper;

use AnimeTadka\Cache\FileCache;
use AnimeTadka\Config;
use AnimeTadka\Exceptions\HttpException;
use AnimeTadka\Exceptions\RateLimitedException;
use AnimeTadka\Exceptions\ScraperException;
use AnimeTadka\Logger;

/**
 * Reusable HTTP client with redirects, compression, retries, rate limiting,
 * caching, keep-alive and parallel fetching via curl_multi.
 */
class HttpClient
{
    protected Config $config;
    protected ?Logger $logger;
    protected ?FileCache $cache;

    /** @var array<string, float> last request time per host (rate limiting) */
    private array $lastRequestAt = [];

    /** @var array<string, array{expires: int, body: string}> per-request memory cache */
    private array $memoryCache = [];

    public function __construct(Config $config, ?Logger $logger = null, ?FileCache $cache = null)
    {
        $this->config = $config;
        $this->logger = $logger;

        // Lazily build the cache from config when none is injected.
        if ($cache === null && $config->bool('CACHE_ENABLED', false)) {
            $cache = new FileCache($config->string('CACHE_DIR', 'storage/cache'), $config->int('CACHE_TTL', 3600));
        }
        $this->cache = $cache;
    }

    private function memoryGet(string $key): ?string
    {
        $entry = $this->memoryCache[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['expires'] > 0 && $entry['expires'] < time()) {
            unset($this->memoryCache[$key]);
            return null;
        }
        return $entry['body'];
    }

    private function memorySet(string $key, string $body, int $ttl = 3600): void
    {
        $this->memoryCache[$key] = [
            'body' => $body,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
        // cap memory cache to avoid unbounded growth (LRU-ish)
        if (count($this->memoryCache) > 200) {
            array_shift($this->memoryCache);
        }
    }

    /**
     * Perform a GET request and return the response body.
     *
     * @param array<string, mixed> $options  force_refresh, expect_html, save_html
     */
    public function get(string $url, array $options = []): string
    {
        $cacheKey = $this->cacheKey('GET', $url, []);

        if (empty($options['force_refresh'])) {
            $mem = $this->memoryGet($cacheKey);
            if ($mem !== null) {
                return $mem;
            }
            if ($this->cache !== null) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    $this->memorySet($cacheKey, $cached);
                    $this->log('debug', 'Cache hit', ['url' => $url]);
                    return $cached;
                }
            }
        }

        $result = $this->fetch('GET', $url, [], $options);
        $body = $result[2];

        // cache both layers
        $ttl = $this->config->int('CACHE_TTL', 3600);
        $this->memorySet($cacheKey, $body, $ttl);
        if ($this->cache !== null && empty($options['force_refresh'])) {
            $this->cache->set($cacheKey, $body);
        } elseif ($this->cache !== null && !empty($options['force_refresh'])) {
            // force_refresh still updates cache for next request
            $this->cache->set($cacheKey, $body);
        }

        return $body;
    }

    /**
     * Perform a POST request (form-encoded) and return the response body.
     *
     * @param array<string, string> $data
     * @param array<string, mixed>  $options
     */
    public function post(string $url, array $data = [], array $options = []): string
    {
        $result = $this->fetch('POST', $url, $data, $options);
        return $result[2];
    }

    /**
     * Parallel GET for multiple URLs. Returns url => body (or null on failure).
     * Respects file & memory cache; only misses are fetched via curl_multi.
     *
     * @param string[] $urls
     * @param array<string,mixed> $options
     * @return array<string, ?string>
     */
    public function getMulti(array $urls, array $options = []): array
    {
        $results = [];
        $toFetch = [];
        $keyMap = []; // url => cacheKey

        foreach ($urls as $url) {
            $cacheKey = $this->cacheKey('GET', $url, []);
            $keyMap[$url] = $cacheKey;
            if (empty($options['force_refresh'])) {
                $mem = $this->memoryGet($cacheKey);
                if ($mem !== null) {
                    $results[$url] = $mem;
                    continue;
                }
                if ($this->cache !== null) {
                    $cached = $this->cache->get($cacheKey);
                    if ($cached !== null) {
                        $this->memorySet($cacheKey, $cached);
                        $results[$url] = $cached;
                        continue;
                    }
                }
            }
            $toFetch[] = $url;
        }

        if ($toFetch !== []) {
            // one throttle per host for the batch
            $this->throttleMulti($toFetch);
            $fetched = $this->requestMulti(array_map(fn($u) => ['method' => 'GET', 'url' => $u, 'data' => [], 'options' => $options], $toFetch));
            foreach ($toFetch as $idx => $url) {
                $res = $fetched[$idx] ?? null;
                if ($res === null || $res[0] >= 400) {
                    $results[$url] = null;
                    continue;
                }
                $body = $res[2];
                // validation
                if (!empty($options['expect_html']) && !$this->looksLikeHtml($res[1], $body)) {
                    $results[$url] = null;
                    continue;
                }
                $cacheKey = $keyMap[$url];
                $ttl = $this->config->int('CACHE_TTL', 3600);
                $this->memorySet($cacheKey, $body, $ttl);
                if ($this->cache !== null) {
                    $this->cache->set($cacheKey, $body);
                }
                $results[$url] = $body;
            }
        }

        return $results;
    }

    /**
     * Parallel POST to same URL with different payloads.
     * @param array<int, array<string,string>> $payloads  indexed payloads
     * @param array<string,mixed> $options
     * @return array<int, ?string>  indexed bodies (null on failure)
     */
    public function postMulti(string $url, array $payloads, array $options = []): array
    {
        if ($payloads === []) {
            return [];
        }
        $this->throttle($url);
        $jobs = [];
        foreach ($payloads as $data) {
            $jobs[] = ['method' => 'POST', 'url' => $url, 'data' => $data, 'options' => $options];
        }
        $rawResults = $this->requestMulti($jobs);
        $out = [];
        foreach ($rawResults as $i => $res) {
            if ($res === null || $res[0] >= 400) {
                $out[$i] = null;
                continue;
            }
            if (!empty($options['expect_html']) && !$this->looksLikeHtml($res[1], $res[2])) {
                $out[$i] = null;
                continue;
            }
            $out[$i] = $res[2];
        }
        return $out;
    }

    /**
     * Perform a GET request without following redirects and return the raw
     * response metadata: [status, headers, body].
     *
     * @param array<string, mixed> $options
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function getWithMeta(string $url, array $options = []): array
    {
        $options['no_follow'] = true;
        [$status, $headers, $body] = $this->fetch('GET', $url, [], $options);

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * GET a page and validate that it looks like HTML.
     *
     * @param array<string, mixed> $options
     */
    public function getHtml(string $url, array $options = []): string
    {
        $options['expect_html'] = true;
        return $this->get($url, $options);
    }

    /**
     * Core request pipeline: rate limit → retry loop → curl → validation.
     *
     * @param array<string, string> $data
     * @param array<string, mixed>  $options
     * @return array{0: int, 1: array<string, string>, 2: string} [status, headers, body]
     */
    protected function fetch(string $method, string $url, array $data, array $options): array
    {
        $maxRetries = $this->config->int('HTTP_MAX_RETRIES', 2);
        $backoffMs = $this->config->int('HTTP_RETRY_BACKOFF_MS', 1500);

        $attempt = 0;
        while (true) {
            $attempt++;
            $this->throttle($url);

            try {
                [$status, $headers, $body] = $this->request($method, $url, $data, $options);
            } catch (ScraperException $e) {
                if ($attempt <= $maxRetries && $this->isRetryable($e)) {
                    $this->log('warning', 'Retrying after transport failure', [
                        'url' => $url, 'attempt' => $attempt, 'error' => $e->getMessage(),
                    ]);
                    usleep($backoffMs * 1000 * $attempt);
                    continue;
                }
                throw $e;
            }

            if ($status === 429) {
                $this->log('warning', 'Rate limited (429)', ['url' => $url]);
                if ($attempt <= $maxRetries) {
                    usleep($backoffMs * 1000 * $attempt * 2);
                    continue;
                }
                throw new RateLimitedException("Rate limited by source (HTTP 429): {$url}");
            }

            if ($status >= 500) {
                $this->log('warning', "Server error HTTP {$status}", ['url' => $url]);
                if ($attempt <= $maxRetries) {
                    usleep($backoffMs * 1000 * $attempt);
                    continue;
                }
                throw new HttpException($status, "Source server error (HTTP {$status}): {$url}");
            }

            if ($status >= 400) {
                throw new HttpException($status, "Source returned HTTP {$status}: {$url}");
            }

            if (!empty($options['expect_html']) && !$this->looksLikeHtml($headers, $body)) {
                throw new ScraperException("Response is not HTML: {$url}");
            }

            if (!empty($options['save_html'])) {
                $this->saveHtmlSnapshot($url, $body);
            }

            return [$status, $headers, $body];
        }
    }

    /**
     * Whether the curl extension is loaded. Without it the CURLOPT_*
     * constants are undefined and referencing them is a fatal Error.
     */
    private function isCurlAvailable(): bool
    {
        return function_exists('curl_init') && function_exists('curl_setopt_array');
    }

    private function buildCurlOptions(string $method, string $url, array $data, array $options): array
    {
        // Guard: CURLOPT_* constants do not exist without ext-curl.
        if (!$this->isCurlAvailable()) {
            return [];
        }

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => empty($options['no_follow']),
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '', // accept all supported encodings (gzip, deflate, br)
            CURLOPT_USERAGENT => $this->config->string('HTTP_USER_AGENT', 'Mozilla/5.0'),
            CURLOPT_TIMEOUT => $this->config->int('HTTP_TIMEOUT', 20),
            CURLOPT_CONNECTTIMEOUT => $this->config->int('HTTP_CONNECT_TIMEOUT', 10),
            CURLOPT_HEADER => true,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 60,
            CURLOPT_FORBID_REUSE => false,
            CURLOPT_FRESH_CONNECT => false,
            CURLOPT_DNS_CACHE_TIMEOUT => 600,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Cache-Control: no-cache',
            ],
        ];

        if (defined('CURL_HTTP_VERSION_2TLS')) {
            $curlOptions[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        }

        // Prefer IPv4 to avoid dual-stack DNS latency (configurable)
        if ($this->config->bool('HTTP_IPRESOLVE_V4', false)) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        $proxy = $this->config->string('HTTP_PROXY', '');
        if ($proxy !== '') {
            $curlOptions[CURLOPT_PROXY] = $proxy;
        }

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($data);
        }

        return $curlOptions;
    }

    /**
     * @return array{0: int, 1: array<string, string>, 2: string} [status, headers, body]
     */
    private function request(string $method, string $url, array $data, array $options): array
    {
        if (!$this->isCurlAvailable()) {
            return $this->requestViaStream($method, $url, $data, $options);
        }

        $ch = \curl_init();
        \curl_setopt_array($ch, $this->buildCurlOptions($method, $url, $data, $options));

        $raw = \curl_exec($ch);
        $errno = \curl_errno($ch);
        $error = \curl_error($ch);
        $status = (int) \curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) \curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        \curl_close($ch);

        if ($raw === false) {
            throw new ScraperException("cURL error {$errno}: {$error} for {$url}");
        }

        // Correct header/body split using header size (handles redirects correctly)
        $headerBlock = $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
        $body = $headerSize > 0 ? substr($raw, $headerSize) : $raw;

        $headers = $headerBlock !== '' ? $this->parseHeaders($headerBlock) : [];

        $this->log('debug', 'HTTP request', [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'bytes' => strlen($body),
        ]);

        return [$status, $headers, $body];
    }

    /**
     * Fallback transport when ext-curl is missing: uses the plain PHP
     * http/https stream wrapper instead of curl.
     *
     * @param array<string, string> $data
     * @param array<string, mixed>  $options
     * @return array{0: int, 1: array<string, string>, 2: string} [status, headers, body]
     * @throws ScraperException on any transport failure (never a raw Error)
     */
    private function requestViaStream(string $method, string $url, array $data, array $options): array
    {
        $timeout = $this->config->int('HTTP_TIMEOUT', 20);
        $connectTimeout = $this->config->int('HTTP_CONNECT_TIMEOUT', 10);
        $userAgent = $this->config->string('HTTP_USER_AGENT', 'Mozilla/5.0');
        $follow = empty($options['no_follow']);

        $headerLines = [
            'User-Agent: ' . $userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            // No Accept-Encoding: the stream wrapper does not decompress.
            'Connection: close',
            'Cache-Control: no-cache',
        ];

        $httpOptions = [
            'method' => strtoupper($method),
            'timeout' => max($timeout, $connectTimeout),
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'user_agent' => $userAgent,
            'ignore_errors' => true, // return body for 4xx/5xx so status can be handled upstream
            'follow_location' => $follow,
            'max_redirects' => $follow ? 5 : 0,
        ];

        if (strtoupper($method) === 'POST') {
            $httpOptions['content'] = http_build_query($data);
            $httpOptions['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        }

        $proxy = $this->config->string('HTTP_PROXY', '');
        if ($proxy !== '') {
            $this->log('debug', 'HTTP_PROXY ignored by stream fallback', ['proxy' => $proxy, 'url' => $url]);
        }

        $context = stream_context_create(['http' => $httpOptions]);

        $this->log('debug', 'curl unavailable, using stream fallback', [
            'method' => $method,
            'url' => $url,
            'timeout' => $httpOptions['timeout'],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaderLines = $http_response_header ?? [];

        if ($body === false || $responseHeaderLines === []) {
            $error = error_get_last()['message'] ?? 'unknown stream error';
            $this->log('debug', 'Stream request failed', ['url' => $url, 'error' => $error]);
            throw new ScraperException("HTTP stream error ({$error}) for {$url}");
        }

        // $http_response_header may contain the header blocks of every
        // followed redirect; the final status is the last HTTP/ line.
        $status = 0;
        foreach ($responseHeaderLines as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        if ($status === 0) {
            $this->log('debug', 'Stream response had no status line', ['url' => $url]);
            throw new ScraperException("HTTP stream error (no status line) for {$url}");
        }

        $headers = $this->parseHeaders(implode("\n", $responseHeaderLines));

        $this->log('debug', 'HTTP request (stream)', [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'bytes' => strlen($body),
        ]);

        return [$status, $headers, $body];
    }

    /**
     * Parallel curl_multi execution.
     * @param array<int, array{method: string, url: string, data: array<string,string>, options: array<string,mixed>}> $jobs
     * @return array<int, array{0:int,1:array<string,string>,2:string}|null>
     */
    private function requestMulti(array $jobs): array
    {
        if ($jobs === []) {
            return [];
        }

        // No ext-curl: run the jobs sequentially through the stream fallback.
        if (!$this->isCurlAvailable()) {
            $results = [];
            foreach ($jobs as $idx => $job) {
                try {
                    $results[$idx] = $this->request(
                        $job['method'] ?? 'GET',
                        $job['url'],
                        $job['data'] ?? [],
                        $job['options'] ?? []
                    );
                } catch (ScraperException $e) {
                    // Same semantics as the curl_multi error path: null entry.
                    $results[$idx] = null;
                    $this->log('warning', 'stream request error', [
                        'url' => $job['url'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return $results;
        }

        $mh = \curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($jobs as $idx => $job) {
            $ch = \curl_init();
            \curl_setopt_array($ch, $this->buildCurlOptions(
                $job['method'] ?? 'GET',
                $job['url'],
                $job['data'] ?? [],
                $job['options'] ?? []
            ));
            \curl_multi_add_handle($mh, $ch);
            $handles[$idx] = $ch;
        }

        $running = 0;
        do {
            $mrc = \curl_multi_exec($mh, $running);
            if ($mrc !== CURLM_OK) {
                break;
            }
            // wait for activity (timeout 1s)
            if ($running) {
                \curl_multi_select($mh, 1.0);
            }
        } while ($running > 0);

        foreach ($handles as $idx => $ch) {
            $raw = \curl_multi_getcontent($ch);
            $errno = \curl_errno($ch);
            $error = \curl_error($ch);
            if ($raw === false || $errno !== 0) {
                $results[$idx] = null;
                $this->log('warning', 'curl_multi error', ['url' => $jobs[$idx]['url'] ?? '', 'error' => $error]);
            } else {
                $status = (int) \curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $headerSize = (int) \curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $headerBlock = $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
                $body = $headerSize > 0 ? substr($raw, $headerSize) : $raw;
                $headers = $headerBlock !== '' ? $this->parseHeaders($headerBlock) : [];
                $results[$idx] = [$status, $headers, $body];
            }
            \curl_multi_remove_handle($mh, $ch);
            \curl_close($ch);
        }

        \curl_multi_close($mh);
        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $block): array
    {
        $headers = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'HTTP/')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $headers[$name] = trim(substr($line, $pos + 1));
        }
        return $headers;
    }

    private function looksLikeHtml(array $headers, string $body): bool
    {
        $contentType = strtolower($headers['content-type'] ?? '');
        if ($contentType !== '') {
            if (str_contains($contentType, 'html') || str_contains($contentType, 'xml') || str_contains($contentType, 'text/')) {
                return true;
            }
            // Some servers omit content-type or send application/octet-stream for HTML.
            return str_contains($contentType, 'octet-stream');
        }

        // No content-type header: sniff the body.
        $sniff = ltrim(substr($body, 0, 512));
        return str_starts_with($sniff, '<') || str_contains($sniff, '<html') || str_contains($sniff, '<!doctype');
    }

    private function isRetryable(ScraperException $e): bool
    {
        if ($e instanceof HttpException) {
            return $e->getStatus() >= 500;
        }
        // Transport-level failures (timeouts, connection refused, DNS).
        return true;
    }

    /**
     * Enforce a minimum delay between requests to the same host.
     */
    private function throttle(string $url): void
    {
        $delayMs = $this->config->int('REQUEST_DELAY_MS', 0);
        if ($delayMs <= 0) {
            return;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $now = microtime(true);
        $last = $this->lastRequestAt[$host] ?? 0.0;
        $elapsed = ($now - $last) * 1000;

        if ($elapsed < $delayMs) {
            usleep((int) (($delayMs - $elapsed) * 1000));
        }

        $this->lastRequestAt[$host] = microtime(true);
    }

    /**
     * Throttle once per host for a batch of URLs (parallel).
     * @param string[] $urls
     */
    private function throttleMulti(array $urls): void
    {
        $delayMs = $this->config->int('REQUEST_DELAY_MS', 0);
        if ($delayMs <= 0 || $urls === []) {
            return;
        }
        // find earliest needed delay across hosts in batch
        $hosts = [];
        foreach ($urls as $url) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $hosts[$host] = true;
        }
        $now = microtime(true);
        $maxSleep = 0;
        foreach (array_keys($hosts) as $host) {
            $last = $this->lastRequestAt[$host] ?? 0.0;
            $elapsed = ($now - $last) * 1000;
            if ($elapsed < $delayMs) {
                $need = $delayMs - $elapsed;
                if ($need > $maxSleep) {
                    $maxSleep = $need;
                }
            }
        }
        if ($maxSleep > 0) {
            usleep((int) ($maxSleep * 1000));
        }
        $after = microtime(true);
        foreach (array_keys($hosts) as $host) {
            $this->lastRequestAt[$host] = $after;
        }
    }

    private function cacheKey(string $method, string $url, array $data): string
    {
        return $method . '|' . $url . '|' . http_build_query($data);
    }

    private function saveHtmlSnapshot(string $url, string $body): void
    {
        $dir = $this->config->string('HTML_DIR', 'storage/html');
        if ($dir === '') {
            return;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) parse_url($url, PHP_URL_PATH));
        $name = trim($name, '_');
        if ($name === '') {
            $name = 'page';
        }
        $name = substr($name, 0, 80) . '-' . substr(sha1($url), 0, 8) . '.html';

        @file_put_contents($dir . '/' . $name, $body, LOCK_EX);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->{$level}($message, $context);
        }
    }
}