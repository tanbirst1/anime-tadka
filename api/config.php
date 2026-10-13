<?php

declare(strict_types=1);

/**
 * Global configuration for the AnimeTadka API.
 *
 * Single source of truth — centralises HTTP, cache, logging, and
 * per-provider settings for the whole merged application.
 *
 * Usage:
 *   $config = require __DIR__ . '/config.php';
 *   $timeout = $config['http']['timeout'];
 *
 * Overriding at runtime:
 *   .env values (loaded by src/Bootstrap.php) take precedence for the
 *   animetadka provider; anikoto/toonstream honour their own module
 *   configs, which can be pointed here for unified control.
 */

// ── Load .env (cheap, no-op when absent) ───────────────────────────
$env = [];
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // strip surrounding quotes
        if (strlen($value) >= 2 && $value[0] === $value[strlen($value) - 1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
}

$envOr = static fn (string $key, mixed $default): mixed => $env[$key] ?? $default;

// ── Return the merged configuration ────────────────────────────────
return [

    // ── Application ────────────────────────────────────────────────
    'app' => [
        'name'    => 'AnimeTadka API',
        'version' => '2.0.0',
        'repo'    => null,
    ],

    // ── HTTP defaults (shared across providers) ────────────────────
    'http' => [
        'user_agent'       => $envOr('HTTP_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'),
        'timeout'          => (int) $envOr('HTTP_TIMEOUT', 15),
        'connect_timeout'  => (int) $envOr('HTTP_CONNECT_TIMEOUT', 7),
        'max_retries'      => (int) $envOr('HTTP_MAX_RETRIES', 1),
        'retry_backoff_ms' => (int) $envOr('HTTP_RETRY_BACKOFF_MS', 600),
        'request_delay_ms' => (int) $envOr('REQUEST_DELAY_MS', 0),
        'proxy'            => $envOr('HTTP_PROXY', ''),
    ],

    // ── Base URLs ──────────────────────────────────────────────────
    // Delegates to baseurl.php — change mirrors in one place.
    'baseurls' => require __DIR__ . '/baseurl.php',

    // ── Provider-specific overrides ────────────────────────────────
    'providers' => [

        'animetadka' => [
            'base_url' => $envOr('BASE_URL', 'https://animetadka.com'),
            'cache'    => [
                'enabled' => filter_var($envOr('CACHE_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
                'ttl'     => (int) $envOr('CACHE_TTL', 7200),
                'dir'     => $envOr('CACHE_DIR', 'storage/cache'),
            ],
        ],

        'anikoto' => [
            'base_url' => 'https://anikototv.to',
            'api_url'  => 'https://anikotoapi.site',
            'timeout'  => 15,
            'cache'    => [
                'dir' => __DIR__ . '/anikoto/cache',
                'ttl' => [
                    'home'    => 300,
                    'search'  => 180,
                    'browse'  => 300,
                    'anime'   => 3600,
                    'episodes'=> 1800,
                    'episode' => 1800,
                    'genre'   => 600,
                    'azlist'  => 3600,
                    'nav'     => 3600,
                ],
            ],
        ],

        'toonstream' => [
            'base_url' => 'https://toonstream.us',
            'timeout'  => 25,
            'retries'  => 2,
            'cache'    => [
                'enabled' => true,
                'dir'     => __DIR__ . '/toonstream/storage/cache',
                'ttl' => [
                    'home'    => 300,
                    'search'  => 300,
                    'series'  => 1800,
                    'episode' => 3600,
                    'category'=> 600,
                    'cast'    => 600,
                    'letter'  => 600,
                ],
            ],
        ],

        'watchhentai' => [
            'base_url' => 'https://watchhentai.net',
            'timeout'  => 25,
            'retries'  => 2,
            'cache'    => [
                'enabled' => true,
                'dir'     => __DIR__ . '/watchhentai/storage/cache',
                'ttl' => [
                    'home'    => 300,
                    'search'  => 300,
                    'series'  => 1800,
                    'episode' => 3600,
                    'category'=> 600,
                    'cast'    => 600,
                    'letter'  => 600,
                ],
            ],
        ],
    ],

    // ── Logging ────────────────────────────────────────────────────
    'logging' => [
        'log_dir'  => $envOr('LOG_DIR', 'storage/logs'),
        'log_level'=> $envOr('LOG_LEVEL', 'info'),
    ],

    // ── API behaviour ──────────────────────────────────────────────
    'api' => [
        'debug'  => filter_var($envOr('API_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'cors'   => [
            'origin'  => '*',
            'methods' => 'GET, OPTIONS',
            'headers' => 'Content-Type',
        ],
    ],
];
