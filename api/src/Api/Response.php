<?php

declare(strict_types=1);

namespace AnimeTadka\Api;

/**
 * JSON API response envelope.
 *
 * Success: { status: "success", data: {...}, meta: {...}, timestamp }
 * Error:   { status: "error", error: { code, message }, timestamp }
 */
final class Response
{
    private int $status;
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(int $status, array $payload)
    {
        $this->status = $status;
        $this->payload = $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(array $data, int $status = 200, array $meta = []): self
    {
        $payload = [
            'status' => 'success',
            'data' => $data,
            'meta' => array_merge(['source' => 'animetadka.com'], $meta),
            'timestamp' => date(DATE_ATOM),
        ];

        return new self($status, $payload);
    }

    public static function error(string $code, string $message, int $status): self
    {
        return new self($status, [
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->payload;
    }

    public function send(): void
    {
        // Determine cache headers based on status
        $isSuccess = $this->status >= 200 && $this->status < 300;
        $isError = $this->status >= 400;

        // Compact JSON for speed/size
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        // Pretty print only when ?pretty=1 is requested (debug)
        if (isset($_GET['pretty']) && $_GET['pretty'] === '1') {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($this->payload, $flags);

        // ETag for conditional GET (saves bandwidth on repeated polls)
        $etag = $isSuccess ? '"' . md5($json) . '"' : null;
        if ($etag !== null && isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=60, s-maxage=120, stale-while-revalidate=60');
            return;
        }

        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-API-Cache: ' . ($isSuccess ? 'HIT' : 'MISS'));
        header('Vary: Accept-Encoding');

        if ($isSuccess) {
            // Cache successful responses at edge/CDN for 2min, allow stale 60s
            header('Cache-Control: public, max-age=60, s-maxage=120, stale-while-revalidate=60');
            if ($etag !== null) {
                header('ETag: ' . $etag);
            }
        } elseif ($isError && $this->status === 429) {
            header('Retry-After: 60');
            header('Cache-Control: no-store');
        } else {
            header('Cache-Control: no-store, max-age=0');
        }

        // Handle compression manually if client accepts gzip (php -S doesn't auto-gzip)
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $canGzip = str_contains(strtolower($acceptEncoding), 'gzip') && strlen($json) > 1024 && function_exists('gzencode');

        if ($canGzip) {
            $gz = gzencode($json, 6);
            if ($gz !== false) {
                header('Content-Encoding: gzip');
                header('Content-Length: ' . strlen($gz));
                echo $gz;
                return;
            }
        }

        header('Content-Length: ' . strlen($json));
        echo $json;
    }
}