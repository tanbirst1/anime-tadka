<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;
use ToonStreamApi\Core\Config;
use ToonStreamApi\Core\ScrapeException;
use ToonStreamApi\Providers\ToonStream;
use ToonStreamApi\Utils\Cache;
use ToonStreamApi\Utils\HttpClient;

final class ToonStreamController
{
    private ?ToonStream $provider = null;
    private ?Cache $cache = null;
    private bool $loaded = false;

    private function loadModule(): void
    {
        if ($this->loaded) {
            return;
        }
        // Mirror the standalone front controller: silence deprecation noise
        // (PHP 8.5 may emit notices that would corrupt JSON output).
        ini_set('display_errors', '0');
        require_once dirname(__DIR__, 3) . '/toonstream/app/autoload.php';
        $this->loaded = true;
    }

    public function info(): Response
    {
        $this->loadModule();
        return Response::success([
            'name' => 'ToonStream API',
            'description' => 'Anime & cartoon scraping API (PHP)',
            'provider' => Config::PROVIDER,
            'version' => Config::VERSION,
            'baseUrl' => Config::baseUrl(),
            'endpoints' => [
                'GET /api/toonstream/index',
                'GET /api/toonstream/home',
                'GET /api/toonstream/series/{slug}?seasons=all&src=true&server=all',
                'GET /api/toonstream/episode/{slug}?server=0,1,2',
                'GET /api/toonstream/movies',
                'GET /api/toonstream/movies/page/{page}',
                'GET /api/toonstream/movies/{slug}',
                'GET /api/toonstream/search/{query}?page=N',
                'GET /api/toonstream/category/{path}?page=N',
                'GET /api/toonstream/cast/{name}?page=N',
                'GET /api/toonstream/letter/{letter}?page=N',
            ],
        ], 200, ['provider' => Config::PROVIDER]);
    }

    public function health(): Response
    {
        $this->loadModule();
        return Response::success([
            'status' => 'ok',
            'timestamp' => gmdate('c'),
            'php' => PHP_VERSION,
        ], 200, ['provider' => Config::PROVIDER]);
    }

    /**
     * Catch-all: everything under /api/toonstream/{route} is handed to the
     * toonstream module's own Router (full parity incl. nested category
     * paths and ?slug=/ ?q= / ?s= query fallbacks).
     *
     * @param array<string, string> $params
     * @param array<string, mixed>  $query
     */
    public function route(array $params, array $query): Response
    {
        $this->loadModule();
        $path = '/' . ltrim($params['route'] ?? '', '/');
        $match = (new \ToonStreamApi\Core\Router())->match($path, $query);
        // The module's query fallback keys search params as 'q'/'s' while the
        // search handler reads 'query' — normalize so ?q=/?s= work (module bug).
        if ($match['handler'] === 'search' && !isset($match['params']['query'])) {
            $match['params']['query'] = (string) ($match['params']['q'] ?? $match['params']['s'] ?? '');
        }
        try {
            return $this->dispatch($match['handler'], $match['params']);
        } catch (ScrapeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
            return Response::error('SOURCE_ERROR', $e->getMessage(), $status);
        } catch (\Throwable $e) {
            return Response::error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
        }
    }

    private function provider(): ToonStream
    {
        if ($this->provider === null) {
            $this->provider = new ToonStream(new HttpClient(), Config::baseUrl());
        }
        return $this->provider;
    }

    private function cache(): Cache
    {
        if ($this->cache === null) {
            $this->cache = new Cache(Config::CACHE_DIR);
        }
        return $this->cache;
    }

    /**
     * @param callable(): array $compute
     * @param callable(array): array $stats
     */
    private function cached(string $namespace, string $key, callable $compute, callable $stats): Response
    {
        $useCache = Config::cacheEnabled();
        $stored = $useCache ? $this->cache()->get($namespace, $key) : null;
        if ($stored !== null) {
            return Response::success($stored, 200, ['provider' => Config::PROVIDER, 'stats' => $stats($stored)]);
        }
        $results = $compute();
        if ($useCache) {
            $this->cache()->set($namespace, $key, $results);
        }
        return Response::success($results, 200, ['provider' => Config::PROVIDER, 'stats' => $stats($results)]);
    }

