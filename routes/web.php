<?php
/**
 * Web routes — ordered from most specific to least specific.
 * The catch-all /{slug} MUST be last to avoid shadowing other routes.
 */

$router = new Router();

// Pre-load all controllers once
$controllers = [
    'LandingController' => ROOT_PATH . '/app/Controllers/LandingController.php',
    'HomeController' => ROOT_PATH . '/app/Controllers/HomeController.php',
    'ScheduleController' => ROOT_PATH . '/app/Controllers/ScheduleController.php',
    'SearchController' => ROOT_PATH . '/app/Controllers/SearchController.php',
    'AllController' => ROOT_PATH . '/app/Controllers/AllController.php',
    'HistoryController' => ROOT_PATH . '/app/Controllers/HistoryController.php',
    'SkipAdsController' => ROOT_PATH . '/app/Controllers/SkipAdsController.php',
    'PageController' => ROOT_PATH . '/app/Controllers/PageController.php',
    'EpisodeController' => ROOT_PATH . '/app/Controllers/EpisodeController.php',
    'HentaiController' => ROOT_PATH . '/app/Controllers/HentaiController.php',
    'PlayerController' => ROOT_PATH . '/app/Controllers/PlayerController.php',
    'CatalogController' => ROOT_PATH . '/app/Controllers/CatalogController.php',
    'SubtitleController' => ROOT_PATH . '/app/Controllers/SubtitleController.php',
    'LetterController' => ROOT_PATH . '/app/Controllers/LetterController.php',
    'LanguageController' => ROOT_PATH . '/app/Controllers/LanguageController.php',
    'GenreController' => ROOT_PATH . '/app/Controllers/GenreController.php',
    'StudioController' => ROOT_PATH . '/app/Controllers/StudioController.php',
    'TypeController' => ROOT_PATH . '/app/Controllers/TypeController.php',
    'AnimeController' => ROOT_PATH . '/app/Controllers/AnimeController.php',
];
foreach ($controllers as $file) {
    require_once $file;
}

// === Exact paths ===
$router->get('/', [LandingController::class, 'index']);

$router->get('/home', [HomeController::class, 'index']);

$router->get('/schedule', [ScheduleController::class, 'index']);

$router->get('/search', [SearchController::class, 'index']);

$router->get('/all', [AllController::class, 'index']);

$router->get('/history', [HistoryController::class, 'index']);

$router->get('/skip-ads', [SkipAdsController::class, 'index']);

// === Diagnostics (hosting triage: versions, extensions, outbound fetch) ===
$router->get('/diag', function () {
    header('Content-Type: application/json');
    try {
        $ext = [];
        foreach (['curl', 'dom', 'mbstring', 'openssl', 'json', 'libxml'] as $e) {
            $ext[$e] = extension_loaded($e);
        }
        $out = [
            'build' => '2026-10-08-r12',
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'extensions' => $ext,
            'curl_multi' => function_exists('curl_multi_init'),
            'set_time_limit_ok' => function_exists('set_time_limit'),
            'cache_writable' => is_writable(ROOT_PATH . '/api/storage/cache'),
            'provider' => class_exists('Config') ? Config::get('provider', '?') : '?',
        ];
        // Outbound fetch to the source (the #1 production failure point).
        if (!function_exists('curl_init')) {
            $out['fetch'] = ['errno' => -1, 'error' => 'curl extension missing', 'http' => 0, 'bytes' => 0, 'ms' => 0];
        } else {
            $t = microtime(true);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://watchhentai.net/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body = curl_exec($ch);
            $out['fetch'] = [
                'errno' => curl_errno($ch),
                'error' => curl_error($ch) ?: null,
                'http' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'bytes' => is_string($body) ? strlen($body) : 0,
                'ms' => (int) round((microtime(true) - $t) * 1000),
            ];
            curl_close($ch);
        }
    } catch (\Throwable $e) {
        $out = ['build' => '2026-10-08-r12', 'diag_error' => substr($e->getMessage(), 0, 200)];
    }
    echo json_encode($out);
});

