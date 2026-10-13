<?php

declare(strict_types=1);

namespace ToonStreamApi\Utils;

/** URL helpers shared across providers. */
final class Url
{
    /**
     * Resolve a possibly-relative URL against the provider base.
     * Handles protocol-relative (//…) and already-absolute URLs.
     */
    public static function absolute(string $base, ?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        return $base . ($url[0] === '/' ? $url : '/' . $url);
    }

    /** Last path segment of an href — the stable slug ("/series/jujutsu-kaisen" → "jujutsu-kaisen"). */
    public static function slug(string $href): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?? $href;
        return basename(rtrim($path, '/'));
    }

    /** Encode each path segment (keeps nested paths like "language/hindi-language" intact). */
    public static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