    /**
     * Mirror of ToonStreamApi\Core\App::dispatch — same handler names, cache
     * namespaces/keys, provider calls and stats, but returning main-API Responses.
     *
     * @param array<string, string|int> $params
     */
    private function dispatch(string $handler, array $params): Response
    {
        switch ($handler) {
            case 'info':
                return $this->info();

            case 'health':
                return $this->health();

            case 'index':
                return $this->cached('home', 'index', fn () => $this->provider()->index(), static fn (array $r) => [
                    'sectionCount' => count($r['sections']),
                    'paragraphCount' => count($r['intro']) + array_sum(array_map(
                        static fn (array $s) => count($s['paragraphs']),
                        $r['sections']
                    )),
                ]);

            case 'home':
                return $this->cached('home', 'home', fn () => $this->provider()->home(), static fn (array $r) => [
                    'episodesCount' => count($r['latestEpisodes']),
                    'seriesCount' => count($r['latestSeries']),
                    'moviesCount' => count($r['movies']),
                ]);

            case 'home_search':
                return $this->cached('search', 'search:' . (string) $params['query'], fn () => $this->provider()->search((string) $params['query']), static fn (array $r) => [
                    'resultsCount' => count($r['results']),
                ]);

            case 'home_cast':
                $name = (string) $params['name'];
                return $this->cached('cast', 'cast:' . $name, fn () => $this->provider()->listing('/cast/' . \ToonStreamApi\Utils\Url::encodePath($name)), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'home_letter':
                $letter = (string) $params['letter'];
                return $this->cached('letter', 'letter:' . $letter, fn () => $this->provider()->listing('/letter/' . \ToonStreamApi\Utils\Url::encodePath($letter), 1, false), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'series':
                $slug = (string) $params['slug'];
                $seasons = isset($params['seasons']) ? (string) $params['seasons'] : null;
                $withSources = isset($params['src']) && in_array(strtolower((string) $params['src']), ['true', '1', 'yes'], true);
                $server = isset($params['server']) ? (string) $params['server'] : null;
                $key = 'series:' . $slug . '|' . ($seasons ?? '') . '|' . ($withSources ? 'src' : '') . '|' . ($server ?? '');
                return $this->cached('series', $key, fn () => $this->provider()->detail($slug, $seasons, $withSources, $server, 'series'), static fn (array $r) => [
                    'totalSeasons' => count($r['availableSeasons']),
                    'requestedSeasons' => count($r['seasons']),
                    'fetchedEpisodes' => array_sum(array_map(static fn (array $s) => count($s['episodes']), $r['seasons'])),
                    'includesServerSources' => $withSources,
                ]);

            case 'episode':
                $slug = (string) $params['slug'];
                $server = isset($params['server']) ? (string) $params['server'] : null;
                return $this->cached('episode', 'episode:' . $slug . '|' . ($server ?? ''), fn () => $this->provider()->episode($slug, $server), static fn (array $r) => [
                    'totalServersAvailable' => count($r['servers']),
                    'serversReturned' => count($r['servers']),
                ]);

            case 'movies':
                return $this->cached('category', 'movies:1', fn () => $this->provider()->listing('/category/movies/'), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'movies_page':
                $page = max(1, (int) $params['page']);
                return $this->cached('category', 'movies:' . $page, fn () => $this->provider()->listing('/category/movies/', $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'movie':
                $slug = (string) $params['slug'];
                return $this->cached('series', 'movie:' . $slug, fn () => $this->provider()->detail($slug, null, false, null, 'movies'), static fn (array $r) => [
                    'totalSeasons' => count($r['availableSeasons']),
                    'requestedSeasons' => count($r['seasons']),
                    'fetchedEpisodes' => array_sum(array_map(static fn (array $s) => count($s['episodes']), $r['seasons'])),
                    'includesServerSources' => false,
                ]);

            case 'search':
                $query = (string) $params['query'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                return $this->cached('search', 'search:' . $query . '|' . $page, fn () => $this->provider()->search($query, $page), static function (array $r) {
                    $seriesCount = 0;
                    foreach ($r['results'] as $item) {
                        if ($item['contentType'] === 'series') {
                            $seriesCount++;
                        }
                    }
                    return [
                        'resultsCount' => count($r['results']),
                        'seriesCount' => $seriesCount,
                        'moviesCount' => count($r['results']) - $seriesCount,
                    ];
                });

            case 'category':
                $path = (string) $params['path'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                return $this->cached('category', 'category:' . $path . '|' . $page, fn () => $this->provider()->listing('/category/' . \ToonStreamApi\Utils\Url::encodePath($path), $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'cast':
                $name = (string) $params['name'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                return $this->cached('cast', 'cast:' . $name . '|' . $page, fn () => $this->provider()->listing('/cast/' . \ToonStreamApi\Utils\Url::encodePath($name), $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'letter':
                $letter = (string) $params['letter'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                return $this->cached('letter', 'letter:' . $letter . '|' . $page, fn () => $this->provider()->listing('/letter/' . \ToonStreamApi\Utils\Url::encodePath($letter), $page, false), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            default:
                return Response::error('NOT_FOUND', 'Route not found: ' . (string) ($params['path'] ?? '/'), 404);
        }
    }
}