// === Static pages ===
$router->get('/contact-us', function() { (new PageController())->show('contact-us'); });
$router->get('/privacy-policy', function() { (new PageController())->show('privacy-policy'); });
$router->get('/dmca', function() { (new PageController())->show('dmca'); });
$router->get('/credits', function() { (new PageController())->show('credits'); });

// === Hentai section (watchhentai provider, /hentai/ link base) ===
$router->get('/hentai', [HentaiController::class, 'home']);
$router->get('/hentai/home', [HentaiController::class, 'home']);
$router->get('/watchhentai/home', [HentaiController::class, 'home']);
$router->get('/hentai/all', [HentaiController::class, 'all']);
$router->get('/hentai/all/page/{page}', [HentaiController::class, 'all']);
$router->get('/hentai/ep/{id:.+}', [HentaiController::class, 'episode']);
$router->get('/hentai/letter/{letter}/page/{page}', [HentaiController::class, 'letter']);
$router->get('/hentai/letter/{letter}', [HentaiController::class, 'letter']);
$router->get('/hentai/year/{year}/page/{page}', [HentaiController::class, 'year']);
$router->get('/hentai/year/{year}', [HentaiController::class, 'year']);
$router->get('/hentai/catalog/{type}/page/{page}', [HentaiController::class, 'catalog']);
$router->get('/hentai/catalog/{type}', [HentaiController::class, 'catalog']);
$router->get('/hentai/catalog/genre/{genre}/page/{page}', [HentaiController::class, 'genre']);
$router->get('/hentai/catalog/genre/{genre}', [HentaiController::class, 'genre']);
$router->get('/hentai/{slug}', [HentaiController::class, 'series']);

// /watchhentai/* is an alias: permanent-redirect everything to the
// canonical /hentai/* section (path + query preserved).
$whRedirect = function (string $to, ...$args) {
    $q = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $to . $q, true, 301);
    exit;
};
$router->get('/watchhentai', function () use ($whRedirect) { $whRedirect('/hentai/'); });
$router->get('/watchhentai/home', function () use ($whRedirect) { $whRedirect('/hentai/'); });
$router->get('/watchhentai/all/page/{page}', function ($page) use ($whRedirect) { $whRedirect('/hentai/all/page/' . $page); });
$router->get('/watchhentai/all', function () use ($whRedirect) { $whRedirect('/hentai/all'); });
$router->get('/watchhentai/catalog/genre/{genre}/page/{page}', function ($genre, $page) use ($whRedirect) { $whRedirect('/hentai/catalog/genre/' . $genre . '/page/' . $page); });
$router->get('/watchhentai/catalog/genre/{genre}', function ($genre) use ($whRedirect) { $whRedirect('/hentai/catalog/genre/' . $genre); });
$router->get('/watchhentai/catalog/{type}/page/{page}', function ($type, $page) use ($whRedirect) { $whRedirect('/hentai/catalog/' . $type . '/page/' . $page); });
$router->get('/watchhentai/catalog/{type}', function ($type) use ($whRedirect) { $whRedirect('/hentai/catalog/' . $type); });
$router->get('/watchhentai/ep/{id:.+}', function ($id) use ($whRedirect) { $whRedirect('/hentai/ep/' . $id); });
$router->get('/watchhentai/{slug}', function ($slug) use ($whRedirect) { $whRedirect('/hentai/' . $slug); });

// === Multi-segment routes (must come before single-segment catch-alls) ===
// Lazy download resolver — must precede the /ep/{id} catch-all below.
$router->get('/ep/downloads', [EpisodeController::class, 'downloads']);

$router->get('/ep/{id:.+}', [EpisodeController::class, 'show']);

