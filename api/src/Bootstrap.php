<?php

declare(strict_types=1);

namespace AnimeTadka;

use AnimeTadka\Api\Controllers\AnimeController;
use AnimeTadka\Api\Controllers\AnimeDekhoController;
use AnimeTadka\Api\Controllers\AnikotoController;
use AnimeTadka\Api\Controllers\CategoryController;
use AnimeTadka\Api\Controllers\EpisodeController;
use AnimeTadka\Api\Controllers\HomeController;
use AnimeTadka\Api\Controllers\MetaController;
use AnimeTadka\Api\Controllers\ProxyController;
use AnimeTadka\Api\Controllers\ScheduleController;
use AnimeTadka\Api\Controllers\SearchController;
use AnimeTadka\Api\Controllers\ToonStreamController;
use AnimeTadka\Api\Controllers\WatchHentaiController;
use AnimeTadka\Api\Router;
use AnimeTadka\Cache\FileCache;
use AnimeTadka\Scraper\AnimeTadkaScraper;
use AnimeTadka\Scraper\HttpClient;

/**
 * Application bootstrap: wires config, logging, cache, HTTP client, scraper
 * and the router. Shared by the web front controller and CLI tools.
 */
final class Bootstrap
{
    public static function config(): Config
    {
        $root = dirname(__DIR__);

        $config = Config::fromEnv($root . '/.env', [
            'BASE_URL' => 'https://animetadka.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'HTTP_TIMEOUT' => 15,
            'HTTP_CONNECT_TIMEOUT' => 7,
            'HTTP_MAX_RETRIES' => 1,
            'HTTP_RETRY_BACKOFF_MS' => 600,
            'REQUEST_DELAY_MS' => 0,
            'HTTP_PROXY' => '',
            'HTTP_IPRESOLVE_V4' => true,
            'CACHE_ENABLED' => true,
            'CACHE_TTL' => 7200,
            'CACHE_DIR' => 'storage/cache',
            'HTML_DIR' => 'storage/html',
            'LOG_DIR' => 'storage/logs',
            'LOG_LEVEL' => 'info',
            'API_DEBUG' => false,
        ]);

        // Resolve storage dirs against the API root so they stay correct no
        // matter what the current working directory is. The API is mounted
        // inside the animetadka site, whose front controller sets its own CWD.
        $values = $config->all();
        foreach (['CACHE_DIR', 'HTML_DIR', 'LOG_DIR'] as $key) {
            $value = (string) ($values[$key] ?? '');
            if ($value !== '' && !str_starts_with($value, '/')) {
                $values[$key] = $root . '/' . $value;
            }
        }

        return new Config($values);
    }

    public static function logger(Config $config): Logger
    {
        $logDir = $config->string('LOG_DIR', 'storage/logs');
        return new Logger($logDir . '/app.log', $config->string('LOG_LEVEL', 'info'));
    }

    public static function httpClient(Config $config, ?Logger $logger = null): HttpClient
    {
        // HTTP-level cache disabled — parsed JSON caching lives in AnimeTadkaScraper.
        return new HttpClient($config, $logger);
    }

    public static function scraper(Config $config, ?HttpClient $http = null): AnimeTadkaScraper
    {
        $http ??= self::httpClient($config, self::logger($config));
        $cache = new FileCache($config->string('CACHE_DIR', 'storage/cache'), $config->int('CACHE_TTL', 7200));
        return new AnimeTadkaScraper($http, $config, $cache);
    }

