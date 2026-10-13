<?php

declare(strict_types=1);

namespace ToonStreamApi\Core;

/**
 * Path router. Ordered pattern table — first match wins — plus query-string
 * fallbacks for the README's `?slug=` / `?q=` variants.
 *
 * Every match returns a handler name + merged params (decoded route params
 * first, then relevant query params).
 */
final class Router
{
    private const ROUTES = [
        'info'        => '#^/$#',
        'health'      => '#^/health$#',
        'index'       => '#^/index$#',
        'home'        => '#^/home$#',
        'home_search' => '#^/home/search/(?P<query>.+)$#',
        'home_cast'   => '#^/home/cast_tv/(?P<name>[^/]+)$#',
        'home_letter' => '#^/home/letter/(?P<letter>[^/]+)$#',
        'series'      => '#^/series/(?P<slug>[^/]+)$#',
        'episode'     => '#^/episode/(?P<slug>[^/]+)$#',
        'movies'      => '#^/movies$#',
        'movies_page' => '#^/movies/page/(?P<page>\d+)$#',
        'movie'       => '#^/movies/(?P<slug>[^/]+)$#',
        'search'      => '#^/search/(?P<query>.+)$#',
        'category'    => '#^/category/(?P<path>.+?)(?:/page/(?P<page>\d+))?$#',
        'cast'        => '#^/cast/(?P<name>[^/]+?)(?:/page/(?P<page>\d+))?$#',
        'letter'      => '#^/letter/(?P<letter>[^/]+?)(?:/page/(?P<page>\d+))?$#',
    ];

    /**
     * Query-string fallbacks: handler => query keys that can name the resource.
     */
    private const QUERY_ROUTES = [
        'series' => ['slug'],
        'episode' => ['slug'],
        'search' => ['q', 's'],
        'home' => ['s'],
    ];

    /** Query keys copied through to handler params. */
    private const PASSTHROUGH = ['seasons', 'src', 'server', 'page'];

    /**
     * @param array<string, mixed> $query
     * @return array{handler: string, params: array<string, string|int>}
     */
    public function match(string $path, array $query): array
    {
        $path = $path === '' ? '/' : $path;

        foreach (self::ROUTES as $handler => $pattern) {
            if (preg_match($pattern, $path, $m)) {
                return $this->build($handler, $m, $query);
            }
        }

        // Query-string variants: /series?slug=…, /episode?slug=…, /search?q=…
        foreach (self::QUERY_ROUTES as $handler => $keys) {
            if ($path !== '/' . $handler) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($query[$key]) && $query[$key] !== '') {
                    return $this->build($handler, [$key => (string) $query[$key]], $query);
                }
            }
        }

        return ['handler' => 'not_found', 'params' => ['path' => $path]];
    }

    /**
     * @param array<int|string, string> $m
     * @param array<string, mixed> $query
     * @return array{handler: string, params: array<string, string|int>}
     */
    private function build(string $handler, array $m, array $query): array
    {
        $params = [];
        foreach ($m as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }
        foreach (self::PASSTHROUGH as $key) {
            if (isset($query[$key]) && $query[$key] !== '') {
                $params[$key] = (string) $query[$key];
            }
        }
        return ['handler' => $handler, 'params' => $params];
    }
}