// Custom player (watchhentai): player page + mp4 proxy — before /{slug}
$router->get('/player/proxy', [PlayerController::class, 'proxy']);
// Netflix skin sheet for the player page — the file lives outside public/,
// so serve it here (used only by player-wh.php, toggled per skin choice).
$router->get('/player/style.css', function () {
    $file = ROOT_PATH . '/player/style.css';
    if (!is_file($file)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: text/css; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    readfile($file);
});
$router->get('/player/watchhentai/{slug:.+}', [PlayerController::class, 'watchhentai']);

$router->get('/catalog/genre/{genre}/page/{page}', [CatalogController::class, 'genre']);
$router->get('/catalog/genre/{genre}', [CatalogController::class, 'genre']);

$router->get('/catalog/season/{season}/page/{page}', [CatalogController::class, 'season']);
$router->get('/catalog/season/{season}', [CatalogController::class, 'season']);

$router->get('/catalog/{type}/page/{page}', [CatalogController::class, 'index']);

$router->get('/catalog/{type}', [CatalogController::class, 'index']);

$router->get('/sub/{subtitle}/page/{page}', [SubtitleController::class, 'index']);

$router->get('/sub/{subtitle}', [SubtitleController::class, 'index']);

$router->get('/letter/{letter}/page/{page}', [LetterController::class, 'index']);

$router->get('/all/page/{page}', [AllController::class, 'index']);

// === Single-segment prefixed routes (must come before /{slug}) ===
$router->get('/lang/{language}', [LanguageController::class, 'index']);

$router->get('/genre/{genre}', [GenreController::class, 'index']);

$router->get('/studio/{studio}', [StudioController::class, 'index']);

$router->get('/type/{type}', [TypeController::class, 'index']);

$router->get('/letter/{letter}', [LetterController::class, 'index']);

// === Catch-all anime detail (MUST be last) ===
$router->get('/{slug}', [AnimeController::class, 'show']);

// === API routes ===
$router->get('/api/menu/refresh', function() {
    require_once ROOT_PATH . '/app/Helpers/ApiClient.php';
    require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
    $provider = $_GET['provider'] ?? '';
    $result = api_menu_refresh($provider);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'menu' => $result]);
});

