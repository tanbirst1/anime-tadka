<?php

declare(strict_types=1);

namespace AnimeTadka\Api;

/**
 * Reads the shared provider base-URL registry (api/baseurl.php) and, when a
 * remote endpoint is configured, lets a deployment refresh that registry from
 * a central `/api/baseurl` so provider mirrors can be re-pointed without a
 * code change.
 *
 * Precedence for any lookup: env override -> remote registry -> local file.
 */
final class BaseUrlRegistry
{
    private const REMOTE_ENV = 'BASEURL_ENDPOINT';

    private const REMOTE_TTL = 3600;

    private const CACHE_KEY = 'baseurl_registry';

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * Full provider => base URL map.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $urls = self::fromRemote() ?? self::fromFile();
        $urls = array_filter($urls, static fn ($v) => is_string($v) && $v !== '');

        self::$cache = $urls;
        return $urls;
    }

    /**
     * Base URL for one provider key ('' when unknown).
     */
    public static function get(string $provider): string
    {
        $all = self::all();
        return (string) ($all[$provider] ?? '');
    }

    /**
     * Resolve a provider base URL with an env override taking precedence,
     * e.g. ANIMEDEKHO_BASE_URL beats the registry.
     */
    public static function resolve(string $provider, string $envVar = ''): string
    {
        if ($envVar !== '') {
            $env = getenv($envVar);
            if ($env !== false && $env !== '') {
                return rtrim($env, '/');
            }
        }
        return rtrim(self::get($provider), '/');
    }

    /** @return array<string, string> */
    private static function fromFile(): array
    {
        $path = self::registryPath();
        if ($path === null || !is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /** @return array<string, string>|null */
    private static function fromRemote(): ?array
    {
        $endpoint = getenv(self::REMOTE_ENV);
        if ($endpoint === false || $endpoint === '') {
            return null;
        }

        $cacheFile = self::cacheFile();
        if ($cacheFile !== null && is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::REMOTE_TTL) {
            $raw = @file_get_contents($cacheFile);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        $fetched = self::fetchRemote($endpoint);
        if ($fetched !== null && $fetched !== []) {
            if ($cacheFile !== null) {
                @file_put_contents($cacheFile, json_encode($fetched, JSON_UNESCAPED_SLASHES));
            }
            return $fetched;
        }

        // Remote unreachable: serve the stale cache if we have one.
        if ($cacheFile !== null && is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return array<string, string>|null */
    private static function fetchRemote(string $endpoint): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'AnimeTadka-BaseUrlResolver/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            return null;
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return null;
        }
        $urls = $decoded['data']['baseurls'] ?? $decoded['baseurls'] ?? null;
        if (!is_array($urls)) {
            return null;
        }
        $out = [];
        foreach ($urls as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private static function registryPath(): ?string
    {
        // api/baseurl.php sits one level above the API src tree.
        $path = dirname(__DIR__, 2) . '/baseurl.php';
        return is_file($path) ? $path : null;
    }

    private static function cacheFile(): ?string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . '/' . self::CACHE_KEY . '.json';
    }

    /** Test seam: drop the per-request memoised registry. */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
