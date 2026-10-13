<?php

declare(strict_types=1);

namespace ToonStreamApi\Utils;

use ToonStreamApi\Core\Config;

/**
 * Tiny file-backed JSON cache. One file per (namespace, key) pair; expired
 * entries are deleted lazily on read. Writes go through a temp file +
 * rename so readers never observe half-written JSON.
 *
 * Values are the API result payloads (arrays), serialized with json_encode —
 * storage/cache/ therefore holds *.json response snapshots, not raw HTML.
 */
final class Cache
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create cache directory: ' . $dir);
        }
    }

    /**
     * @return array<mixed>|null the cached result payload, or null on miss/expiry
     */
    public function get(string $namespace, string $key): ?array
    {
        $file = $this->file($namespace, $key);
        if (!is_file($file)) {
            return null;
        }
        $ttl = Config::cacheTtl($namespace);
        if (time() - (int) filemtime($file) > $ttl) {
            @unlink($file);
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $value
     */
    public function set(string $namespace, string $key, array $value): void
    {
        $target = $this->file($namespace, $key);
        $tmp = $target . '.' . getmypid() . '.tmp';
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($tmp, $json) === false) {
            return;
        }
        @rename($tmp, $target);
        @chmod($target, 0664);
    }

    private function file(string $namespace, string $key): string
    {
        return $this->dir . '/' . $namespace . '_' . sha1($key) . '.json';
    }
}