    public static function router(Config $config, ?AnimeTadkaScraper $scraper = null): Router
    {
        $scraper ??= self::scraper($config);
        $logger = self::logger($config);

        $home = new HomeController($scraper, $logger);
        $search = new SearchController($scraper, $logger);
        $anime = new AnimeController($scraper, $logger);
        $episode = new EpisodeController($scraper, $logger);
        $category = new CategoryController($scraper, $logger);
        $meta = new MetaController($scraper, $logger);
        $proxy = new ProxyController($scraper, $logger);
        $schedule = new ScheduleController($scraper, $logger);

        $anikoto = new AnikotoController();

        $health = static fn () => \AnimeTadka\Api\Response::success([
            'status' => 'ok',
            'time' => date(DATE_ATOM),
        ]);

        $router = new Router();

        $router->get('/api/health', $health);
        $router->get('/api/animetadka/health', $health);

        $router->get('/api/home', [$home, 'home']);
        $router->get('/api/latest', [$home, 'latest']);
        $router->get('/api/all', [$home, 'all']);
        $router->get('/api/schedule', [$schedule, 'show']);
        $router->get('/api/top', [$home, 'top']);

        $router->get('/api/search', [$search, 'search']);

        $router->get('/api/anime/{slug}', [$anime, 'show']);
        $router->get('/api/anime/{slug}/episodes', [$anime, 'episodes']);

        $router->get('/api/episode/{hash}', [$episode, 'show']);
        $router->get('/api/movie/{hash}', [$episode, 'movie']);

        $router->get('/api/category/{type}/{slug}', [$category, 'show']);
        $router->get('/api/letter/{letter}', [$category, 'letter']);

        $router->get('/api/languages', [$meta, 'languages']);
        $router->get('/api/genres', [$meta, 'genres']);
        $router->get('/api/studios', [$meta, 'studios']);
        $router->get('/api/extra', [$meta, 'extra']);

        $router->get('/api/proxy/watch', [$proxy, 'watch']);
        $router->get('/api/proxy/download', [$proxy, 'download']);

        // --- Animetadka aliases under /api/animetadka/... ---
        $router->get('/api/animetadka/home', [$home, 'home']);
        $router->get('/api/animetadka/latest', [$home, 'latest']);
        $router->get('/api/animetadka/all', [$home, 'all']);
        $router->get('/api/animetadka/schedule', [$schedule, 'show']);
        $router->get('/api/animetadka/top', [$home, 'top']);
        $router->get('/api/animetadka/search', [$search, 'search']);
        $router->get('/api/animetadka/anime/{slug}', [$anime, 'show']);
        $router->get('/api/animetadka/anime/{slug}/episodes', [$anime, 'episodes']);
        $router->get('/api/animetadka/episode/{hash}', [$episode, 'show']);
        $router->get('/api/animetadka/movie/{hash}', [$episode, 'movie']);
        $router->get('/api/animetadka/category/{type}/{slug}', [$category, 'show']);
        $router->get('/api/animetadka/letter/{letter}', [$category, 'letter']);
        $router->get('/api/animetadka/languages', [$meta, 'languages']);
        $router->get('/api/animetadka/genres', [$meta, 'genres']);
        $router->get('/api/animetadka/studios', [$meta, 'studios']);
        $router->get('/api/animetadka/extra', [$meta, 'extra']);
        $router->get('/api/animetadka/proxy/watch', [$proxy, 'watch']);
        $router->get('/api/animetadka/proxy/download', [$proxy, 'download']);

        // --- Anikoto routes under /api/anikoto/... ---
        $router->get('/api/anikoto/cache/stats', [$anikoto, 'cacheStats']);
        $router->get('/api/anikoto/home', [$anikoto, 'home']);
        $router->get('/api/anikoto/index', [$anikoto, 'index']);
        $router->get('/api/anikoto/nav', [$anikoto, 'nav']);
        $router->get('/api/anikoto/search', [$anikoto, 'search']);
        $router->get('/api/anikoto/browse', [$anikoto, 'browse']);
        $router->get('/api/anikoto/azlist/{sort}', [$anikoto, 'azlist']);
        $router->get('/api/anikoto/azlist', [$anikoto, 'azlist']);
        $router->get('/api/anikoto/genre/{name}', [$anikoto, 'genre']);
        $router->get('/api/anikoto/category/{name}', [$anikoto, 'category']);
        $router->get('/api/anikoto/type/{name}', [$anikoto, 'type']);
        $router->get('/api/anikoto/anime/{id}/episodes', [$anikoto, 'episodes']);
        $router->get('/api/anikoto/anime/{id}/ep/{num}', [$anikoto, 'episode']);
        $router->get('/api/anikoto/anime/{id}', [$anikoto, 'anime']);

        $toonstream = new ToonStreamController();

        $router->get('/api/toonstream', [$toonstream, 'info']);
        $router->get('/api/toonstream/health', [$toonstream, 'health']);
        $router->get('/api/toonstream/{route:.+}', [$toonstream, 'route']);

        $animedekho = new AnimeDekhoController();

        $router->get('/api/animedekho', [$animedekho, 'info']);
        $router->get('/api/animedekho/health', [$animedekho, 'health']);
        $router->get('/api/animedekho/{route:.+}', [$animedekho, 'route']);

        $watchhentai = new WatchHentaiController();

        $router->get('/api/watchhentai', [$watchhentai, 'info']);
        $router->get('/api/watchhentai/health', [$watchhentai, 'health']);
        $router->get('/api/watchhentai/{route:.+}', [$watchhentai, 'route']);

        return $router;
    }
}