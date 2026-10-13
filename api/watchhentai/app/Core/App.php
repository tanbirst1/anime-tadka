<?php

declare(strict_types=1);

namespace WatchHentaiApi\Core;

use WatchHentaiApi\Providers\WatchHentai;
use WatchHentaiApi\Utils\Cache;
use WatchHentaiApi\Utils\HttpClient;
use WatchHentaiApi\Utils\Url;

/**
 * Application kernel: resolves the request, dispatches to the provider,
 * and shapes every response through the shared Response helpers.
 *
 * Provider responses are cached as finished JSON payloads (see
 * Cache::set/get) keyed by route + params; the switch in config.json
 * (`cache_enabled`) turns the whole cache on or off.
 */
final class App
{
    private WatchHentai $provider;

    private Cache $cache;

    public function __construct()
    {
        $this->cache = new Cache(Config::CACHE_DIR);
        $http = new HttpClient();
        $this->provider = new WatchHentai($http, Config::baseUrl());
    }

    public function run(): never
    {
        Response::cors();

        // CORS preflight
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $path = $this->resolvePath();
        $query = $_GET;
        $route = $query['route'] ?? null;
        if (is_string($route) && $route !== '') {
            $path = '/' . trim($route, '/');
        }

        try {
            $match = (new Router())->match($path, $query);
            $this->dispatch($match['handler'], $match['params']);
        } catch (ScrapeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
            Response::fail($e->getMessage(), $status);
        } catch (\Throwable $e) {
            Response::fail('Internal error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Compute an endpoint response through the JSON result cache: cache hit
     * serves the stored payload, otherwise $compute() runs and its array is
     * persisted under $namespace/$key (when cache_enabled in config.json).
     *
     * @param callable(): array $compute
     * @param callable(array): array $stats
     */
    private function cached(string $namespace, string $key, callable $compute, callable $stats): never
    {
        $stored = Config::cacheEnabled() ? $this->cache->get($namespace, $key) : null;
        if ($stored !== null) {
            Response::ok($stored, $stats($stored));
        }
        $results = $compute();
        // Never persist failure skeletons — they would pin a broken page for
        // the whole cache TTL instead of retrying on the next request.
        if (Config::cacheEnabled() && ($results['failed'] ?? false) !== true) {
            $this->cache->set($namespace, $key, $results);
        }
        Response::ok($results, $stats($results));
    }

    /**
     * @param array<string, string|int> $params
     */
    private function dispatch(string $handler, array $params): never
    {
        switch ($handler) {
            case 'info':
                Response::ok([
                    'name' => 'WatchHentai API',
                    'description' => 'Hentai scraping API (PHP)',
                    'provider' => Config::PROVIDER,
                    'version' => Config::VERSION,
                    'baseUrl' => Config::baseUrl(),
                    'endpoints' => [
                        'GET /index',
                        'GET /home',
                        'GET /series/:slug?seasons=all&src=true&server=all',
                        'GET /episode/:slug?server=0,1,2',
                        'GET /movies',
                        'GET /movies/page/:page',
                        'GET /movies/:slug',
                        'GET /extra',
                        'GET /search/:query?page=N',
                        'GET /category/:path?page=N',
                        'GET /cast/:name?page=N',
                        'GET /letter/:letter?page=N',
                    ],
                ]);

            case 'health':
                Response::ok([
                    'status' => 'ok',
                    'timestamp' => gmdate('c'),
                    'php' => PHP_VERSION,
                ]);

            case 'index':
                $this->cached('home', 'index', fn () => $this->provider->index(), static fn (array $r) => [
                    'sectionCount' => count($r['sections']),
                    'paragraphCount' => count($r['intro']) + array_sum(array_map(
                        static fn (array $s) => count($s['paragraphs']),
                        $r['sections']
                    )),
                ]);

            case 'home':
                $this->cached('home', 'home', fn () => $this->provider->home(), static fn (array $r) => [
                    'episodesCount' => count($r['latestEpisodes']),
                    'seriesCount' => count($r['latestSeries']),
                    'moviesCount' => count($r['movies']),
                ]);

            case 'home_search':
                $this->cached('search', 'search:' . (string) $params['query'], fn () => $this->provider->search((string) $params['query']), static fn (array $r) => [
                    'resultsCount' => count($r['results']),
                ]);

            case 'home_cast':
                $name = (string) $params['name'];
                $this->cached('cast', 'cast:' . $name, fn () => $this->provider->listing('/cast/' . Url::encodePath($name)), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'home_letter':
                $letter = (string) $params['letter'];
                $this->cached('letter', 'letter:' . $letter, fn () => $this->provider->listing('/letter/' . Url::encodePath($letter), 1, false), static fn (array $r) => [
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
                $this->cached('series', $key, fn () => $this->provider->detail($slug, $seasons, $withSources, $server, 'series'), static fn (array $r) => [
                    'totalSeasons' => count($r['availableSeasons']),
                    'requestedSeasons' => count($r['seasons']),
                    'fetchedEpisodes' => array_sum(array_map(static fn (array $s) => count($s['episodes']), $r['seasons'])),
                    'includesServerSources' => $withSources,
                ]);

            case 'episode':
                $slug = (string) $params['slug'];
                $server = isset($params['server']) ? (string) $params['server'] : null;
                $this->cached('episode', 'episode:' . $slug . '|' . ($server ?? ''), fn () => $this->provider->episode($slug, $server), static fn (array $r) => [
                    'totalServersAvailable' => count($r['servers']),
                    'serversReturned' => count($r['servers']),
                ]);

            case 'movies':
                $this->cached('category', 'movies:1', fn () => $this->provider->listing('/3d/'), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'movies_page':
                $page = max(1, (int) $params['page']);
                $this->cached('category', 'movies:' . $page, fn () => $this->provider->listing('/3d/', $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'movie':
                $slug = (string) $params['slug'];
                $this->cached('series', 'movie:' . $slug, fn () => $this->provider->detail($slug, null, false, null, 'movies'), static fn (array $r) => [
                    'totalSeasons' => count($r['availableSeasons']),
                    'requestedSeasons' => count($r['seasons']),
                    'fetchedEpisodes' => array_sum(array_map(static fn (array $s) => count($s['episodes']), $r['seasons'])),
                    'includesServerSources' => false,
                ]);

            case 'extra':
                $this->cached('home', 'extra', fn () => $this->provider->extra(), static fn (array $r) => [
                    'linksCount' => count($r['links']),
                    'typesCount' => count($r['types']),
                    'statusesCount' => count($r['statuses']),
                    'yearsCount' => count($r['years']),
                    'genresCount' => count($r['genres']),
                    'languagesCount' => count($r['languages']),
                ]);

            case 'search':
                $query = (string) $params['query'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                $this->cached('search', 'search:' . $query . '|' . $page, fn () => $this->provider->search($query, $page), static function (array $r) {
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
                $this->cached('category', 'category:' . $path . '|' . $page, fn () => $this->provider->listing('/category/' . Url::encodePath($path), $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'cast':
                $name = (string) $params['name'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                $this->cached('cast', 'cast:' . $name . '|' . $page, fn () => $this->provider->listing('/cast/' . Url::encodePath($name), $page), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            case 'letter':
                $letter = (string) $params['letter'];
                $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
                $this->cached('letter', 'letter:' . $letter . '|' . $page, fn () => $this->provider->listing('/letter/' . Url::encodePath($letter), $page, false), static fn (array $r) => [
                    'resultsCount' => count($r['items']),
                    'page' => $r['page'],
                    'totalPages' => $r['totalPages'],
                ]);

            default:
                Response::fail('Route not found: ' . (string) ($params['path'] ?? '/'), 404);
        }
    }

    private function resolvePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['PATH_INFO'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Sub-folder deployment (e.g. /watchhentai/home): strip the front
        // controller's directory from the request path before routing.
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = str_replace('/index.php', '', $path);
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