// === Live search ajax ===
$router->post('/wp-admin/admin-ajax.php', function() {
    $action = $_POST['action'] ?? '';
    if ($action !== 'anime_search') {
        echo '0';
        return;
    }

    $term = trim((string)($_POST['term'] ?? ''));
    if ($term === '') {
        echo '0';
        return;
    }

    $cacheDir = ROOT_PATH . '/api/storage/cache';
    // Section-aware search: hentai pages search the watchhentai backend.
    // The shared overlay JS posts no section info, so detect it from the
    // referring page. Cache is keyed per provider to avoid cross-talk.
    $refPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '';
    if (str_starts_with($refPath, '/hentai') || str_starts_with($refPath, '/watchhentai')) {
        Config::set('provider', 'watchhentai');
        $GLOBALS['__link_base'] = '/hentai';
        api_provider_reset();
    }
    $provider = Config::get('provider', 'animetadka');
    $cacheKey = 'search_' . md5($provider . '|' . strtolower($term));
    $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 60) {
        echo file_get_contents($cacheFile);
        return;
    }

    $provider = api_provider();

    if ($provider === 'anikoto') {
        require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
        pa_load_anikoto();
        try {
            $data = anikotoSearch($term);
            $results = pa_anikoto_search($data);
            echo search_render_cards($results, 'Serie');
        } catch (\Throwable $e) {
            echo search_no_results();
        }
        return;
    }

    if ($provider === 'toonstream') {
        require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
        pa_load_toonstream();
        try {
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            $data = $ts->search($term);
            $results = pa_toonstream_search($data);
            echo search_render_cards($results, 'Dub');
        } catch (\Throwable $e) {
            echo search_no_results();
        }
        return;
    }

    if ($provider === 'animedekho') {
        require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
        pa_load_animedekho();
        try {
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            $data = $ad->search($term);
            // animedekho search returns same card shape as toonstream
            $results = [];
            foreach ($data['results'] ?? $data['items'] ?? [] as $it) {
                $results[] = ['slug' => $it['slug'] ?? pa_slug_from_url($it['url'] ?? ''), 'title' => $it['title'] ?? '', 'image' => $it['image'] ?? '/assets/images/placeholder.jpg', 'audio' => $it['audio'] ?? '', 'episode' => $it['episode'] ?? '', 'type' => $it['contentType'] ?? ($it['type'] ?? '')];
            }
            echo search_render_cards($results, 'Dub');
        } catch (\Throwable $e) {
            echo search_no_results();
        }
        return;
    }

    if ($provider === 'watchhentai') {
        require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
        pa_load_watchhentai();
        try {
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $data = $wh->search($term);
            $results = pa_watchhentai_search($data);
            echo search_render_cards($results, 'Sub');
        } catch (\Throwable $e) {
            echo search_no_results();
        }
        return;
    }

    $fragment = api_try(function () use ($term) {
        require_once ROOT_PATH . '/api/src/autoload.php';
        $config = \AnimeTadka\Bootstrap::config();
        $http = \AnimeTadka\Bootstrap::httpClient($config);
        return $http->post('https://animetadka.com/wp-admin/admin-ajax.php', [
            'action' => 'anime_search',
            'term' => $term,
        ]);
    }, '');
    if ($fragment === '' || trim($fragment) === '' || trim($fragment) === '0') {
        echo search_no_results();
        return;
    }
    $fragment = preg_replace_callback('#href="https://animetadka\.com/([^"]+)"#', function ($m) {
        $base = $GLOBALS['__link_base'] ?? '';
        return 'href="' . $base . '/' . $m[1] . '"';
    }, $fragment);

    if (is_dir($cacheDir)) {
        file_put_contents($cacheFile, $fragment, LOCK_EX);
    }

    echo $fragment;
});

function search_render_cards(array $results, string $typeText): string {
    $cards = [];
    foreach ($results as $r) {
        $slug = htmlspecialchars($r['slug'] ?? '', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $image = htmlspecialchars($r['image'] ?? '', ENT_QUOTES, 'UTF-8');
        $audio = htmlspecialchars($r['audio'] ?? '', ENT_QUOTES, 'UTF-8');
        $hasAudio = $audio !== '';
        $hasType = $typeText !== '' && !empty($r['episode']);
        $typeLabel = $hasType ? $typeText : '';
        $cards[] = '<a href="' . htmlspecialchars(View::typeHref('/', $r['type'] ?? '', $r['slug'] ?? ''), ENT_QUOTES, 'UTF-8') . '" class="search-res-item" onclick="saveSearchHistory(\'' . addslashes($r['title'] ?? '') . '\')">' .
            '<div class="search-res-img"><img width="150" height="150" src="' . $image . '" alt="" loading="lazy"></div>' .
            '<div class="search-res-info">' .
            '<span class="res-title">' . $title . '</span>' .
            '<div class="res-subline">' .
            ($hasAudio ? '<div class="sub-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M5 8l7-7 7 7M5 16l7 7 7-7"></path></svg><span>' . $audio . '</span></div>' : '') .
            ($hasType ? '<div class="sub-item res-type-highlight"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M17 3v18"/><path d="M3 7h4"/><path d="M3 12h18"/><path d="M3 17h4"/><path d="M17 12h4"/><path d="M17 17h4"/><path d="M17 7h4"/></svg><span class="type-text">' . $typeLabel . '</span></div>' : '') .
            '</div></div></a>';
    }
    return $cards ? implode('', $cards) : search_no_results();
}

function search_no_results(): string {
    return '<div class="no-search-res"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg><p>No results found.</p></div>';
}
