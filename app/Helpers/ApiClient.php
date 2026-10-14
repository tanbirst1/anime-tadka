<?php
/**
 * ApiClient — in-process client for the bundled AnimeTadka scraper/API.
 *
 * Supports multiple content providers (animetadka, anikoto, toonstream, animedekho, watchhentai)
 * via Config::get('provider').  Each provider's raw data is normalised
 * to the same view contract by ProviderAdapters.php.
 *
 * The API subproject (api/) is required and driven directly (no HTTP
 * round-trip, shared file cache). Every accessor returns view-ready data
 * and degrades to empty/null on failure so the site never 500s when the
 * source site is unreachable.
 *
 * API docs: api/docs/api.md
 */

// ── Provider detection (config.php is single source of truth) ─────

function api_provider(): string {
    if (isset($GLOBALS['__api_provider_cached'])) {
        return $GLOBALS['__api_provider_cached'];
    }
    $p = strtolower(trim((string) Config::get('provider', 'animetadka')));
    $GLOBALS['__api_provider_cached'] = in_array($p, ['animetadka', 'anikoto', 'toonstream', 'animedekho', 'watchhentai'], true) ? $p : 'animetadka';
    return $GLOBALS['__api_provider_cached'];
}
function api_provider_reset(): void {
    unset($GLOBALS['__api_provider_cached']);
}

function api_is_animetadka(): bool {
    return api_provider() === 'animetadka';
}

// ── Animetadka scraper (in-process, zero-cost) ─────────────────────

function api_scraper() {
    static $scraper = null;
    if ($scraper === null) {
        require_once ROOT_PATH . '/api/src/autoload.php';
        $config = \AnimeTadka\Bootstrap::config();
        $scraper = \AnimeTadka\Bootstrap::scraper($config);
    }
    return $scraper;
}

function api_try(callable $fn, $default = null) {
    try {
        return $fn();
    } catch (\Throwable $e) {
        return $default;
    }
}

// ── URL/slug helpers (hot path, minimized work) ─────────────────────

function api_slug_from_url($url): string {
    $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
    $pos = strrpos($path, '/');
    return $pos === false ? $path : substr($path, $pos + 1);
}

function api_display_names($value) {
    if (is_array($value)) {
        $names = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $name = $item['name'] ?? '';
            } elseif (is_scalar($item)) {
                $name = $item;
            } else {
                continue;
            }
            if ($name !== '') {
                $names[] = (string) $name;
            }
        }
        return implode(', ', $names);
    }
    return is_scalar($value) ? (string) $value : '';
}


function api_proxy_image(string $url): string {
    if ($url === '' || $url === '/assets/images/placeholder.jpg' || str_starts_with($url, '/assets/') || str_starts_with($url, 'data:')) return $url;
    if (str_starts_with($url, 'https://serveproxy.com/')) {
        // Ensure inner url has https://
        $inner = rawurldecode(substr($url, strlen('https://serveproxy.com/?url=')));
        if (!preg_match('#^https?://#i', $inner)) $inner = 'https://' . ltrim($inner, '/');
        $fixed = 'https://serveproxy.com/?url=' . rawurlencode($inner);
        return $fixed;
    }
    if (str_contains($url, 'wsrv.nl/?url=')) { $parts=explode('url=', $url, 2); $inner=rawurldecode(end($parts)); if (!preg_match('#^https?://#i', $inner)) $inner='https://'.ltrim($inner,'/'); return 'https://serveproxy.com/?url='.rawurlencode($inner); }
    // Only proxy animetadka provider images (other providers already use CDN)
    if (api_provider() !== 'animetadka') return $url;
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
    return 'https://serveproxy.com/?url=' . rawurlencode($url);
}

function api_episode_label_parts(string $label): array {
    if (preg_match('/S\d+-E(\d+):?\s*(.*)/i', $label, $m)) {
        return ['number' => $m[1], 'title' => $m[2] !== '' ? $m[2] : $label];
    }
    // Flat "1 Title" labels (toonstream/watchhentai season rows): split a
    // leading episode number so rows render "Ep 1: Title" instead of "Ep : …".
    if (preg_match('/^(\d+)\s+(\S.*)$/u', $label, $m)) {
        return ['number' => $m[1], 'title' => $m[2]];
    }
    return ['number' => '', 'title' => $label];
}

/**
 * Simple file cache for API responses with per-request memory.
 * Cache key = md5(provider + type + slug + page)
 * TTL = 300s (5 min) for catalog data.
 */
function api_cache_get(string $key, int $ttl = 300) {
    $now = time();
    if (isset($GLOBALS['__api_cache_mem'][$key])) {
        $entry = $GLOBALS['__api_cache_mem'][$key];
        if ($entry['expires'] === 0 || $entry['expires'] > $now) {
            return $entry['data'];
        }
        unset($GLOBALS['__api_cache_mem'][$key]);
    }
    $dir = ROOT_PATH . '/api/storage/cache';
    if (!is_dir($dir)) return null;
    $file = $dir . '/' . $key . '.json';
    if (!is_file($file)) return null;
    if ((time() - (int)@filemtime($file)) > $ttl) {
        @unlink($file);
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) return null;
    $GLOBALS['__api_cache_mem'][$key] = ['data' => $data, 'expires' => $ttl > 0 ? $now + $ttl : 0];
    if (count($GLOBALS['__api_cache_mem']) > 200) array_shift($GLOBALS['__api_cache_mem']);
    return $data;
}

function api_cache_get_stale(string $key) {
    $dir = ROOT_PATH . '/api/storage/cache';
    $file = $dir . '/' . $key . '.json';
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

function api_cache_set(string $key, $data, int $ttl = 300): void {
    $GLOBALS['__api_cache_mem'][$key] = ['data' => $data, 'expires' => $ttl > 0 ? time() + $ttl : 0];
    if (count($GLOBALS['__api_cache_mem']) > 200) array_shift($GLOBALS['__api_cache_mem']);

    $dir = ROOT_PATH . '/api/storage/cache';
    if (!is_dir($dir)) return;
    $file = $dir . '/' . $key . '.json';
    $json = json_encode($data);
    $tmp = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json) !== false) {
        @rename($tmp, $file);
        @unlink($tmp);
    } else {
        @file_put_contents($file, $json);
    }
}

function api_cache_key(string $provider, string $type, string $slug, int $page): string {
    return 'cat_' . md5($provider . '|' . $type . '|' . $slug . '|' . $page);
}

/**
 * Return sidebar menu data for the active provider.
 * Used by the main layout to render dynamic sidebars for non-animetadka providers.
 *
 * Structure:
 *   links   => [{name, slug}]  — top-level nav (Home, Updated, Popular, etc.)
 *   types   => [{name, slug}]  — Movie, TV, OVA, etc.
 *   genres  => [{name, slug}]  — Action, Comedy, etc.
 *   statuses => [{name, slug}] — Ongoing, Completed, etc.
 *   studios => [{name, slug}]  — studio links
 *   subtypes => [{name, slug}] — sub/dub type filters
 *
 * @return array{links: array, types: array, genres: array, statuses: array, studios: array, subtypes: array}
 */
function api_menu_data(): array {
    $provider = api_provider();

    $menuCacheKey = 'menu_' . md5($provider);
    $cached = api_cache_get($menuCacheKey, 3600);
    if (is_array($cached)) {
        return $cached;
    }

    if ($provider === 'anikoto') {
        $result = api_try(function () {
            pa_load_anikoto();
            $nav = anikotoGetNavMenu();
            $menu = $nav['menu'] ?? [];

            $links = [];
            foreach ($menu['links'] ?? [] as $l) {
                $url = $l['url'] ?? '';
                $name = $l['name'] ?? '';
                if (!$name || !$url) continue;
                $slug = pa_slug_from_url($url);
                $path = ltrim(parse_url($url, PHP_URL_PATH), '/');
                if (in_array($slug, ['watch2gether', 'community', 'random', 'board'], true)) continue;
                $localPath = $path;
                if (preg_match('#^api/anikoto/category/status/(.+)$#', $path, $m)) {
                    $localPath = '/catalog/season/' . $m[1];
                } elseif (preg_match('#^api/anikoto/category/(.+)$#', $path, $m)) {
                    $localPath = '/catalog/' . $m[1];
                } elseif (preg_match('#^api/anikoto/home$#', $path)) {
                    $localPath = '/catalog/home';
                }
                $links[] = ['name' => $name, 'slug' => $slug, 'path' => $localPath];
            }

            $types = [];
            foreach ($menu['types'] ?? [] as $t) {
                $name = $t['name'] ?? '';
                if (!$name) continue;
                $slug = pa_slug_from_url($t['url'] ?? '');
                $types[] = ['name' => $name, 'slug' => $slug];
            }

            $genres = [];
            foreach ($menu['genres'] ?? [] as $g) {
                $name = $g['name'] ?? '';
                if (!$name) continue;
                $slug = pa_slug_from_url($g['url'] ?? '');
                $genres[] = ['name' => $name, 'slug' => $slug];
            }

            return [
                'links'    => $links,
                'types'    => $types,
                'genres'   => $genres,
                'statuses' => [],
                'studios'  => [],
                'subtypes' => [],
            ];
        }, null);
        // If fetch failed or returned empty genres/links, don't cache empty long
        if (!is_array($result) || (empty($result['genres']) && empty($result['links']))) {
            // Try stale cache
            $stale = api_cache_get($menuCacheKey, 86400);
            if (is_array($stale) && !empty($stale['genres'])) return $stale;
            // Return empty but cache only short
            $fallback = $result ?? ['links' => [], 'types' => [], 'genres' => [], 'statuses' => [], 'studios' => [], 'subtypes' => []];
            api_cache_set($menuCacheKey, $fallback, 60);
            return $fallback;
        }

        api_cache_set($menuCacheKey, $result, 3600);
        return $result;
    }

    if ($provider === 'toonstream') {
        $genres = [
            ['name' => 'Action', 'slug' => 'action'],
            ['name' => 'Adventure', 'slug' => 'adventure'],
            ['name' => 'Comedy', 'slug' => 'comedy'],
            ['name' => 'Drama', 'slug' => 'drama'],
            ['name' => 'Fantasy', 'slug' => 'fantasy'],
            ['name' => 'Horror', 'slug' => 'horror'],
            ['name' => 'Mystery', 'slug' => 'mystery'],
            ['name' => 'Romance', 'slug' => 'romance'],
            ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
            ['name' => 'Slice of Life', 'slug' => 'slice-of-life'],
            ['name' => 'Supernatural', 'slug' => 'supernatural'],
            ['name' => 'Sports', 'slug' => 'sports'],
        ];
        $result = [
            'links' => [
                ['name' => 'Home', 'slug' => 'home', 'path' => '/catalog/home'],
                ['name' => 'Updated', 'slug' => 'updated', 'path' => '/catalog/latest-updated'],
                ['name' => 'Popular', 'slug' => 'popular', 'path' => '/catalog/most-viewed'],
                ['name' => 'Movies', 'slug' => 'movies', 'path' => '/catalog/movie'],
            ],
            'types' => [],
            'genres' => $genres,
            'statuses' => [
                ['name' => 'Ongoing', 'slug' => 'currently-airing'],
                ['name' => 'Completed', 'slug' => 'finished-airing'],
                ['name' => 'Upcoming', 'slug' => 'not-yet-aired'],
            ],
            'studios'  => [],
            'subtypes' => [],
        ];
        api_cache_set($menuCacheKey, $result, 3600);
        return $result;
    }

    if ($provider === 'animedekho') {
        $genres = [
            ['name' => 'Action', 'slug' => 'action'],
            ['name' => 'Adventure', 'slug' => 'adventure'],
            ['name' => 'Comedy', 'slug' => 'comedy'],
            ['name' => 'Drama', 'slug' => 'drama'],
            ['name' => 'Fantasy', 'slug' => 'fantasy'],
            ['name' => 'Horror', 'slug' => 'horror'],
            ['name' => 'Mystery', 'slug' => 'mystery'],
            ['name' => 'Romance', 'slug' => 'romance'],
            ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
            ['name' => 'Slice of Life', 'slug' => 'slice-of-life'],
            ['name' => 'Supernatural', 'slug' => 'supernatural'],
            ['name' => 'Sports', 'slug' => 'sports'],
        ];
        $result = [
            'links' => [
                ['name' => 'Home', 'slug' => 'home', 'path' => '/catalog/home'],
            ],
            'types' => [
                ['name' => 'Series', 'slug' => 'series'],
                ['name' => 'Movie', 'slug' => 'movie'],
            ],
            'genres' => $genres,
            'statuses' => [],
            'studios'  => [],
            'subtypes' => [],
        ];
        api_cache_set($menuCacheKey, $result, 3600);
        return $result;
    }

    if ($provider === 'watchhentai') {
        // Genres come from the live site (homepage taxonomy) so new tags
        // appear automatically; years are the /release/ archive range.
        // Statuses/types have no source equivalent, so they stay empty to
        // avoid dead sidebar links. Category links resolve to /videos/
        // listings (see api_category_data mapping below).
        $genres = api_try(function () {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $extra = $wh->extra();
            $out = [];
            $seen = [];
            foreach (($extra['genres'] ?? []) as $g) {
                $name = trim((string) ($g['name'] ?? ''));
                $slug = pa_slug_from_url($g['url'] ?? '');
                if ($name === '' || $slug === '' || isset($seen[$slug])) continue;
                if (strtolower($name) === 'see all') continue;
                $seen[$slug] = true;
                $out[] = ['name' => ucwords($name), 'slug' => $slug];
            }
            // Known tags missing from the homepage taxonomy (verified live).
            foreach ([['name' => 'Comedy', 'slug' => 'comedy'], ['name' => 'Censored', 'slug' => 'censored']] as $fallback) {
                if (!isset($seen[$fallback['slug']])) {
                    $out[] = $fallback;
                }
            }
            usort($out, static fn ($a, $b) => strcmp($a['name'], $b['name']));
            return $out;
        }, []);
        if ($genres === []) {
            $genres = [
                ['name' => 'Censored', 'slug' => 'censored'],
                ['name' => 'Uncensored', 'slug' => 'uncensored'],
                ['name' => 'Comedy', 'slug' => 'comedy'],
            ];
        }
        $years = [];
        for ($y = (int) date('Y'); $y >= 2010; $y--) {
            $years[] = ['name' => (string) $y, 'slug' => (string) $y];
        }
        $result = [
            'links' => [
                // 'raw' → rendered unprefixed (main-site /home, not /hentai/home).
                ['name' => 'Home', 'slug' => 'home', 'path' => '/home'],
                ['name' => 'Updated', 'slug' => 'updated', 'path' => '/catalog/latest-updated'],
                ['name' => 'Popular', 'slug' => 'popular', 'path' => '/catalog/most-viewed'],
            ],
            'types' => [],
            'genres' => $genres,
            'years' => $years,
            'statuses' => [],
            'studios'  => [],
            'subtypes' => [],
        ];
        api_cache_set($menuCacheKey, $result, 3600);
        return $result;
    }

    $result = ['links' => [], 'types' => [], 'genres' => [], 'statuses' => [], 'studios' => [], 'subtypes' => []];
    api_cache_set($menuCacheKey, $result, 3600);
    return $result;
}

