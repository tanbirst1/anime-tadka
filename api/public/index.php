<?php

declare(strict_types=1);
if(!function_exists('str_starts_with')){function str_starts_with(string $h,string $n):bool{return $n===''||strncmp($h,$n,strlen($n))===0;}}
if(!function_exists('str_contains')){function str_contains(string $h,string $n):bool{return $n===''||strpos($h,$n)!==false;}}
if(!function_exists('str_ends_with')){function str_ends_with(string $h,string $n):bool{return $n===''||substr($h,-strlen($n))===$n;}}

/**
 * Front controller — AnimeTadka API.
 *
 * Run:  php -S localhost:8000 -t public
 */

use AnimeTadka\Bootstrap;
use AnimeTadka\Api\Response;

$__api_start = microtime(true);

require dirname(__DIR__) . '/src/autoload.php';

$config = Bootstrap::config();

// Performance: disable session, enable output buffering, set server timing
if (function_exists('opcache_get_status') && PHP_SAPI !== 'cli') {
    // hint opcache to keep this file cached
    @opcache_compile_file(__FILE__);
}

// CORS (public read-only API).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Powered-By: AnimeTadka-API/2.0');
header('Timing-Allow-Origin: *');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = Bootstrap::router($config);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$query = $_GET;

// Provider base-URL registry. Served straight from baseurl.php (no scraping)
// so any deployment — including a mirror of this app — can pull the current
// provider URLs and re-point itself via BASEURL_ENDPOINT.
if (in_array($path, ['/api/baseurl', '/api/baseurl/', '/baseurl', '/baseurl/'], true)) {
    $baseurls = \AnimeTadka\Api\BaseUrlRegistry::all();
    Response::success(['baseurls' => $baseurls], 200, [
        'count' => count($baseurls),
        'source' => 'api/baseurl.php',
    ])->send();
    exit;
}

// Root: API info (standalone "/" and mounted "/api").
if (in_array($path, ['/', '', '/api', '/api/'], true)) {
    Response::success([
        'name' => 'AnimeTadka API',
        'version' => '1.0.0',
        'providers' => ['animetadka', 'anikoto', 'toonstream', 'animedekho', 'watchhentai'],
        'endpoints' => [
            'animetadka' => [
                '/api/animetadka/health',
                '/api/animetadka/home',
                '/api/animetadka/latest?type=all|series|movies&page=N',
                '/api/animetadka/all?page=N',
                '/api/animetadka/search?q={query}',
                '/api/animetadka/anime/{slug}',
                '/api/animetadka/anime/{slug}/episodes',
                '/api/animetadka/episode/{hash}',
                '/api/animetadka/movie/{hash}',
                '/api/animetadka/category/{type}/{slug}?page=N&type=series|movies',
                '/api/animetadka/letter/{letter}?page=N&type=series|movies',
                '/api/animetadka/languages',
                '/api/animetadka/genres',
                '/api/animetadka/studios',
                '/api/animetadka/extra',
                '/api/animetadka/proxy/watch?server=thugs|toonx|hyx&hash={hash}',
                '/api/animetadka/proxy/download?dd={id}',
            ],
            'anikoto' => [
                '/api/anikoto/home',
                '/api/anikoto/index',
                '/api/anikoto/nav',
                '/api/anikoto/search?q={query}&page=N',
                '/api/anikoto/browse?page=N',
                '/api/anikoto/azlist/{sort}?page=N',
                '/api/anikoto/genre/{name}?page=N',
                '/api/anikoto/category/{name}?page=N',
                '/api/anikoto/type/{name}?page=N',
                '/api/anikoto/anime/{id}',
                '/api/anikoto/anime/{id}/episodes',
                '/api/anikoto/anime/{id}/ep/{num}',
                '/api/anikoto/cache/stats',
            ],
            'toonstream' => [
                '/api/toonstream',
                '/api/toonstream/health',
                '/api/toonstream/index',
                '/api/toonstream/home',
                '/api/toonstream/series/{slug}?seasons=all&src=true&server=all',
                '/api/toonstream/episode/{slug}?server=0,1,2',
                '/api/toonstream/movies',
                '/api/toonstream/movies/page/{page}',
                '/api/toonstream/movies/{slug}',
                '/api/toonstream/search/{query}?page=N',
                '/api/toonstream/category/{path}?page=N',
                '/api/toonstream/cast/{name}?page=N',
                '/api/toonstream/letter/{letter}?page=N',
            ],
            'animedekho' => [
                '/api/animedekho',
                '/api/animedekho/health',
                '/api/animedekho/home',
                '/api/animedekho/index',
                '/api/animedekho/search/{query}?page=N',
                '/api/animedekho/series-list?page=N',
                '/api/animedekho/series/{slug}?seasons=all&src=true&server=all',
                '/api/animedekho/movie/{slug}',
                '/api/animedekho/episode/{slug}?server=all',
                '/api/animedekho/token/{slug}?kind=episode|movie   (no slug = random pick)',
                '/api/animedekho/movies?page=N',
                '/api/animedekho/category/{path}?page=N',
                '/api/animedekho/cast/{name}?page=N',
                '/api/animedekho/letter/{letter}?page=N',
            ],
            'watchhentai' => [
                '/api/watchhentai',
                '/api/watchhentai/health',
                '/api/watchhentai/index',
                '/api/watchhentai/home',
                '/api/watchhentai/series/{slug}?seasons=all&src=true&server=all',
                '/api/watchhentai/episode/{slug}?server=0,1,2',
                '/api/watchhentai/movies',
                '/api/watchhentai/movies/page/{page}',
                '/api/watchhentai/movies/{slug}',
                '/api/watchhentai/extra',
                '/api/watchhentai/search/{query}?page=N',
                '/api/watchhentai/category/{path}?page=N',
                '/api/watchhentai/cast/{name}?page=N',
                '/api/watchhentai/letter/{letter}?page=N',
            ],
        ],
        'legacy' => 'The original /api/{endpoint} routes remain available and unchanged.',
    ])->send();
    exit;
}

try {
    $response = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $query);
} catch (\Throwable $e) {
    // Last-resort safety net: never leak stack traces.
    Bootstrap::logger($config)->error('Unhandled exception', ['error' => $e->getMessage()]);
    $response = Response::error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

// Server-Timing header for observability (how long PHP spent)
$elapsedMs = (microtime(true) - $__api_start) * 1000;
header(sprintf('Server-Timing: app;dur=%.2f', $elapsedMs));
header(sprintf('X-Response-Time: %.2fms', $elapsedMs));

$response->send();