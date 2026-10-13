<?php

declare(strict_types=1);

namespace AnimeTadka\Cache;

/**
 * Simple file-based cache with TTL + per-request memory and atomic writes.
 */
final class FileCache
{
    private string $dir;
    private int $ttl;

    /** @var array<string, array{expires:int, value:string}> */
    private array $memory = [];

    public function __construct(string $dir, int $ttl = 3600)
    {
        $this->dir = rtrim($dir, '/');
        $this->ttl = max(0, $ttl);
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function get(string $key, ?int $ttl = null): ?string
    {
        $ttl = $ttl ?? $this->ttl;
        $cacheKey = sha1($key);

        // memory fast-path
        if (isset($this->memory[$cacheKey])) {
            $entry = $this->memory[$cacheKey];
            if ($entry['expires'] === 0 || $entry['expires'] > time()) {
                return $entry['value'];
            }
            unset($this->memory[$cacheKey]);
        }

        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        if ($ttl > 0 && (time() - (int) @filemtime($path)) > $ttl) {
            @unlink($path);
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        // populate memory
        $this->memory[$cacheKey] = [
            'value' => $content,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
        // cap memory
        if (count($this->memory) > 300) {
            array_shift($this->memory);
        }
        return $content;
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->ttl;
        $cacheKey = sha1($key);
        $this->memory[$cacheKey] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
        if (count($this->memory) > 300) {
            array_shift($this->memory);
        }

        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $path = $this->path($key);
        $tmp = $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        // atomic write via temp file + rename (avoids 0-byte files on concurrent writes)
        if (@file_put_contents($tmp, $value) !== false) {
            @rename($tmp, $path);
        } else {
            @file_put_contents($path, $value);
        }
        @unlink($tmp);
    }

    public function has(string $key, ?int $ttl = null): bool
    {
        return $this->get($key, $ttl) !== null;
    }

    public function forget(string $key): void
    {
        unset($this->memory[sha1($key)]);
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clear(): void
    {
        $this->memory = [];
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.cache';
    }
}