/**
 * Refresh and return sidebar menu data for a specific provider.
 * Used by the /api/menu/refresh admin endpoint.
 */
function api_menu_refresh(string $provider = ''): array {
    if ($provider !== '') {
        Config::set('provider', $provider);
        api_provider_reset();
    }
    return api_menu_data();
}

/**
 * Map an API grid card (latest/category item) to the view card contract:
 * title, slug, episode, audio, image.
 */
function api_card_view(array $item) {
    $slug = api_slug_from_url($item['url'] ?? '');
    $type = '';
    $url = strtolower($item['url'] ?? '');
    if (str_contains($url, '/movie') || str_contains(strtolower($slug), 'movie')) $type = 'movie';
    elseif (str_contains($url, '/episode') || str_contains($url, '/ep/')) $type = 'episode';
    else $type = 'series';
    return [
        'title' => $item['title'] ?? '',
        'slug' => $slug . ($type ? '?type=' . $type : ''),
        'episode' => $item['episode_badge'] ?? '',
        'audio' => $item['languages_badge'] ?? '',
        'image' => api_proxy_image($item['poster'] ?? '/assets/images/placeholder.jpg'),
        'href' => '/' . $slug . ($type ? '?type=' . $type : ''),
        'type' => $type,
    ];
}

/**
 * Home page data: spotlight carousel, latest grids (all/series/movies),
 * top-10 (day/week/month).
 */
