<?php

declare(strict_types=1);

namespace AnimeDekhoApi\Core;

/**
 * Static configuration. Everything is overridable via environment variables
 * so the same codebase can target mirrors of the site.
 */
final class Config
{
    /** Base URL of the scraped site (override with ANIMEDEKHO_BASE_URL). */
    public const BASE_URL = 'https://animedekho.tv';

    public const PROVIDER = 'animedekho';

    public const VERSION = '1.0.0';

    /** Browser-like user agent used for every request. */
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public const TIMEOUT = 25;

    public const RETRIES = 2;

    public static function timeout(): int {
        $env = getenv('ANIMEDEKHO_TIMEOUT');
        if ($env !== false && $env !== '' && is_numeric($env)) {
            return max(5, (int) $env);
        }
        return self::TIMEOUT;
    }

    /** Where the file cache lives (runtime-created). */
    public const CACHE_DIR = ANIMEDEKHO_ROOT . '/storage/cache';

    /**
     * Persistent cookie jar. animedekho's episode pages are gated behind an
     * ad-skip wall whose verify URL sets a `dgt_verified` cookie valid ~24h —
     * the jar lets the client pass that gate transparently.
     */
    public const COOKIE_FILE = ANIMEDEKHO_ROOT . '/storage/cache/cookies.txt';

    /**
     * Seconds to dwell on the ad-gate page before hitting its verify token.
     * The site's gate rejects a verify request that arrives too soon after the
     * token was minted (the `i` query parameter), answering 403 "That was too
     * fast" and issuing no unlock cookie. Override with ANIMEDEKHO_GATE_DWELL.
     */
    public const GATE_DWELL = 10;

    /** Per-content-type TTL in seconds (home/search 5m, series 30m, servers 1h, listings 10m). */
    public const CACHE_TTL = [
        'home' => 300,
        'search' => 300,
        'series' => 1800,
        'episode' => 3600,
        'token' => 300,
        'category' => 600,
        'cast' => 600,
        'letter' => 600,
        // Resolved wrapper → direct player URL. Player embeds are stable for
        // hours, so a 15-minute window keeps tab switching instant while still
        // picking up a rotated URL well within the episode cache's lifetime.
        'wrapper_src' => 900,
    ];

    /** Project-root config.json, loaded once. */
    private static ?array $fileConfig = null;

    /**
     * Whether the response cache is on. Controlled by `cache_enabled` in
     * config.json (defaults to true when the file or key is absent).
     */
    public static function cacheEnabled(): bool
    {
        return (bool) (self::fileConfig()['cache_enabled'] ?? true);
    }

    /**
     * TTL (seconds) for a cache namespace: config.json `cache_ttl` overrides
     * the built-in defaults.
     */
    public static function cacheTtl(string $namespace): int
    {
        $overrides = self::fileConfig()['cache_ttl'] ?? [];
        if (isset($overrides[$namespace]) && is_int($overrides[$namespace])) {
            return $overrides[$namespace];
        }
        return self::CACHE_TTL[$namespace] ?? 300;
    }

    /** @return array<string, mixed> */
    private static function fileConfig(): array
    {
        if (self::$fileConfig !== null) {
            return self::$fileConfig;
        }
        $path = ANIMEDEKHO_ROOT . '/config.json';
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $data = $raw !== false ? json_decode($raw, true) : null;
        self::$fileConfig = is_array($data) ? $data : [];
        return self::$fileConfig;
    }

    public static function baseUrl(): string
    {
        $env = getenv('ANIMEDEKHO_BASE_URL');
        return $env !== false && $env !== '' ? rtrim($env, '/') : self::BASE_URL;
    }

    /**
     * Cookie jar path. Overridable with ANIMEDEKHO_COOKIE_FILE so a test can
     * point the client at a scratch jar instead of the shared one.
     */
    public static function cookieFile(): string
    {
        $env = getenv('ANIMEDEKHO_COOKIE_FILE');
        return $env !== false && $env !== '' ? $env : self::COOKIE_FILE;
    }

    /**
     * Dwell (seconds) to wait out before activating a gate verify token.
     * Overridable with ANIMEDEKHO_GATE_DWELL.
     */
    public static function gateDwell(): int
    {
        $env = getenv('ANIMEDEKHO_GATE_DWELL');
        if ($env !== false && $env !== '' && is_numeric($env)) {
            return max(0, (int) $env);
        }
        return self::GATE_DWELL;
    }
}