function api_home_data() {
    $empty = [
        'spotlight' => [],
        'latest' => ['all' => [], 'series' => [], 'movies' => []],
        'top' => ['day' => [], 'week' => [], 'month' => []],
    ];

    $provider = api_provider();
    $cacheKey = 'home_' . $provider;

    // ── Anikoto ─────────────────────────────────────────────────────
    if ($provider === 'anikoto') {
        $cached = api_cache_get($cacheKey, 1800);
        if (is_array($cached) && !empty($cached['spotlight']) && !empty($cached['latest']['all'])) {
            return $cached;
        }
        $data = api_try(function () {
            pa_load_anikoto();
            $raw = anikotoGetHome();
            return pa_anikoto_home($raw);
        }, null);
        // Validate: if parsing yielded empty spotlight/latest, treat as failure
        if (is_array($data) && !empty($data['spotlight']) && !empty($data['latest']['all'])) {
            api_cache_set($cacheKey, $data, 1800);
            return $data;
        }
        // Return stale cache if available (even expired) before falling back to empty
        if (is_array($cached)) {
            return $cached;
        }
        // Try stale with longer TTL
        $stale = api_cache_get($cacheKey, 86400);
        if (is_array($stale)) {
            return $stale;
        }
        return is_array($data) ? $data : $empty;
    }

    // ── Toonstream ──────────────────────────────────────────────────
    if ($provider === 'toonstream') {
        $cached = api_cache_get($cacheKey, 1800);
        if (is_array($cached) && !empty($cached['spotlight']) && !empty($cached['latest']['all'])) {
            return $cached;
        }
        $data = api_try(function () {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $providerInst = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            $raw = $providerInst->home();
            return pa_toonstream_home($raw);
        }, null);
        if (is_array($data) && !empty($data['spotlight']) && !empty($data['latest']['all'])) {
            api_cache_set($cacheKey, $data, 1800);
            return $data;
        }
        if (is_array($cached)) {
            return $cached;
        }
        $stale = api_cache_get($cacheKey, 86400);
        if (is_array($stale)) {
            return $stale;
        }
        return is_array($data) ? $data : $empty;
    }

    // ── Watchhentai ─────────────────────────────────────────────────
    if ($provider === 'watchhentai') {
        $cached = api_cache_get($cacheKey, 1800);
        if (is_array($cached) && !empty($cached['spotlight']) && !empty($cached['latest']['all'])) {
            return $cached;
        }
        $data = api_try(function () {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $providerInst = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $raw = $providerInst->home();
            $data = pa_watchhentai_home($raw);
            // Popular rail (Top Animes block renders it as "Popular"): the
            // 10 most-viewed series. Cached with home data.
            try {
                $pop = $providerInst->listing('/series/?orderby=views', 1);
                $day = [];
                foreach (array_slice($pop['items'] ?? [], 0, 10) as $rank => $it) {
                    $slug = $it['slug'] ?? pa_slug_from_url($it['url'] ?? '');
                    if ($slug === '') continue;
                    $day[] = [
                        'rank' => $rank + 1,
                        'title' => $it['title'] ?? '',
                        'slug' => $slug,
                        'image' => $it['image'] ?? '/assets/images/placeholder.jpg',
                        'rating' => $it['rating'] ?? '',
                    ];
                }
                if ($day !== []) {
                    $data['top']['day'] = $day;
                }
            } catch (\Throwable $e) {
            }
            // Extra genre rows mirror the source site's own homepage
            // sections (original names). Fetched once, cached with home.
            foreach (['uncensored' => 'Uncensored', 'harem' => 'Harem', 'school-girls' => 'School Girls'] as $slug => $label) {
                try {
                    $list = $providerInst->listing('/genre/' . $slug . '/', 1);
                } catch (\Throwable $e) {
                    continue;
                }
                $items = [];
                foreach (array_slice($list['items'] ?? [], 0, 12) as $it) {
                    $items[] = pa_watchhentai_card($it);
                }
                if ($items !== []) {
                    $data['extraSections'][] = ['title' => $label, 'items' => $items];
                }
            }
            return $data;
        }, null);
        if (is_array($data) && !empty($data['spotlight']) && !empty($data['latest']['all'])) {
            api_cache_set($cacheKey, $data, 1800);
            return $data;
        }
        if (is_array($cached)) {
            return $cached;
        }
        $stale = api_cache_get($cacheKey, 86400);
        if (is_array($stale)) {
            return $stale;
        }
        return is_array($data) ? $data : $empty;
    }

    // ── Animedekho ──────────────────────────────────────────────────
    if ($provider === 'animedekho') {
        $cached = api_cache_get($cacheKey, 1800);
        if (is_array($cached) && !empty($cached['spotlight']) && !empty($cached['latest']['all'])) {
            return $cached;
        }
        $data = api_try(function () {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            $raw = $ad->home();
            $sections = $raw['sections'] ?? [];

            // Classify provider rows so their data stays in the right place.
            $isEpisodeRow = static fn (string $t): bool => stripos($t, 'episode') !== false;
            $isTopRow = static fn (string $t): bool => (bool) preg_match('/top\s*\d|top\s*rated|most\s*(viewed|popular)|trending/i', $t);

            // One card per title (sections overlap; e.g. a series appears in both
            // "New series" and a Top row).
            $dedupe = static function (array $cards): array {
                $out = [];
                $seen = [];
                foreach ($cards as $c) {
                    $key = ($c['slug'] ?? '') !== '' ? $c['slug'] : ($c['title'] ?? '');
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[] = $c;
                }
                return $out;
            };

            // Every provider row keeps its own section, so "Latest episodes",
            // "New series", "New movies" and the Top rows each render once with
            // their own data — nothing is merged together.
            $extraSections = [];
            $latestSeries = [];
            $latestMovies = [];
            foreach ($sections as $sec) {
                $title = $sec['title'] ?? 'Section';
                $items = [];
                foreach ($sec['items'] ?? [] as $it) {
                    $items[] = pa_animedekho_card($it);
                }
                if (!$items) continue;
                // "standalone" rows render as their own section. The episode row
                // and the Top/top-rated rows do; "New series"/"New movies" do not,
                // because their items ARE the Latest Animes grid (avoids showing
                // the same titles twice).
                $standalone = $isEpisodeRow($title) || $isTopRow($title);
                $extraSections[] = ['title' => $title, 'items' => $items, 'standalone' => $standalone];

                // "Latest Animes" is ONLY the latest series/movies: never the
                // episode row and never the Top-10/top-rated rows (that data
                // already has its own section and must not leak into Latest Animes).
                if ($isEpisodeRow($title) || $isTopRow($title)) {
                    continue;
                }
                if (stripos($title, 'movie') !== false) {
                    $latestMovies = array_merge($latestMovies, $items);
                } else {
                    $latestSeries = array_merge($latestSeries, $items);
                }
            }

            $latestSeries = $dedupe($latestSeries);
            $latestMovies = $dedupe($latestMovies);
            $latestAll = $dedupe(array_merge($latestSeries, $latestMovies));

            // spotlight: first 6 of the latest list, use title text (no logo)
            $spotlight = [];
            foreach (array_slice($latestAll, 0, 6) as $c) {
                $spotlight[] = [
                    'title' => $c['title'],
                    'slug' => $c['slug'],
                    'image' => $c['image'],
                    'logo' => '', // use title text
                    'audio' => $c['episode'],
                    'genres' => '',
                    'description' => '',
                ];
            }
            return [
                'spotlight' => $spotlight,
                'latest' => ['all' => $latestAll, 'series' => $latestSeries, 'movies' => $latestMovies],
                'extraSections' => $extraSections,
                'top' => ['day' => [], 'week' => [], 'month' => []],
            ];
        }, null);
        if (is_array($data) && !empty($data['spotlight']) && !empty($data['latest']['all'])) {
            api_cache_set($cacheKey, $data, 1800);
            return $data;
        }
        if (is_array($cached) && !empty($cached['spotlight'])) {
            return $cached;
        }
        $stale = api_cache_get_stale($cacheKey);
        if (is_array($stale) && !empty($stale['spotlight'])) {
            return $stale;
        }
        // Provider blocked (403) — return last known good or dummy so home never blank
        if (is_array($data) && !empty($data['spotlight'])) {
            return $data;
        }
        // Final fallback: seed from toonstream cache if available
        $fallback = api_cache_get_stale('home_toonstream');
        if (is_array($fallback) && !empty($fallback['spotlight'])) {
            // re-label as animedekho fallback to avoid empty
            api_cache_set($cacheKey, $fallback, 1800);
            return $fallback;
        }
        return $empty;
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $home = api_try(fn () => api_scraper()->getHome(), []);
    if (!is_array($home)) {
        return $empty;
    }

    $spotlight = [];
    foreach (array_slice($home['spotlight'] ?? [], 0, 6) as $s) {
        $banner = $s['banner'] ?? '';
        // animetadka has logo (poster), keep it; limit 4-6
        $spotlight[] = [
            'title' => '',
            'slug' => api_slug_from_url($s['details_url'] ?? ($s['watch_url'] ?? '')),
            'image' => $banner,
            'logo' => $s['poster'] ?? $banner,
            'audio' => $s['badge'] ?? '',
            'genres' => $s['genres'] ?? '',
            'description' => $s['description'] ?? '',
        ];
    }

    $latest = [];
    foreach (['all', 'series', 'movies'] as $type) {
        $latest[$type] = [];
        foreach ($home['latest'][$type] ?? [] as $item) {
            $latest[$type][] = api_card_view($item);
        }
    }

    $top = [];
    foreach (['day', 'week', 'month'] as $range) {
        $top[$range] = [];
        foreach ($home['top'][$range] ?? [] as $t) {
            $top[$range][] = api_top_view($t);
        }
    }

    return ['spotlight' => $spotlight, 'latest' => $latest, 'top' => $top];
}

/**
 * Top-10 ranking card (day|week|month).
 */
function api_top_view(array $t): array {
    $langs = $t['languages'] ?? [];
    return [
        'rank' => (int) ($t['rank'] ?? 0),
        'title' => $t['title'] ?? '',
        'slug' => api_slug_from_url($t['url'] ?? ''),
        'image' => $t['poster'] ?? '',
        'rating' => is_array($langs) ? implode(', ', $langs) : (string) $langs,
    ];
}

/**
 * Latest grid cards (all|series|movies).
 */
function api_latest_items(string $type = 'all') {
    $provider = api_provider();

    // ── Anikoto ─────────────────────────────────────────────────────
    if ($provider === 'anikoto') {
        return api_try(function () use ($type) {
            pa_load_anikoto();
            $data = anikotoGetHome();
            $items = [];
            foreach ($data['latestEpisodeAnimes'] ?? [] as $item) {
                $items[] = pa_anikoto_card($item);
            }
            return $items;
        }, []);
    }

    // ── Toonstream ──────────────────────────────────────────────────
    if ($provider === 'toonstream') {
        return api_try(function () use ($type) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            $data = $ts->home();
            $items = [];
            foreach ($data['latestSeries'] ?? [] as $item) {
                $items[] = pa_toonstream_card($item);
            }
            foreach ($data['latestEpisodes'] ?? [] as $item) {
                $items[] = pa_toonstream_card($item);
            }
            return $items;
        }, []);
    }

    // ── Watchhentai ─────────────────────────────────────────────────
    if ($provider === 'watchhentai') {
        return api_try(function () use ($type) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $data = $wh->home();
            $items = [];
            foreach ($data['latestSeries'] ?? [] as $item) {
                $items[] = pa_watchhentai_card($item);
            }
            foreach ($data['latestEpisodes'] ?? [] as $item) {
                $items[] = pa_watchhentai_card($item);
            }
            return $items;
        }, []);
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $data = api_try(fn () => api_scraper()->getLatest($type), []);
    $items = [];
    foreach (is_array($data) ? ($data['items'] ?? []) : [] as $item) {
        $items[] = api_card_view($item);
    }
    return $items;
}

/**
 * Full "All Animes" listing with pagination.
 *
 * @return array{items: array<int, array<string, mixed>>, pagination: array<string, mixed>}
 */
function api_all_items(int $page = 1) {
    $provider = api_provider();

    // ── Anikoto: browse endpoint ────────────────────────────────────
    if ($provider === 'anikoto') {
        $data = api_try(function () use ($page) {
            pa_load_anikoto();
            return anikotoBrowse([], $page);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['animes'] ?? [] as $item) {
            $items[] = pa_anikoto_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['currentPage'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Toonstream: browse/listing endpoint ──────────────────────────
    if ($provider === 'toonstream') {
        $data = api_try(function () use ($page) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            return $ts->home();
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach (($data['latestSeries'] ?? []) as $item) {
            $items[] = pa_toonstream_card($item);
        }
        foreach (($data['movies'] ?? []) as $item) {
            $items[] = pa_toonstream_card($item);
        }
        return [
            'items' => $items,
            'pagination' => ['current' => $page, 'total' => 1],
        ];
    }

    // ── Watchhentai: videos listing endpoint (paginated) ─────────────
    // (series only — watchhentai has no movies)
    if ($provider === 'watchhentai') {
        $data = api_try(function () use ($page) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            return $wh->listing('/videos/', $page);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach (($data['items'] ?? []) as $item) {
            $items[] = pa_watchhentai_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Animedekho: browse grid (paginated) ─────────────────────────
    if ($provider === 'animedekho') {
        $data = api_try(function () use ($page) {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            return $ad->listing('/browse/', $page);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_animedekho_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $data = api_try(fn () => api_scraper()->getAllAnimes($page), null);
    if (!is_array($data)) {
        return ['items' => [], 'pagination' => ['current' => 1, 'total' => 1]];
    }

    $items = [];
    foreach ($data['items'] ?? [] as $item) {
        $items[] = api_card_view($item);
    }

    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $pagination['current'] = (int) ($pagination['current'] ?? $page);
    $pagination['total'] = (int) ($pagination['total'] ?? 1);

    return ['items' => $items, 'pagination' => $pagination];
}

/**
 * Letter listing (A-Z) with pagination.
 *
 * @return array{items: array<int, array<string, mixed>>, pagination: array<string, mixed>}
 */
function api_letter_items(string $letter, int $page = 1, string $filter = '') {
    $provider = api_provider();

    // ── Anikoto: az-list endpoint ───────────────────────────────────
    if ($provider === 'anikoto') {
        $data = api_try(function () use ($letter, $page) {
            pa_load_anikoto();
            return anikotoGetAzList($letter, $page);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['animes'] ?? [] as $item) {
            $items[] = pa_anikoto_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['currentPage'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Toonstream: letter endpoint ─────────────────────────────────
    if ($provider === 'toonstream') {
        $data = api_try(function () use ($letter, $page) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            return $ts->listing('/letter/' . \ToonStreamApi\Utils\Url::encodePath($letter), $page, false);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_toonstream_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Watchhentai: letter endpoint ────────────────────────────────
    if ($provider === 'watchhentai') {
        $data = api_try(function () use ($letter, $page) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            return $wh->listing('/series/?letter=' . rawurlencode($letter), $page, false);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_watchhentai_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Animedekho: browse by letter ────────────────────────────────
    if ($provider === 'animedekho') {
        $data = api_try(function () use ($letter, $page) {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            return $ad->listing('/browse/', $page, ['letter' => $letter]);
        }, null);
        if (!is_array($data)) {
            return ['items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_animedekho_card($item);
        }
        return [
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $data = api_try(fn () => api_scraper()->getLetter($letter, $page, $filter), null);
    if (!is_array($data)) {
        return ['items' => [], 'pagination' => ['current' => 1, 'total' => 1]];
    }

    $items = [];
    foreach ($data['items'] ?? [] as $item) {
        $items[] = api_card_view($item);
    }

    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $pagination['current'] = (int) ($pagination['current'] ?? $page);
    $pagination['total'] = (int) ($pagination['total'] ?? 1);

    return ['items' => $items, 'pagination' => $pagination];
}

/**
 * Category listing data (type|lang|genre|studio|sub): label, title, items.
 */
function api_category_data(string $type, string $slug, int $page = 1, string $filter = '') {
    $provider = api_provider();

    // ── Anikoto: genre/category/type endpoints ──────────────────────
    if ($provider === 'anikoto') {
        $cacheKey = api_cache_key($provider, $type, $slug, $page);
        $cached = api_cache_get($cacheKey, 300);
        if (is_array($cached)) {
            return $cached;
        }

        $data = api_try(function () use ($type, $slug, $page) {
            pa_load_anikoto();
            switch ($type) {
                case 'genre':  return anikotoGetGenre($slug, $page);
                case 'type':   return anikotoGetType($slug, $page);
                case 'season': return anikotoBrowse(['status' => $slug], $page);
                default:       return anikotoGetCategory($slug, $page);
            }
        }, null);
        if (!is_array($data)) {
            $result = ['label' => '', 'title' => '', 'items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
            api_cache_set($cacheKey, $result, 60);
            return $result;
        }
        $items = [];
        foreach ($data['animes'] ?? [] as $item) {
            $items[] = pa_anikoto_card($item);
        }
        $result = [
            'label' => $data['title'] ?? $data['genreName'] ?? $data['category'] ?? $data['type'] ?? '',
            'title' => $data['title'] ?? '',
            'items' => $items,
            'pagination' => [
                'current' => $data['currentPage'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
        api_cache_set($cacheKey, $result, 300);
        return $result;
    }

    // ── Toonstream: category endpoint ───────────────────────────────
    if ($provider === 'toonstream') {
        $cacheKey = api_cache_key($provider, $type, $slug, $page);
        $cached = api_cache_get($cacheKey, 300);
        if (is_array($cached)) {
            return $cached;
        }

        $catPath = $type . '/' . $slug;
        $data = api_try(function () use ($catPath, $page) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            return $ts->listing('/category/' . \ToonStreamApi\Utils\Url::encodePath($catPath), $page);
        }, null);
        if (!is_array($data)) {
            $result = ['label' => '', 'title' => '', 'items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
            api_cache_set($cacheKey, $result, 60);
            return $result;
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_toonstream_card($item);
        }
        $result = [
            'label' => ucwords(str_replace('-', ' ', $type)) . ' Collection',
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
        api_cache_set($cacheKey, $result, 300);
        return $result;
    }

    // ── Watchhentai: category endpoint ──────────────────────────────
    if ($provider === 'watchhentai') {
        $cacheKey = api_cache_key($provider, $type, $slug, $page);
        $cached = api_cache_get($cacheKey, 300);
        if (is_array($cached)) {
            return $cached;
        }

        // Map site catalog types to real watchhentai.net source paths
        // (verified live): genres → /genre/{slug}/, movies → /3d/,
        // series → /series/, recency catalogs → /videos/.
        // Season/status slugs have no source equivalent → empty result.
        $sourcePath = null;
        $typeLower = strtolower($type);
        $slugLower = strtolower($slug);
        if ($typeLower === 'genre') {
            $sourcePath = '/genre/' . $slug;
        } elseif ($typeLower === 'year') {
            $sourcePath = '/release/' . $slug . '/';
        } elseif ($typeLower === 'type' && in_array($slugLower, ['series', 'tv'], true)) {
            $sourcePath = '/series/';
        } elseif ($typeLower === 'category') {
            $sourcePath = '/videos/';
        }
        // Note: no movie/movies mapping — watchhentai has no movies, so
        // /catalog/movie falls into the empty result below.
        if ($sourcePath === null) {
            $result = [
                'label' => ucwords(str_replace('-', ' ', $type)) . ' Collection',
                'title' => ucwords(str_replace('-', ' ', $slug)),
                'items' => [],
                'pagination' => ['current' => $page, 'total' => 1],
            ];
            api_cache_set($cacheKey, $result, 60);
            return $result;
        }
        $data = api_try(function () use ($sourcePath, $page) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $path = rtrim($sourcePath, '/') . '/';
            return $wh->listing($path, $page);
        }, null);
        if (!is_array($data)) {
            // Try stale cache before falling back to empty (mirrors toonstream stale-cache fallback).
            $stale = api_cache_get_stale($cacheKey);
            if (is_array($stale)) {
                return $stale;
            }
            $result = ['label' => '', 'title' => '', 'items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
            api_cache_set($cacheKey, $result, 60);
            return $result;
        }
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            $items[] = pa_watchhentai_card($item);
        }
        $result = [
            'label' => ucwords(str_replace('-', ' ', $type)) . ' Collection',
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'items' => $items,
            'pagination' => [
                'current' => $data['page'] ?? $page,
                'total' => $data['totalPages'] ?? 1,
            ],
        ];
        api_cache_set($cacheKey, $result, 300);
        return $result;
    }

    // ── Animedekho: home/listing endpoints ───────────────────────────
    if ($provider === 'animedekho') {
        $cacheKey = api_cache_key($provider, $type, $slug, $page);
        $cached = api_cache_get($cacheKey, 300);
        if (is_array($cached)) {
            return $cached;
        }

        putenv('ANIMEDEKHO_TIMEOUT=10');
        $data = api_try(function () use ($type, $slug, $page) {
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());

            if ($type === 'home') {
                $data = $ad->home();
                $items = [];
                foreach ($data['sections'] ?? [] as $section) {
                    foreach ($section['items'] ?? [] as $item) {
                        $items[] = pa_animedekho_card($item);
                    }
                }
                return [
                    'label' => 'Home',
                    'title' => 'Home',
                    'items' => $items,
                    'pagination' => ['current' => $page, 'total' => 1],
                ];
            }

            // Type/genre/season all map to the site's /browse/ grid, which
            // accepts type, genre and pg params (type=movies|series|all).
            $params = [];
            if ($type === 'type') {
                $params['type'] = in_array($slug, ['movie', 'movies'], true) ? 'movies' : ($slug === 'tv' ? 'series' : $slug);
            } elseif ($type === 'genre') {
                $params['genre'] = $slug;
            }
            $listing = $ad->listing('/browse/', $page, $params);
            $items = [];
            foreach ($listing['items'] ?? [] as $item) {
                $items[] = pa_animedekho_card($item);
            }
            return [
                'label' => ucwords(str_replace('-', ' ', $type)) . ' Collection',
                'title' => ucwords(str_replace('-', ' ', $slug)),
                'items' => $items,
                'pagination' => [
                    'current' => $listing['page'] ?? $page,
                    'total' => $listing['totalPages'] ?? 1,
                ],
            ];
        }, null);
        if (!is_array($data)) {
            $result = ['label' => '', 'title' => '', 'items' => [], 'pagination' => ['current' => $page, 'total' => 1]];
            api_cache_set($cacheKey, $result, 60);
            return $result;
        }
        api_cache_set($cacheKey, $data, 300);
        return $data;
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $data = api_try(fn () => api_scraper()->getCategory($type, $slug, $page, $filter), []);
    $items = [];
    foreach (is_array($data) ? ($data['items'] ?? []) : [] as $item) {
        $items[] = api_card_view($item);
    }

    $pagination = is_array($data) && is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $pagination['current'] = (int) ($pagination['current'] ?? $page);
    $pagination['total'] = (int) ($pagination['total'] ?? 1);

    return [
        'label' => is_array($data) ? ($data['label'] ?? '') : '',
        'title' => is_array($data) ? ($data['title'] ?? '') : '',
        'items' => $items,
        'pagination' => $pagination,
    ];
}

/**
 * Search results as grid cards.
 */
function api_search_items(string $query) {
    $provider = api_provider();

    // ── Anikoto ─────────────────────────────────────────────────────
    if ($provider === 'anikoto') {
        return api_try(function () use ($query) {
            pa_load_anikoto();
            $data = anikotoSearch($query);
            return pa_anikoto_search($data);
        }, []);
    }

    // ── Toonstream ──────────────────────────────────────────────────
    if ($provider === 'toonstream') {
        return api_try(function () use ($query) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            $data = $ts->search($query);
            return pa_toonstream_search($data);
        }, []);
    }

    // ── Watchhentai ─────────────────────────────────────────────────
    if ($provider === 'watchhentai') {
        return api_try(function () use ($query) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $data = $wh->search($query);
            return pa_watchhentai_search($data);
        }, []);
    }

    // ── Animedekho ──────────────────────────────────────────────────
    if ($provider === 'animedekho') {
        return api_try(function () use ($query) {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            $data = $ad->search($query);
            $items = [];
            foreach ($data['results'] ?? [] as $r) {
                $items[] = pa_animedekho_card($r);
            }
            return $items;
        }, []);
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $data = api_try(fn () => api_scraper()->search($query), []);
    $items = [];
    foreach (is_array($data) ? ($data['results'] ?? []) : [] as $r) {
        $items[] = [
            'title' => $r['title'] ?? '',
            'slug' => api_slug_from_url($r['url'] ?? ''),
            'episode' => '',
            'audio' => $r['languages'] ?? '',
            'image' => $r['poster'] ?? '/assets/images/placeholder.jpg',
        ];
    }
    return $items;
}

/**
 * Home spotlight mapped to the search-page spotlight contract.
 */
function api_search_spotlight() {
    $provider = api_provider();

    if ($provider === 'anikoto') {
        return api_try(function () {
            pa_load_anikoto();
            $data = anikotoGetHome();
            $home = pa_anikoto_home($data);
            $slides = [];
            foreach ($home['spotlight'] as $i => $s) {
                $slides[] = [
                    'bg' => $s['image'],
                    'logo' => $s['logo'],
                    'rank' => '#' . ($i + 1) . ' Spotlight',
                    'badge' => $s['audio'],
                    'genres' => $s['genres'],
                    'desc' => $s['description'],
                    'watch' => '/' . $s['slug'] . '/',
                    'details' => '/' . $s['slug'] . '/',
                ];
            }
            return $slides;
        }, []);
    }

    if ($provider === 'toonstream') {
        return api_try(function () {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            $data = $ts->home();
            $home = pa_toonstream_home($data);
            $slides = [];
            foreach ($home['spotlight'] as $i => $s) {
                $slides[] = [
                    'bg' => $s['image'],
                    'logo' => $s['logo'],
                    'rank' => '#' . ($i + 1) . ' Spotlight',
                    'badge' => $s['audio'],
                    'genres' => $s['genres'],
                    'desc' => $s['description'],
                    'watch' => '/' . $s['slug'] . '/',
                    'details' => '/' . $s['slug'] . '/',
                ];
            }
            return $slides;
        }, []);
    }

    if ($provider === 'watchhentai') {
        return api_try(function () {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            $data = $wh->home();
            $home = pa_watchhentai_home($data);
            $slides = [];
            foreach ($home['spotlight'] as $i => $s) {
                $slides[] = [
                    'bg' => $s['image'],
                    'logo' => $s['logo'],
                    'rank' => '#' . ($i + 1) . ' Spotlight',
                    'badge' => $s['audio'],
                    'genres' => $s['genres'],
                    'desc' => $s['description'],
                    'watch' => '/' . $s['slug'] . '/',
                    'details' => '/' . $s['slug'] . '/',
                ];
            }
            return $slides;
        }, []);
    }

    // Animetadka (default)
    $home = api_home_data();
    $slides = [];
    foreach ($home['spotlight'] as $i => $s) {
        $slides[] = [
            'bg' => $s['image'],
            'logo' => $s['logo'],
            'rank' => '#' . ($i + 1) . ' Spotlight',
            'badge' => $s['audio'],
            'genres' => $s['genres'],
            'desc' => $s['description'],
            'watch' => '/' . $s['slug'] . '/',
            'details' => '/' . $s['slug'] . '/',
        ];
    }
    return $slides;
}

/**
 * Anime detail page data: anime (view contract) + flattened episodes list.
 * Returns null when the slug cannot be resolved.
 */
function api_anime_data(string $slug) {
    $provider = api_provider();

    // ── Anikoto ─────────────────────────────────────────────────────
    if ($provider === 'anikoto') {
        $data = api_try(function () use ($slug) {
            pa_load_anikoto();
            return anikotoGetById($slug);
        }, null);
        if (!is_array($data)) return null;
        $normalized = pa_anikoto_anime($data);
        if ($normalized === null) return null;
        // Fetch episodes for this anime
        $episodes = api_try(function () use ($slug) {
            pa_load_anikoto();
            $epData = anikotoGetEpisodes($slug);
            return pa_anikoto_episodes($epData);
        }, []);
        $normalized['episodes'] = $episodes;
        // Build seasons from episodes
        if (!empty($episodes)) {
            $normalized['seasons'] = [[
                'season' => 1,
                'title' => 'Season 1',
                'episode_count' => count($episodes) . ' Episodes',
                'poster' => $normalized['anime']['poster'],
                'episodes' => array_map(function ($ep) {
                    return [
                        'label' => 'S1-E' . $ep['number'] . ': ' . $ep['title'],
                        'url' => '',
                        'thumbnail' => $ep['thumbnail'],
                    ];
                }, $episodes),
            ]];
        }
        return $normalized;
    }

    // ── Toonstream ──────────────────────────────────────────────────
    if ($provider === 'toonstream') {
        $origSlug = $slug;
        $slug = preg_replace('/\?.*$/', '', $slug);
        $slug = preg_replace('/^post-/', '', $slug);
        $slug = ltrim($slug, '/');
        $slug = preg_replace('#^(episode|series|movies|ep)/#', '', $slug);
        $isMovieSlug = str_contains(strtolower($slug), 'movie');
        if (!$isMovieSlug) $slug = preg_replace('/-\d+x\d+$/', '', $slug);
        // Type detection via href if available: episode/series -> series, movies -> movie, else fallback.
        // An explicit ?type= query param always wins (e.g. ?type=series must not render as movie).
        $typeHint = 'series';
        if (preg_match('#/(movies|movie)/#i', $origSlug)) $typeHint = 'movies';
        $reqTypeHint = strtolower(trim((string) ($_GET['type'] ?? '')));
        if ($reqTypeHint === 'movie' || $reqTypeHint === 'movies') $typeHint = 'movies';
        elseif ($reqTypeHint === 'series' || $reqTypeHint === 'episode') $typeHint = 'series';
        $data = api_try(function () use ($slug, $typeHint) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            return $ts->detail($slug, 'all', false, null, $typeHint);
        }, null);
        if (!is_array($data) || empty($data['title'])) {
            $mSlug = $origSlug;
            $mSlug = preg_replace('/^post-/', '', $mSlug);
            $mSlug = ltrim($mSlug, '/');
            $mSlug = preg_replace('#^(episode|series|movies)/#', '', $mSlug);
            $fallbackType = $typeHint === 'movies' ? 'series' : 'movies';
            $data = api_try(function () use ($mSlug, $fallbackType) {
                pa_load_toonstream();
                $http = new \ToonStreamApi\Utils\HttpClient();
                $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
                return $ts->detail($mSlug, 'all', false, null, $fallbackType);
            }, null);
        }
        if (!is_array($data) || empty($data['title'])) return null;
        return pa_toonstream_anime_detail($data);
    }

    // ── Watchhentai ─────────────────────────────────────────────────
    if ($provider === 'watchhentai') {
        $origSlug = $slug;
        $slug = preg_replace('/\?.*$/', '', $slug);
        $slug = preg_replace('/^post-/', '', $slug);
        $slug = ltrim($slug, '/');
        $slug = preg_replace('#^(videos|episode|series|movies|3d)/#', '', $slug);
        // Episode-style slugs carry -episode-{n}-id-{id}; detail needs the series slug.
        $slug = preg_replace('/-episode-\d+-id-\d+$/', '', $slug);
        // Type detection via href if available: videos/episode -> series, movies/3d -> movie, else fallback.
        // An explicit ?type= query param always wins (e.g. ?type=series must not render as movie).
        $typeHint = 'series';
        if (preg_match('#/(movies|movie|3d)/#i', $origSlug)) $typeHint = 'movies';
        $reqTypeHint = strtolower(trim((string) ($_GET['type'] ?? '')));
        if ($reqTypeHint === 'movie' || $reqTypeHint === 'movies') $typeHint = 'movies';
        elseif ($reqTypeHint === 'series' || $reqTypeHint === 'episode') $typeHint = 'series';
        $data = api_try(function () use ($slug, $typeHint) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            return $wh->detail($slug, 'all', false, null, $typeHint);
        }, null);
        if (!is_array($data) || empty($data['title'])) {
            $mSlug = $origSlug;
            $mSlug = preg_replace('/^post-/', '', $mSlug);
            $mSlug = ltrim($mSlug, '/');
            $mSlug = preg_replace('#^(videos|episode|series|movies|3d)/#', '', $mSlug);
            $mSlug = preg_replace('/-episode-\d+-id-\d+$/', '', $mSlug);
            $fallbackType = $typeHint === 'movies' ? 'series' : 'movies';
            $data = api_try(function () use ($mSlug, $fallbackType) {
                pa_load_watchhentai();
                $http = new \WatchHentaiApi\Utils\HttpClient();
                $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
                return $wh->detail($mSlug, 'all', false, null, $fallbackType);
            }, null);
        }
        if (!is_array($data) || empty($data['title'])) return null;
        return pa_watchhentai_anime($data);
    }

    // ── Animedekho ──────────────────────────────────────────────────
    if ($provider === 'animedekho') {
        // Strip any ?type= hint from the slug (Router already strips it for
        // web hits, but /api/* callers may pass it through).
        $slug = preg_replace('/\?.*$/', '', $slug);
        $data = api_try(function () use ($slug) {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            return $ad->detail($slug, 'all', false, null, 'series');
        }, null);
        if (!is_array($data)) {
            // Movie URLs live under /movie-hindi/{slug}/ — try movie detail.
            $movie = api_try(function () use ($slug) {
                pa_load_animedekho();
                require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
                $http = new \AnimeDekhoApi\Utils\HttpClient();
                $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
                return $ad->movie($slug, null);
            }, null);
            if (is_array($movie) && !empty($movie['title'])) {
                $movie['episodes'] = 1;
                $movie['episodes_count'] = 1;
                return pa_animedekho_anime($movie);
            }
            return null;
        }
        $result = pa_animedekho_anime($data);
        // Ensure episodes field is never 0 (movies default to 1).
        if (is_array($result) && ($result['anime']['episodes'] ?? '') === '0') {
            $result['anime']['episodes'] = '1';
        }
        return $result;
    }

    // ── Animetadka (default) ────────────────────────────────────────
    // Explicit ?type= hint (e.g. ?type=series) is respected: it selects the
    // lookup flavor and suppresses the single-unit "Movie" synthesis below.
    $reqType = strtolower(trim((string) ($_GET['type'] ?? '')));
    if ($reqType === '' && str_contains($slug, '?type=')) {
        [$slug, $reqType] = explode('?type=', $slug, 2);
        $reqType = strtolower(trim((string) explode('&', $reqType)[0]));
    }
    $slug = preg_replace('/\?.*$/', '', $slug);
    $isExplicitSeries = ($reqType === 'series' || $reqType === 'episode');
    $a = api_try(fn () => api_scraper()->getAnime($slug), null);

    if (!is_array($a)) {
        return null;
    }

    $meta = is_array($a['meta'] ?? null) ? $a['meta'] : [];

    // Unresolvable slug: scraper returned an empty shape (no title) → treat as 404.
    if ((string) ($a['title'] ?? '') === '') {
        return null;
    }

    $genres = [];
    foreach ($a['genres'] ?? [] as $g) {
        $name = is_array($g) ? ($g['name'] ?? '') : $g;
        if ((string) $name !== '') {
            $genres[] = (string) $name;
        }
    }

    $latest = $a['latest_episode'] ?? null;
    $metaEpisodes = api_display_names($meta['episodes'] ?? '');
    // Movies have no separate episode listing — the movie itself is the single
    // playable unit. If episodes is empty/missing, derive from parsed seasons
    // (series) and only default to "1" for actual movies. An explicit
    // ?type=series never collapses to "1" (was misread as a movie signal).
    if ($metaEpisodes === '' || $metaEpisodes === '0') {
        $derived = 0;
        foreach ($a['seasons'] ?? [] as $sn) {
            $derived += count(is_array($sn) ? ($sn['episodes'] ?? []) : []);
        }
        if ($derived > 0) {
            $metaEpisodes = (string) $derived;
        } elseif ($isExplicitSeries) {
            $metaEpisodes = '';
        } else {
            $metaEpisodes = '1';
        }
    }
    $anime = [
        'title' => $a['title'] ?? '',
        'slug' => $a['slug'] ?? $slug,
        'poster' => api_proxy_image($a['poster'] ?? '/assets/images/placeholder.jpg'),
        'image' => api_proxy_image($a['poster'] ?? '/assets/images/placeholder.jpg'),
        'type' => api_display_names($meta['type'] ?? ''),
        'status' => api_display_names($meta['status'] ?? ''),
        'episodes' => $metaEpisodes,
        'aired' => api_display_names($meta['aired'] ?? ''),
        'duration' => api_display_names($meta['duration'] ?? ''),
        'quality' => api_display_names($meta['quality'] ?? ''),
        'audio' => api_display_names($meta['audio_languages'] ?? ''),
        'subtitle' => api_display_names($meta['subtitles'] ?? ''),
        'studio' => api_display_names($meta['studio'] ?? ''),
        'encode' => api_display_names($meta['encode'] ?? ''),
        'rating' => api_display_names($meta['rating'] ?? ''),
        'views' => api_display_names($meta['views'] ?? ''),
        'description' => $a['description'] ?? '',
        'genres' => $genres,
        'latest_episode' => is_array($latest) ? [
            'slug' => api_slug_from_url($latest['url'] ?? ''),
            'label' => $latest['label'] ?? '',
        ] : null,
        'external_ids' => is_array($a['external_ids'] ?? null)
            ? $a['external_ids']
            : ['mal' => null, 'tmdb' => null],
        'animePoster' => api_proxy_image($a['poster'] ?? '/assets/images/placeholder.jpg'),
    ];

    // Movie fallback: if seasons empty, synthesize 1 movie episode so detail shows button (was 0 episodes).
    // Suppressed for an explicit ?type=series (or slug with no movie signal but series markers):
    // multi-episode series whose episode list failed to parse must not masquerade as "Full Movie".
    if (empty($a['seasons']) && !empty($a['title']) && !$isExplicitSeries) {
        $a['seasons'] = [['season'=>1,'title'=>'Movie','episode_count'=>'1 Episodes','poster'=>$a['poster']??'', 'episodes'=>[['label'=>'Movie '.($a['title']??''), 'url'=>'/'.($a['slug']??$slug).'/', 'thumbnail'=>$a['poster']??'']]]];
    }
    $episodes = [];
    foreach ($a['seasons'] ?? [] as $season) {
        foreach (is_array($season) ? ($season['episodes'] ?? []) : [] as $ep) {
            $parts = api_episode_label_parts((string) ($ep['label'] ?? ''));
            $episodes[] = [
                'slug' => api_slug_from_url($ep['url'] ?? ''),
                'number' => $parts['number'],
                'title' => $parts['title'],
                'thumbnail' => $ep['thumbnail'] ?? '',
                'watched' => false,
            ];
        }
    }

    return ['anime' => $anime, 'episodes' => $episodes, 'seasons' => $a['seasons'] ?? []];
}

/**
 * Episode watch page data mapped to the watch view contract.
 * Returns null when the hash cannot be resolved.
 */
function api_episode_data(string $hash) {
    $provider = api_provider();

    // ── Anikoto ─────────────────────────────────────────────────────
    if ($provider === 'anikoto') {
        // Anikoto episode IDs are "{animeId}/ep/{num}" format
        // We parse it here and return a minimal episode view.
        $parts = explode('/ep/', $hash);
        if (count($parts) !== 2) return null;
        $animeId = $parts[0];
        $epNum = (int) $parts[1];

        $data = api_try(function () use ($animeId, $epNum) {
            pa_load_anikoto();
            return anikotoGetEpisode($animeId, $epNum);
        }, null);
        if (!is_array($data)) return null;

        $ep = $data['episode'] ?? null;
        if (!is_array($ep)) return null;

        $sources = $ep['sources'] ?? [];
        $playerSource = $sources['sub'] ?? $sources['dub'] ?? null;

        // Anime poster for fallback + seasons/recommendations (OTHER SEASONS / Recommended)
        $animeDetail = api_try(function () use ($animeId) {
            pa_load_anikoto();
            return anikotoGetById($animeId);
        }, null);
        $animePoster = $animeDetail['anime']['poster'] ?? $animeDetail['anime']['image'] ?? '/assets/images/placeholder.jpg';
        // Build recommendations from anime detail if available
        $recs = [];
        foreach ($animeDetail['related'] ?? $animeDetail['recommendations'] ?? [] as $r) {
            if (!is_array($r)) continue;
            $recs[] = ['slug' => $r['id'] ?? $r['slug'] ?? '', 'title' => $r['name'] ?? $r['title'] ?? '', 'poster' => $r['poster'] ?? $animePoster];
            if (count($recs) >= 12) break;
        }
        $seasonsOut = [];
        foreach ($animeDetail['seasons'] ?? [] as $s) {
            if (!is_array($s)) continue;
            $sn = $s['seasonNumber'] ?? $s['season'] ?? null;
            $eps = $s['episodes'] ?? [];
            $firstEpNum = is_array($eps[0] ?? null) ? ($eps[0]['number'] ?? '1') : '1';
            $seasonsOut[] = ['hash' => $animeId . '/ep/' . $firstEpNum, 'title' => $s['name'] ?? ($sn ? "Season $sn" : 'Season'), 'poster' => $s['poster'] ?? $animePoster];
        }

        // Build episodes list + prev/next for OTHER EPISODES (was [] -> empty drawer)
        $epList = [];
        $totalEps = 0;
        $epData = api_try(function () use ($animeId) {
            pa_load_anikoto();
            return anikotoGetEpisodes($animeId);
        }, null);
        if (is_array($epData) && isset($epData['episodes'])) {
            foreach ($epData['episodes'] as $e) {
                $n = (string)($e['number'] ?? '');
                $thumb = $e['image'] ?? $e['thumbnail'] ?? $animePoster; // fallback to anime poster if missing
                $epList[] = [
                    'hash' => $animeId . '/ep/' . $n,
                    'num' => $n,
                    'accent' => '',
                    'title' => $e['title'] ?? "Episode $n",
                    'thumb' => $thumb,
                    'active' => (int)$n === $epNum,
                ];
            }
            $totalEps = count($epList);
        }
        // Fallback to single-episode list if episodes API fails (e.g. movie)
        if (empty($epList)) {
            $epList[] = ['hash' => $hash, 'num' => (string)$epNum, 'accent' => '', 'title' => $ep['title'] ?? "Episode $epNum", 'thumb' => $animePoster, 'active' => true];
        }
        $prevUrl = $epNum > 1 ? '/ep/' . $animeId . '/ep/' . ($epNum - 1) . '/' : '';
        $nextUrl = ($totalEps > $epNum || empty($totalEps)) ? '/ep/' . $animeId . '/ep/' . ($epNum + 1) . '/' : '';

        return [
            'id' => $hash,
            'hash' => $hash,
            'accent' => '',
            'title' => $ep['title'] ?? "Episode $epNum",
            'animeSlug' => $animeId,
            'backLabel' => 'Back to Anime Details',
            'number' => (string) $epNum,
            'rating' => '',
            'runtime' => '',
            'description' => '',
            'player_url' => '',
            'player_source' => $playerSource,
            'next_episode_url' => $nextUrl,
            'prev_episode_url' => $prevUrl,
            'download_links' => [],
            'servers' => [
                [
                    'name' => 'Sub',
                    'active' => true,
                    'url' => $sources['sub'] ?? '',
                    'source' => $sources['sub'] ?? null,
                    'auto' => false,
                    'cdn21' => false,
                ],
                [
                    'name' => 'Dub',
                    'active' => false,
                    'url' => $sources['dub'] ?? '',
                    'source' => $sources['dub'] ?? null,
                    'auto' => false,
                    'cdn21' => false,
                ],
            ],
            'episodes' => $epList,
            'seasons' => $seasonsOut,
            'recommendations' => $recs,
            'animePoster' => $animePoster,
        ];
    }

    // ── Toonstream ──────────────────────────────────────────────────
    if ($provider === 'toonstream') {
        // Clean hash: strip post- prefix, keep -{num}x{num} suffix for episode lookup
        $epHash = preg_replace('/\?.*$/', '', $hash);
        $epHash = preg_replace('/^post-/', '', $epHash);
        $epHash = ltrim($epHash, '/');
        $epHash = preg_replace('#^(episode|series|movies|ep)/#', '', $epHash);
        $data = api_try(function () use ($epHash) {
            pa_load_toonstream();
            $http = new \ToonStreamApi\Utils\HttpClient();
            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
            return $ts->episode($epHash, null);
        }, null);
        // Fallback: if not found, try detail as movie (movies use /movies slug, not /episode)
        if (!is_array($data) || (empty($data['title']) && empty($data['servers']))) {
            $mSlug = preg_replace('/-\d+x\d+$/', '', $epHash);
            $mData = api_try(function () use ($mSlug) {
                pa_load_toonstream();
                $http = new \ToonStreamApi\Utils\HttpClient();
                $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
                return $ts->detail($mSlug, 'all', false, null, 'movies');
            }, null);
            if (is_array($mData) && !empty($mData['title'])) {
                $movie = pa_toonstream_anime_detail($mData);
                // Movie detail may have 0 seasons (no season UI) - synthesize
                if (is_array($movie) && empty($movie['seasons']) && empty($movie['episodes'])) {
                    $movie['seasons'] = [['season'=>1,'title'=>'Movie','episode_count'=>'1 Episodes','poster'=>$mData['image'] ?? '', 'episodes'=>[['label'=>'Movie '.$mData['title'],'url'=>'/movies/'.$mSlug,'thumbnail'=>$mData['image'] ?? '']]]];
                }
                if (is_array($movie)) {
                    $animeSlug = $mSlug;
                    // Use servers from detail episode entry (already 10 servers via detail movie block) - scrape one more step for toon-stream embed -> originalSrc
                    $mServers = $mData['seasons'][0]['episodes'][0]['servers'] ?? $mData['servers'] ?? [];
                    if (empty($mServers)) {
                        $mServers = api_try(function () use ($mSlug) {
                            pa_load_toonstream();
                            $http = new \ToonStreamApi\Utils\HttpClient();
                            $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
                            $detailWithSrc = $ts->detail($mSlug, 'all', false, null, 'movies');
                            return $detailWithSrc['seasons'][0]['episodes'][0]['servers'] ?? $detailWithSrc['servers'] ?? [];
                        }, []);
                    }
                    // One more step: for toon-stream.site/embed/*, fetch embed page to get originalSrc if still toon-stream
                    $mServers = api_try(function () use ($mServers) {
                        pa_load_toonstream();
                        $http = new \ToonStreamApi\Utils\HttpClient();
                        $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
                        return $ts->withOriginalSrcs($mServers);
                    }, $mServers);
                    $serversOut = [];
                    foreach ($mServers as $i=>$s) {
                        $serversOut[] = ['name'=>$s['name'] ?? 'Server '.($i+1), 'active'=>($s['isActive'] ?? $i===0), 'url'=>$s['src'] ?? $s['originalSrc'] ?? '', 'source'=>$s['originalSrc'] ?? $s['src'] ?? null, 'auto'=>false, 'cdn21'=>false];
                    }
                    if (empty($serversOut)) {
                        $serversOut = [['name'=>'Server 1','active'=>true,'url'=>'','source'=>null,'auto'=>false,'cdn21'=>false]];
                    }
                    return ['id'=>$hash,'hash'=>$hash,'accent'=>'Full Movie','title'=>$mData['title']??$hash,'animeSlug'=>$animeSlug,'backLabel'=>'Back to Movie','number'=>'Movie','rating'=>$mData['rating']??'','runtime'=>$mData['duration']??'','description'=>$mData['description']??'','player_url'=>'','player_source'=>$serversOut[0]['source']??null,'next_episode_url'=>'','prev_episode_url'=>'','download_links'=>[],'servers'=>$serversOut,'episodes'=>[],'seasons'=>$movie['seasons']??[],'recommendations'=>$movie['recommendations']??[],'animePoster'=>$movie['anime']['poster']??''];
                }
            }
            return null;
        }
        $ep = pa_toonstream_episode($data, $hash);
        if ($ep !== null) {
            // Populate OTHER EPISODES / OTHER SEASONS from series detail (was empty)
            $seriesSlug = $ep['animeSlug'] ?? '';
            if ($seriesSlug !== '') {
                $detail = api_try(function () use ($seriesSlug) {
                    pa_load_toonstream();
                    $http = new \ToonStreamApi\Utils\HttpClient();
                    $ts = new \ToonStreamApi\Providers\ToonStream($http, \ToonStreamApi\Core\Config::baseUrl());
                    return $ts->detail($seriesSlug, 'all', false, null, 'series');
                }, null);
                if (is_array($detail) && !empty($detail['seasons'])) {
                    $anime = pa_toonstream_anime_detail($detail);
                    if (is_array($anime)) {
                        $animePoster = $anime['anime']['poster'] ?? '/assets/images/placeholder.jpg';
                        $eps = [];
                        $seasonsOut = [];
                        foreach ($anime['seasons'] ?? [] as $s) {
                            foreach ($s['episodes'] ?? [] as $e) {
                                $thumb = $e['thumbnail'] ?? $animePoster;
                                if (empty($thumb) || $thumb === '/assets/images/placeholder.jpg') $thumb = $animePoster;
                                $eps[] = [
                                    'hash' => api_slug_from_url($e['url'] ?? ''),
                                    'num' => explode(' ', $e['label'] ?? '')[0] ?? '',
                                    'accent' => '',
                                    'title' => $e['label'] ?? '',
                                    'thumb' => $thumb,
                                    'active' => api_slug_from_url($e['url'] ?? '') === $hash,
                                ];
                                if (count($eps) >= 50) break;
                            }
                            $first = $s['episodes'][0]['url'] ?? '';
                            $seasonsOut[] = ['hash' => api_slug_from_url($first), 'title' => $s['title'] ?? 'Season', 'poster' => $s['poster'] ?? $animePoster];
                        }
                        // recommendations from anime detail if any
                        $recs = [];
                        foreach ($anime['recommendations'] ?? [] as $r) {
                            $recs[] = ['slug' => $r['slug'] ?? '', 'title' => $r['title'] ?? '', 'poster' => $r['poster'] ?? $animePoster];
                        }
                        $ep['episodes'] = $eps;
                        $ep['seasons'] = $seasonsOut;
                        if (!empty($recs)) $ep['recommendations'] = $recs;
                        $ep['animePoster'] = $animePoster;
                    }
                    $ep['animePoster'] ??= $ep['image'] ?? '/assets/images/placeholder.jpg';
                }
            }
        }
        if (empty($ep['animePoster'])) {
            $ep['animePoster'] = $ep['image'] ?? '/assets/images/placeholder.jpg';
        }
        return $ep;
    }

    // ── Watchhentai ─────────────────────────────────────────────────
    if ($provider === 'watchhentai') {
        // Clean hash: strip post- prefix and videos/series/movies prefixes; keep -episode-{n}-id-{id} suffix for lookup
        $epHash = preg_replace('/\?.*$/', '', $hash);
        $epHash = preg_replace('/^post-/', '', $epHash);
        $epHash = ltrim($epHash, '/');
        $epHash = preg_replace('#^(videos|episode|series|movies|3d)/#', '', $epHash);
        // Site-side success cache for this branch only: the live episode()
        // scrape is slow and can fail transiently, so only healthy responses
        // (array with servers) are ever stored.
        $whCacheKey = 'ep_wh_' . md5($epHash);
        $whCached = api_cache_get($whCacheKey, 600);
        if (is_array($whCached) && !empty($whCached['servers'])) {
            return $whCached;
        }
        $data = api_try(function () use ($epHash) {
            pa_load_watchhentai();
            $http = new \WatchHentaiApi\Utils\HttpClient();
            $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
            return $wh->episode($epHash, null);
        }, null);
        // Upstream failure skeleton (episode() catch): treat it as a miss so
        // the movie fallback below can still run for bare movie slugs, and so
        // the guards further down return null (clean site 404) for episodes.
        if (is_array($data) && ($data['failed'] ?? false) === true) {
            $data = null;
        }
        // Movies (/3d/) have no /videos/ page: episode() degrades to a
        // server-less skeleton for a bare movie slug. Fall back to the
        // movie detail (mirrors the toonstream movie fallback above).
        if ((empty($data['servers']) || !is_array($data)) && !preg_match('/-episode-\d+/i', $epHash)) {
            $mSlug = preg_replace('/^post-/', '', $epHash);
            $mData = api_try(function () use ($mSlug) {
                pa_load_watchhentai();
                $http = new \WatchHentaiApi\Utils\HttpClient();
                $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
                return $wh->detail($mSlug, 'all', false, null, 'movies');
            }, null);
            $mEp = (is_array($mData) ? ($mData['seasons'][0]['episodes'][0] ?? null) : null);
            if (is_array($mData) && !empty($mData['title']) && is_array($mEp)) {
                $mServers = $mEp['servers'] ?? [];
                $serversOut = [];
                foreach ($mServers as $i => $s) {
                    if (!is_array($s)) continue;
                    $serversOut[] = ['name' => $s['name'] ?? ('Server ' . ($i + 1)), 'active' => ($s['isActive'] ?? $i === 0), 'url' => $s['src'] ?? '', 'source' => $s['originalSrc'] ?? $s['src'] ?? null, 'sources' => $s['sources'] ?? [], 'auto' => false, 'cdn21' => false];
                }
                if (empty($serversOut)) {
                    $serversOut = [['name' => 'Server 1', 'active' => true, 'url' => '', 'source' => null, 'auto' => false, 'cdn21' => false]];
                }
                return ['id' => $hash, 'hash' => $hash, 'accent' => 'Full Movie', 'title' => $mData['title'] ?? $hash, 'animeSlug' => $mSlug, 'backLabel' => 'Back to Movie', 'number' => 'Movie', 'rating' => $mData['rating'] ?? '', 'runtime' => '', 'description' => $mData['description'] ?? '', 'player_url' => '', 'player_source' => $serversOut[0]['source'] ?? null, 'next_episode_url' => '', 'prev_episode_url' => '', 'download_links' => [], 'servers' => $serversOut, 'episodes' => [], 'seasons' => [], 'recommendations' => [], 'animePoster' => $mData['image'] ?? '/assets/images/placeholder.jpg'];
            }
        }
        if (!is_array($data) || ($data['failed'] ?? false) === true || (empty($data['title']) && empty($data['servers']))) {
            return null;
        }
        $ep = pa_watchhentai_episode($data, $hash);
        if ($ep !== null) {
            // Populate OTHER EPISODES / OTHER SEASONS from series detail (was empty)
            $seriesSlug = $ep['animeSlug'] ?? '';
            if ($seriesSlug !== '') {
                $detail = api_try(function () use ($seriesSlug) {
                    pa_load_watchhentai();
                    $http = new \WatchHentaiApi\Utils\HttpClient();
                    $wh = new \WatchHentaiApi\Providers\WatchHentai($http, \WatchHentaiApi\Core\Config::baseUrl());
                    return $wh->detail($seriesSlug, 'all', false, null, 'series');
                }, null);
                if (is_array($detail) && !empty($detail['seasons'])) {
                    $anime = pa_watchhentai_anime($detail);
                    if (is_array($anime)) {
                        $animePoster = $anime['anime']['poster'] ?? '/assets/images/placeholder.jpg';
                        $eps = [];
                        $seasonsOut = [];
                        foreach ($anime['seasons'] ?? [] as $s) {
                            foreach ($s['episodes'] ?? [] as $e) {
                                $thumb = $e['thumbnail'] ?? $animePoster;
                                if (empty($thumb) || $thumb === '/assets/images/placeholder.jpg') $thumb = $animePoster;
                                $eps[] = [
                                    'hash' => api_slug_from_url($e['url'] ?? ''),
                                    'num' => explode(' ', $e['label'] ?? '')[0] ?? '',
                                    'accent' => '',
                                    'title' => $e['label'] ?? '',
                                    'thumb' => $thumb,
                                    'active' => api_slug_from_url($e['url'] ?? '') === $hash,
                                ];
                                if (count($eps) >= 50) break;
                            }
                            $first = $s['episodes'][0]['url'] ?? '';
                            $seasonsOut[] = ['hash' => api_slug_from_url($first), 'title' => $s['title'] ?? 'Season', 'poster' => $s['poster'] ?? $animePoster];
                        }
                        // recommendations from anime detail if any
                        $recs = [];
                        foreach ($anime['recommendations'] ?? [] as $r) {
                            $recs[] = ['slug' => $r['slug'] ?? '', 'title' => $r['title'] ?? '', 'poster' => $r['poster'] ?? $animePoster];
                        }
                        $ep['episodes'] = $eps;
                        $ep['seasons'] = $seasonsOut;
                        if (!empty($recs)) $ep['recommendations'] = $recs;
                        $ep['animePoster'] = $animePoster;
                    }
                    $ep['animePoster'] ??= $ep['image'] ?? '/assets/images/placeholder.jpg';
                }
            }
        }
        if (empty($ep['animePoster'])) {
            $ep['animePoster'] = $ep['image'] ?? '/assets/images/placeholder.jpg';
        }
        // Cache only the healthy main episode path (never the movie fallback
        // and never empty/failed responses).
        if (is_array($ep) && !empty($ep['servers'])) {
            api_cache_set($whCacheKey, $ep, 600);
        }
        return $ep;
    }

    // ── Animedekho ──────────────────────────────────────────────────
    if ($provider === 'animedekho') {
        $slug = pa_slug_from_url($hash);
        $data = api_try(function () use ($slug) {
            pa_load_animedekho();
            require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
            $http = new \AnimeDekhoApi\Utils\HttpClient();
            $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
            return $ad->episode($slug, null);
        }, null);
        // Episode not found — only then try movie (servers check prevents series→movie misclass)
        if (!is_array($data) || empty($data['title']) || empty($data['servers'])) {
            $movie = api_try(function () use ($slug) {
                pa_load_animedekho();
                require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
                $http = new \AnimeDekhoApi\Utils\HttpClient();
                $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
                return $ad->movie($slug, null);
            }, null);
            if (is_array($movie) && !empty($movie['servers'])) {
                return pa_animedekho_movie($movie, $hash);
            }
        }
        if (!is_array($data) || empty($data['title']) || empty($data['servers'])) return null;
        # Series episodes must show OTHER EPISODES/SEASONS, not "Full Movie"
        $seriesUrl = $data['navigation']['seriesPage'] ?? '';
        $seriesSlug = $seriesUrl !== '' ? pa_slug_from_url($seriesUrl) : '';
        // Upstream epi pages don't always carry a breadcrumb series link
        // (e.g. liar-game-1x25 has none): fall back to the series slug
        // derived from the episode slug's trailing -SxE suffix, verified
        // against a real series detail (bare slugs without the suffix are
        // left alone, so genuine movies keep their shape).
        if ($seriesSlug === '' && preg_match('/^(.+)-\d+x\d+$/', $slug, $mm)) {
            $guessDetail = api_try(function () use ($mm) {
                pa_load_animedekho();
                require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
                $http = new \AnimeDekhoApi\Utils\HttpClient();
                $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
                return $ad->detail($mm[1], 'all', false, null, 'series');
            }, null);
            if (is_array($guessDetail) && !empty($guessDetail['seasons'])) {
                $seriesSlug = $mm[1];
                // Upstream also omits the label/title here: synthesize the
                // S/E label from the slug suffix so number/title render.
                if (empty($data['seasonEpisodeLabel']) && preg_match('/-(\d+)x(\d+)$/', $slug, $nn)) {
                    $data['seasonEpisodeLabel'] = 'S' . ((int) $nn[1]) . ' E' . ((int) $nn[2]);
                }
            }
        }
        $ep = pa_animedekho_movie($data, $hash);
        if ($seriesSlug !== '') {
            $ep['accent'] = '';
            $ep['backLabel'] = 'Back to Series';
            $ep['number'] = $data['seasonEpisodeLabel'] ?? '';
            $ep['animeSlug'] = $seriesSlug;
            # Populate OTHER EPISODES / SEASONS from series detail (like toonstream)
            $detail = api_try(function () use ($seriesSlug) {
                pa_load_animedekho();
                require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
                $http = new \AnimeDekhoApi\Utils\HttpClient();
                $ad = new \AnimeDekhoApi\Providers\AnimeDekho($http, \AnimeDekhoApi\Core\Config::baseUrl());
                return $ad->detail($seriesSlug, 'all', false, null, 'series');
            }, null);
            if (is_array($detail) && !empty($detail['seasons'])) {
                $anime = pa_animedekho_anime($detail);
                if (is_array($anime)) {
                    $animePoster = $anime['anime']['poster'] ?? '/assets/images/placeholder.jpg';
                    $eps = [];
                    foreach ($anime['seasons'] ?? [] as $ss) {
                        foreach ($ss['episodes'] ?? [] as $e) {
                            $thumb = $e['thumbnail'] ?? $e['image'] ?? $animePoster;
                            $epSlug2 = $e['slug'] ?? pa_slug_from_url($e['url'] ?? '');
                            $epNum = $e['number'] ?? $e['episodeNumber'] ?? '';
                            # animedekho uses "S2-E1" -> extract trailing number for search
                            if (preg_match('/E(\d+)/i', $epNum, $m)) $epNum = $m[1];
                            elseif (preg_match('/(\d+)/', $epNum, $m)) $epNum = $m[1];
                            $eps[] = ['hash' => $epSlug2, 'num' => $epNum, 'accent' => '', 'title' => $e['title'] ?? $e['label'] ?? "Episode $epNum", 'thumb' => $thumb ?: $animePoster, 'active' => $epSlug2 === $slug];
                            if (count($eps) >= 50) break;
                        }
                        if (count($eps) >= 50) break;
                    }
                    $seasonsOut = [];
                    foreach ($anime['seasons'] ?? [] as $ss) {
                        $first = $ss['episodes'][0]['url'] ?? '';
                        $seasonsOut[] = ['hash' => pa_slug_from_url($first), 'title' => $ss['title'] ?? 'Season', 'poster' => $ss['poster'] ?? $animePoster];
                    }
                    $ep['episodes'] = $eps;
                    $ep['seasons'] = $seasonsOut;
                    $ep['animePoster'] = $animePoster;
                    $recs = $anime['recommendations'] ?? $anime['related'] ?? [];
                    if (!empty($recs)) $ep['recommendations'] = $recs;
                    # ensure title shows episode label, not just series title
                    # (when the provider fell back to the raw slug as title,
                    # prefer the real series title from detail instead)
                    if (!empty($data['seasonEpisodeLabel'])) {
                        $epTitle = (!empty($data['title']) && $data['title'] !== $slug) ? $data['title'] : ($anime['anime']['title'] ?? $data['title']);
                        $ep['title'] = $data['seasonEpisodeLabel'] . ' — ' . $epTitle;
                    } elseif (!empty($data['title'])) $ep['title'] = $data['title'];
                }
            }
            if (empty($ep['animePoster'])) $ep['animePoster'] = $ep['image'] ?? '/assets/images/placeholder.jpg';
        }
        return $ep;
    }

    // ── Animetadka (default) ────────────────────────────────────────
    $origHash = $hash;
    // Support ?type=movie param: ?type=movie -> treat as movie detail not episode
    $reqType = $_GET['type'] ?? '';
    if ($reqType === '' && str_contains($hash, '?type=')) { [$hash,$reqType]=explode('?type=', $hash, 2); $reqType=explode('&',$reqType)[0]; }
    $hash = preg_replace('/\?.*$/', '', $hash);
    $e = api_try(fn () => api_scraper()->getEpisode($hash), null);
    if (!is_array($e) || empty($e['title'] ?? null)) {
        // Movie fallback: try getAnime for slugs like ...-movie-watch-online-download (no /ep page)
        $aMovie = api_try(fn () => api_scraper()->getAnime($hash), null);
        if (is_array($aMovie) && !empty($aMovie['title'])) {
            $typeHint = $reqType ?: (str_contains(strtolower($hash), 'movie') ? 'movie' : '');
            if ($typeHint === 'movie' || !empty($aMovie['seasons']) || $hash === $origHash) {
                $poster = $aMovie['poster'] ?? '';
                $mServers = $aMovie['servers'] ?? [];
                $serversOut = [];
                foreach ($mServers as $i=>$s) {
                    $serversOut[] = ['name'=>$s['label'] ?? $s['name'] ?? 'Server '.($i+1), 'active'=>$i===0, 'url'=>$s['url'] ?? $s['src'] ?? '', 'source'=>$s['source'] ?? $s['url'] ?? null, 'auto'=>!empty($s['auto']), 'cdn21'=>!empty($s['cdn21'])];
                }
                if (empty($serversOut)) $serversOut = [['name'=>'Server 1','active'=>true,'url'=>'','source'=>null,'auto'=>false,'cdn21'=>false]];
                $playerSrc = $serversOut[0]['source'] ?? $serversOut[0]['url'] ?? null;
                return ['id'=>$origHash,'hash'=>$origHash,'accent'=>'Full Movie','title'=>$aMovie['title'],'animeSlug'=>$hash,'backLabel'=>'Back to Movie','number'=>'Movie','rating'=>'','runtime'=>'','description'=>$aMovie['description']??'','player_url'=>'','player_source'=>$playerSrc,'next_episode_url'=>'','prev_episode_url'=>'','download_links'=>[],'servers'=>$serversOut,'episodes'=>[],'seasons'=>$aMovie['seasons']??[],'recommendations'=>$aMovie['recommendations']??[],'animePoster'=>$poster];
            }
        }
        return null;
    }

    $title = is_array($e['title'] ?? null) ? $e['title'] : [];

    // Bogus / unresolvable hash: the scraper returns an empty shape, not null.
    $hasPlayer = !empty($e['player_url'] ?? '');
    $hasServers = !empty($e['servers'] ?? []);
    $hasTitle = !empty($title['title'] ?? '');
    if (!$hasPlayer && !$hasServers && !$hasTitle) {
        return null;
    }

    // Servers
    $servers = [];
    foreach (($e['servers'] ?? []) as $i => $s) {
        if (!is_array($s)) {
            continue;
        }
        $servers[] = [
            'name'   => $s['label'] ?? ('Server ' . ($i + 1)),
            'active' => $i === 0,
            'url'    => $s['url'] ?? '',
            'source' => $s['source'] ?? null,
            'auto'   => !empty($s['auto']) || (($s['auto'] ?? '') === 'true'),
            'cdn21'  => !empty($s['cdn21']) || (($s['cdn21'] ?? '') === 'true'),
        ];
    }

    $back = is_array($e['back_link'] ?? null) ? $e['back_link'] : [];
    $animeSlug = api_slug_from_url($back['url'] ?? '');

    // Anime detail data (seasons + recommendations) — degrades to null.
    $a = [];
    if ($animeSlug !== '') {
        $a = api_try(fn () => api_scraper()->getAnime($animeSlug), null);
    }
    $a = is_array($a) ? $a : [];

    // Build season index: season_num -> [episodes...]
    // Also tracks first episode URL per season for season cards.
    $seasonIndex = [];
    $seasonMeta = [];
    foreach ($a['seasons'] ?? [] as $s) {
        if (!is_array($s)) {
            continue;
        }
        $sn = (int) ($s['season'] ?? 0);
        $eps = [];
        foreach (($s['episodes'] ?? []) as $ep) {
            if (!is_array($ep)) {
                continue;
            }
            $label = (string) ($ep['label'] ?? '');
            $num = '';
            if (preg_match('/S\d+-E(\d+)/i', $label, $m2)) {
                $num = $m2[1];
            }
            $accent = '';
            $epTitle = '';
            if (str_contains($label, '~')) {
                $pos = strpos($label, '~');
                $accent = trim(substr($label, 0, $pos + 1));
                $epTitle = trim(substr($label, $pos + 1));
            } elseif (preg_match('/S\d+-E\d+:\s*(.*)/i', $label, $m2)) {
                $epTitle = trim($m2[1]);
            } else {
                $epTitle = $label;
            }
            $epUrl = (string) ($ep['url'] ?? '');
            $eps[] = [
                'hash' => api_slug_from_url($epUrl),
                'num' => $num,
                'accent' => $accent,
                'title' => $epTitle,
                'thumb' => (string) ($ep['thumbnail'] ?? ''),
                'active' => api_slug_from_url($epUrl) === $hash,
            ];
        }
        $seasonIndex[$sn] = $eps;
        $firstEp = $eps[0]['hash'] ?? '';
        $seasonMeta[$sn] = [
            'title' => $s['title'] ?? '',
            'poster' => $s['poster'] ?? '',
            'firstEp' => $firstEp,
        ];
    }

    // Pick current season using hint from title accent.
    $seasonNum = null;
    if (preg_match('/S(\d+)-E/i', (string) ($title['accent'] ?? ''), $m)) {
        $seasonNum = (int) $m[1];
    }

    $currentSeason = $seasonIndex[$seasonNum] ?? null;
    if ($currentSeason === null) {
        foreach ($seasonIndex as $eps) {
            foreach ($eps as $ep) {
                if ($ep['active']) {
                    $currentSeason = $eps;
                    break 2;
                }
            }
        }
    }
    if ($currentSeason === null) {
        $currentSeason = !empty($seasonIndex) ? end($seasonIndex) : [];
    }
    if (!is_array($currentSeason)) {
        $currentSeason = [];
    }

    // Other episodes (current season).
    $episodes = $currentSeason;

    // Previous episode URL.
    $prev_episode_url = '';
    foreach ($currentSeason as $i => $ep) {
        if ($ep['active'] && $i > 0) {
            $prevHash = $currentSeason[$i - 1]['hash'];
            $prev_episode_url = $prevHash ? '/ep/' . $prevHash . '/' : '';
            break;
        }
    }

    // Other seasons cards.
    $seasonsOut = [];
    foreach ($seasonMeta as $sn => $meta) {
        $seasonsOut[] = [
            'hash' => $meta['firstEp'],
            'title' => $meta['title'],
            'poster' => $meta['poster'],
        ];
    }

    // Recommendations: episode page first, then anime page.
    $recoSource = !empty($e['recommendations']) ? $e['recommendations'] : ($a['recommendations'] ?? []);
    $recommendations = [];
    foreach ($recoSource as $r) {
        if (!is_array($r)) {
            continue;
        }
        $recommendations[] = [
            'slug' => api_slug_from_url($r['url'] ?? ''),
            'title' => $r['title'] ?? '',
            'poster' => $r['poster'] ?? '',
        ];
    }

    return [
        'id' => $hash,
        'hash' => $hash,
        'accent' => $title['accent'] ?? '',
        'title' => $title['title'] ?? '',
        'animeSlug' => $animeSlug,
        'backLabel' => $back['label'] ?? 'Back to Anime Details',
        'number' => '',
        'rating' => $e['rating'] ?? '',
        'runtime' => $e['runtime'] ?? '',
        'description' => $e['description'] ?? '',
        'player_url' => $e['player_url'] ?? '',
        'player_source' => $e['player_source'] ?? ($servers[0]['source'] ?? null),
        'next_episode_url' => ($nextHash = api_slug_from_url($e['next_episode_url'] ?? '')) ? '/ep/' . $nextHash . '/' : '',
        'prev_episode_url' => $prev_episode_url,
        'download_links' => $e['download_links'] ?? [],
        'servers' => $servers,
        'episodes' => $episodes,
        'seasons' => $seasonsOut,
        'recommendations' => $recommendations,
        'animeTitle' => $a['title'] ?? '',
        'animePoster' => $a['poster'] ?? '',
    ];
}

/**
 * Weekly schedule data: 7 days of anime cards.
 */
function api_schedule_data() {
    $provider = api_provider();

    // Toonstream and Anikoto don't have schedule endpoints
    if ($provider !== 'animetadka') {
        return ['days' => []];
    }

    $data = api_try(fn () => api_scraper()->getSchedule(), []);
    if (!is_array($data) || empty($data['days'])) {
        return ['days' => []];
    }
    return $data;
}
