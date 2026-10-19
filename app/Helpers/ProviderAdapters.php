<?php
/**
 * Provider Adapters — normalizes Anikoto and Toonstream responses to the
 * same view contract used by Animetadka.
 *
 * Each adapter function takes raw provider data and returns the shape the
 * existing views expect.  Missing data is never invented; fields are left
 * null/empty when the provider doesn't supply them.
 *
 * Provider-specific modules are loaded lazily on first use.
 */

// ── Module loading ──────────────────────────────────────────────────

/** @var bool */
$_pa_anikoto_loaded = false;
/** @var bool */
$_pa_toonstream_loaded = false;

function pa_load_anikoto(): void {
    global $_pa_anikoto_loaded;
    if ($_pa_anikoto_loaded) return;
    $base = ROOT_PATH . '/api/anikoto';
    require_once $base . '/config.php';
    require_once $base . '/cache.php';
    require_once $base . '/http.php';
    require_once $base . '/dom.php';
    require_once $base . '/providers/anikoto.php';
    $_pa_anikoto_loaded = true;
}

function pa_load_toonstream(): void {
    global $_pa_toonstream_loaded;
    if ($_pa_toonstream_loaded) return;
    require_once ROOT_PATH . '/api/toonstream/app/autoload.php';
    $_pa_toonstream_loaded = true;
}

function pa_load_animedekho(): void {
    global $_pa_animedekho_loaded;
    if ($_pa_animedekho_loaded) return;
    require_once ROOT_PATH . '/api/animedekho/app/autoload.php';
    $_pa_animedekho_loaded = true;
}

/** @var bool */
$_pa_watchhentai_loaded = false;

function pa_load_watchhentai(): void {
    global $_pa_watchhentai_loaded;
    if ($_pa_watchhentai_loaded) return;
    require_once ROOT_PATH . '/api/watchhentai/app/autoload.php';
    $_pa_watchhentai_loaded = true;
}

// ── Helper: extract slug from a URL path ────────────────────────────

function pa_slug_from_url(string $url): string {
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    return (string) end($parts);
}

// ══════════════════════════════════════════════════════════════════════
//  ANIKOTO ADAPTERS
// ══════════════════════════════════════════════════════════════════════

/**
 * Normalize an anikoto item (from search/browse/home listings) to the
 * standard card view contract: {title, slug, episode, audio, image}.
 */
function pa_anikoto_card(array $item): array {
    $id = $item['id'] ?? '';
    $name = $item['name'] ?? ($item['jname'] ?? '');
    $ep = $item['episodes'] ?? [];
    $sub = $ep['sub'] ?? null;
    $dub = $ep['dub'] ?? null;
    $audio = [];
    if ($sub !== null) $audio[] = 'Sub';
    if ($dub !== null) $audio[] = 'Dub';
    $episode = '';
    if ($sub !== null || $dub !== null) {
        $parts = [];
        if ($sub !== null) $parts[] = $sub;
        if ($dub !== null) $parts[] = $dub;
        $episode = implode('/', $parts);
    }
    return [
        'title' => $name,
        'slug' => $id,  // anikoto uses slug-like IDs (e.g. "one-piece")
        'episode' => $episode,
        'audio' => implode(' + ', $audio),
        'image' => $item['poster'] ?? '/assets/images/placeholder.jpg',
        'href' => '/' . $id . (($item['type'] ?? '') ? '?type=' . $item['type'] : ''),
        'type' => $item['type'] ?? '',
        'slug' => $id . (($item['type'] ?? '') ? '?type=' . $item['type'] : ''),
    ];
}

/**
 * Normalize anikoto home data to the view contract:
 * {spotlight[], latest{all,series,movies}[], top{day,week,month}[]}
 */
function pa_anikoto_home(array $data): array {
    // Spotlight — limit 4-6, use title for text fallback (no logo)
    $spotlight = [];
    foreach (array_slice($data['spotlightAnimes'] ?? [], 0, 6) as $s) {
        $name = $s['name'] ?? ($s['jname'] ?? '');
        $spotlight[] = [
            'title' => $name,
            'slug' => $s['id'] ?? '',
            'image' => $s['poster'] ?? '/assets/images/placeholder.jpg',
            'logo' => '', // no logo for anikoto → use title text
            'audio' => $s['rating'] ?? '',
            'genres' => is_array($s['genres'] ?? null) ? implode(', ', $s['genres']) : ($s['genres'] ?? ''),
            'description' => $s['description'] ?? '',
        ];
    }

    // Latest episodes → cards
    $latestAll = [];
    foreach ($data['latestEpisodeAnimes'] ?? [] as $item) {
        $latestAll[] = pa_anikoto_card($item);
    }

    // Top-10
    $top = ['day' => [], 'week' => [], 'month' => []];
    $topData = $data['top10Animes'] ?? [];
    foreach (['today' => 'day', 'week' => 'week', 'month' => 'month'] as $srcKey => $destKey) {
        foreach ($topData[$srcKey] ?? [] as $t) {
            $top[$destKey][] = [
                'rank' => (int) ($t['rank'] ?? 0),
                'title' => $t['name'] ?? '',
                'slug' => $t['id'] ?? '',
                'image' => $t['poster'] ?? '',
                'rating' => '',
            ];
        }
    }

    // Extra sections for home: new releases / top upcoming as separate grids
    $extraSections = [];
    if (!empty($data['newReleases'])) {
        $items = [];
        foreach ($data['newReleases'] as $it) $items[] = pa_anikoto_card($it);
        if ($items) $extraSections[] = ['title' => 'New Releases', 'items' => $items];
    }
    if (!empty($data['topUpcomingAnimes'])) {
        $items = [];
        foreach ($data['topUpcomingAnimes'] as $it) $items[] = pa_anikoto_card($it);
        if ($items) $extraSections[] = ['title' => 'Top Upcoming', 'items' => $items];
    }

    return [
        'spotlight' => $spotlight,
        'latest' => ['all' => $latestAll, 'series' => $latestAll, 'movies' => []],
        'top' => $top,
        'extraSections' => $extraSections,
        'movies' => [],
    ];
}

/**
 * Normalize anikoto search results to card list.
 */
function pa_anikoto_search(array $data): array {
    $items = [];
    foreach ($data['animes'] ?? [] as $item) {
        $items[] = pa_anikoto_card($item);
    }
    return $items;
}

/**
 * Normalize anikoto anime detail to the view contract.
 */
function pa_anikoto_anime(array $data): ?array {
    $a = $data['anime'] ?? null;
    if (!is_array($a) || empty($a['name'])) return null;

    $genres = $a['genres'] ?? [];
    if (is_array($genres)) {
        $genres = array_map(function ($g) {
            return is_array($g) ? ($g['name'] ?? '') : (string) $g;
        }, $genres);
        $genres = array_values(array_filter($genres));
    } else {
        $genres = [];
    }

    $epCount = $a['episodesTotal'] ?? null;

    $meta = [
        'type' => $a['type'] ?? null,
        'status' => $a['status'] ?? null,
        'episodes' => $epCount !== null ? (string) $epCount : null,
        'aired' => $a['aired'] ?? null,
        'duration' => $a['duration'] ?? null,
        'quality' => null,
        'audio_languages' => null,
        'subtitles' => null,
        'studio' => !empty($a['studios']) ? implode(', ', $a['studios']) : null,
        'encode' => null,
        'rating' => $a['score'] ?? $a['rating'] ?? null,
        'views' => null,
    ];

    return [
        'anime' => [
            'title' => $a['name'] ?? '',
            'slug' => $a['id'] ?? '',
            'poster' => $a['poster'] ?? '/assets/images/placeholder.jpg',
            'image' => $a['poster'] ?? '/assets/images/placeholder.jpg',
            'type' => $meta['type'] ?? '',
            'status' => $meta['status'] ?? '',
            'episodes' => $meta['episodes'] ?? '',
            'aired' => $meta['aired'] ?? '',
            'duration' => $meta['duration'] ?? '',
            'quality' => $meta['quality'] ?? '',
            'audio' => $meta['audio_languages'] ?? '',
            'subtitle' => $meta['subtitles'] ?? '',
            'studio' => $meta['studio'] ?? '',
            'encode' => $meta['encode'] ?? '',
            'rating' => $meta['rating'] ?? '',
            'views' => $meta['views'] ?? '',
            'description' => $a['description'] ?? '',
            'genres' => $genres,
            'latest_episode' => null,
            'external_ids' => [
                'mal' => $a['malId'] ?? null,
                'tmdb' => null,
            ],
        ],
        'episodes' => [],  // episodes are fetched separately via anikotoGetEpisodes
        'seasons' => $data['seasons'] ?? [],
    ];
}

/**
 * Normalize anikoto episode data (from anikotoGetEpisodes/anikotoGetEpisode).
 */
function pa_anikoto_episodes(array $data): array {
    $episodes = [];
    foreach ($data['episodes'] ?? [] as $ep) {
        $number = $ep['number'] ?? '';
        $title = $ep['title'] ?? "Episode $number";
        $label = "S1-E$number: $title";
        $episodes[] = [
            'slug' => '', // anikoto doesn't use episode hashes; we'll handle this differently
            'number' => (string) $number,
            'title' => $title,
            'thumbnail' => '',
            'watched' => false,
        ];
    }
    return $episodes;
}

// ══════════════════════════════════════════════════════════════════════
//  TOONSTREAM ADAPTERS
// ══════════════════════════════════════════════════════════════════════

/**
 * Normalize a toonstream card (from home/search/listing) to the
 * standard card view contract: {title, slug, episode, audio, image}.
 */
function pa_toonstream_card(array $item): array {
    // Hardcode href parsing: remove base + episode/series/movies prefix
    $href = $item['url'] ?? '';
    $slug = $item['slug'] ?? '';
    $origSlug = $slug;
    if ($href !== '') {
        $path = parse_url($href, PHP_URL_PATH) ?? $href;
        $path = ltrim($path, '/');
        $path = preg_replace('#^(episode|series|movies)/#', '', $path);
        $slug = rtrim($path, '/');
        $origSlug = $slug;
    } else {
        $slug = $item['slug'] ?? pa_slug_from_url($href);
        $origSlug = $slug;
    }
    // Least anime fix: provide both ids - original with -{num}x{num} and normalized without suffix
    $normalizedSlug = preg_replace('/-\d+x\d+$/', '', $slug);
    $id = $item['id'] ?? '';
    $idNormalized = preg_replace('/-\d+x\d+$/', '', $id);
    $epLabel = $item['episode'] ?? '';
    $badge = $item['badge'] ?? '';
    $contentType = $item['contentType'] ?? '';

    // Build a display episode string from badge + episode
    $episode = trim("$badge $epLabel");

    return [
        'title' => $item['title'] ?? '',
        'slug' => $slug,
        'originalSlug' => $origSlug,
        'normalizedSlug' => $normalizedSlug,
        'id' => $id,
        'idNormalized' => $idNormalized,
        'originalId' => $item['id'] ?? '',
        'episode' => $episode,
        'audio' => $contentType === 'episode' ? 'Dub' : '',
        'image' => $item['image'] ?? '/assets/images/placeholder.jpg',
        'href' => ($contentType === 'episode' ? '/ep/' : '/') . $slug . ($contentType ? '?type=' . $contentType : ''),
        'type' => $contentType,
        'slug' => ($contentType === 'episode' ? 'ep/' : '') . $slug . ($contentType ? '?type=' . $contentType : ''),
    ];
}

/**
 * Normalize toonstream home data to the view contract.
 */
function pa_toonstream_home(array $data): array {
    // Spotlight: use featured cards — limit 4-6, use title text (no logo)
    $spotlight = [];
    foreach (array_slice($data['featured'] ?? [], 0, 6) as $f) {
        $slug = pa_slug_from_url($f['url'] ?? '');
        $title = $f['title'] ?? '';
        $spotlight[] = [
            'title' => $title,
            'slug' => $slug,
            'image' => $f['image'] ?? '/assets/images/placeholder.jpg',
            'logo' => '', // no logo → use title
            'audio' => $f['badge'] ?? '',
            'genres' => '',
            'description' => $f['title'] ?? '',
        ];
    }

    // Latest: separate episodes vs series vs movies
    $latestEpisodes = [];
    foreach (($data['latestEpisodes'] ?? []) as $item) {
        $latestEpisodes[] = pa_toonstream_card($item);
    }
    $latestSeries = [];
    foreach (($data['latestSeries'] ?? []) as $item) {
        $latestSeries[] = pa_toonstream_card($item);
    }
    $movies = [];
    foreach (($data['movies'] ?? []) as $item) {
        $movies[] = pa_toonstream_card($item);
    }
    // For "All" keep episodes + series
    $latestAll = array_merge($latestEpisodes, $latestSeries);

    // Extra sections for home
    $extraSections = [];
    if ($movies) $extraSections[] = ['title' => 'Movies', 'items' => $movies];
    if ($latestSeries) $extraSections[] = ['title' => 'Latest Series', 'items' => array_slice($latestSeries, 0, 12)];
    if ($latestEpisodes) $extraSections[] = ['title' => 'Latest Episodes', 'items' => array_slice($latestEpisodes, 0, 12)];

    $top = ['day' => [], 'week' => [], 'month' => []];

    return [
        'spotlight' => $spotlight,
        'latest' => ['all' => $latestAll, 'series' => $latestSeries, 'movies' => $movies],
        'top' => $top,
        'extraSections' => $extraSections,
        'movies' => $movies,
    ];
}

/**
 * Normalize toonstream search results to card list.
 */
function pa_toonstream_search(array $data): array {
    $items = [];
    foreach ($data['results'] ?? [] as $item) {
        $items[] = pa_toonstream_card($item);
    }
    return $items;
}

/**
 * Normalize toonstream series detail to the anime detail view contract.
 */
function pa_toonstream_anime_detail(array $data): ?array {
    if (empty($data['title'])) return null;

    $genres = [];
    foreach ($data['categories'] ?? [] as $cat) {
        if (is_array($cat) && !empty($cat['name'])) {
            $genres[] = $cat['name'];
        } elseif (is_string($cat)) {
            $genres[] = $cat;
        }
    }

    // Flatten seasons → episodes for the view
    $allEpisodes = [];
    foreach ($data['seasons'] ?? [] as $season) {
        foreach ($season['episodes'] ?? [] as $ep) {
            $epSlug = $ep['slug'] ?? pa_slug_from_url($ep['url'] ?? '');
            $epNum = $ep['episodeNumber'] ?? '';
            $allEpisodes[] = [
                'slug' => $epSlug,
                'number' => $epNum,
                'title' => $ep['title'] ?? '',
                'thumbnail' => $ep['image'] ?? '',
                'watched' => false,
            ];
        }
    }

    // Movies (single playable unit): ToonStream returns one fake episode with
    // episodeNumber "Movie" and the page player. Ensure at least 1 episode so
    // the detail view shows "1/1 Episodes" instead of "0/0 Episodes".
    $isMovie = ($data['contentType'] ?? '') === 'movies' || (isset($data['slug']) && stripos((string)$data['slug'], 'movie') !== false);
    if (($data['totalEpisodes'] ?? 0) === 0 && $isMovie && empty($allEpisodes)) {
        $allEpisodes[] = [
            'slug' => $data['slug'] ?? '',
            'number' => 'Movie',
            'title' => $data['title'] ?? '',
            'thumbnail' => $data['image'] ?? '',
            'watched' => false,
        ];
    }

    // Build seasons array for the view
    $seasons = [];
    foreach ($data['seasons'] ?? [] as $season) {
        $seasonEps = [];
        foreach ($season['episodes'] ?? [] as $ep) {
            $seasonEps[] = [
                'label' => ($ep['episodeNumber'] ?? '') . ' ' . ($ep['title'] ?? ''),
                'url' => $ep['url'] ?? '',
                'thumbnail' => $ep['image'] ?? '',
            ];
        }
        $seasons[] = [
            'season' => $season['seasonNumber'] ?? null,
            'title' => $season['name'] ?? 'Season',
            'episode_count' => count($seasonEps) . ' Episodes',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'episodes' => $seasonEps,
        ];
    }

    // Build the latest_episode from first episode in first season
    $latestEp = null;
    if (!empty($allEpisodes)) {
        $first = $allEpisodes[0];
        $latestEp = [
            'slug' => $first['slug'],
            'label' => $first['title'] ?: ('Episode ' . $first['number']),
        ];
    }

    return [
        'anime' => [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'image' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'type' => '',
            'status' => '',
            'episodes' => (string) max(1, (int) ($data['totalEpisodes'] ?? count($allEpisodes))),
            'aired' => $data['year'] ?? '',
            'duration' => $data['duration'] ?? '',
            'quality' => '',
            'audio' => '',
            'subtitle' => '',
            'studio' => '',
            'encode' => '',
            'rating' => $data['rating'] ?? '',
            'views' => '',
            'description' => $data['description'] ?? '',
            'genres' => $genres,
            'latest_episode' => $latestEp,
            'external_ids' => ['mal' => null, 'tmdb' => null],
        ],
        'episodes' => $allEpisodes,
        'seasons' => $seasons,
        'recommendations' => $data['recommendations'] ?? $data['related'] ?? [],
    ];
}

/**
 * Normalize toonstream episode data to the watch page view contract.
 */
function pa_toonstream_episode(array $data, string $hash): ?array {
    if (empty($data['title']) && empty($data['servers'])) return null;

    $navigation = $data['navigation'] ?? [];
    $prevUrl = $navigation['previousEpisode'] ?? '';
    $nextUrl = $navigation['nextEpisode'] ?? '';
    $seriesUrl = $navigation['seriesPage'] ?? '';

    // Normalize servers to the view contract
    $servers = [];
    foreach ($data['servers'] ?? [] as $i => $s) {
        $servers[] = [
            'name'   => $s['name'] ?? ('Server ' . ($i + 1)),
            'active' => ($s['isActive'] ?? false),
            'url'    => $s['src'] ?? '',
            'source' => $s['originalSrc'] ?? $s['src'] ?? null,
            'auto'   => false,
            'cdn21'  => false,
        ];
    }

    // Player source: prefer originalSrc, then src
    $playerSource = null;
    if (!empty($servers)) {
        $first = reset($servers);
        $playerSource = $first['source'] ?? null;
    }

    $animeSlug = pa_slug_from_url($seriesUrl);

        return [
            'id' => $hash,
            'hash' => $hash,
            'accent' => '',
            'title' => $data['title'] ?? '',
            'animeSlug' => $animeSlug,
            'backLabel' => 'Back to Series',
            'number' => '',
            'rating' => $data['rating'] ?? '',
            'runtime' => $data['duration'] ?? '',
            'description' => $data['description'] ?? '',
            'player_url' => '',
            'player_source' => $playerSource,
            'next_episode_url' => $nextUrl !== '' ? '/ep/' . pa_slug_from_url($nextUrl) . '/' : '',
            'prev_episode_url' => $prevUrl !== '' ? '/ep/' . pa_slug_from_url($prevUrl) . '/' : '',
            'download_links' => [],
            'servers' => $servers,
            'episodes' => [],
            'seasons' => [],
            'recommendations' => $data['recommendations'] ?? $data['related'] ?? [],
            'animePoster' => '',
        ];
}

// ══════════════════════════════════════════════════════════════════════
//  ANIMEDEKHO ADAPTERS
// ══════════════════════════════════════════════════════════════════════

/**
 * Normalize an animedekho card to the standard card view contract.
 */
function pa_animedekho_card(array $item): array {
    $slug = $item['slug'] ?? pa_slug_from_url($item['url'] ?? '');
    $origSlug = $slug;
    $normalizedSlug = preg_replace('/-\d+x\d+$/', '', $slug);
    if ($normalizedSlug === '' || $normalizedSlug === null) $normalizedSlug = $slug;
    $id = $item['id'] ?? '';
    $idNormalized = preg_replace('/-\d+x\d+$/', '', (string) $id);
    $epLabel = $item['episodeLabel'] ?? $item['episode'] ?? '';
    $quality = $item['quality'] ?? '';

    $episode = trim("$quality $epLabel");

    return [
        'title' => $item['title'] ?? '',
        'slug' => $slug,
        'originalSlug' => $origSlug,
        'normalizedSlug' => $normalizedSlug,
        'id' => $id,
        'idNormalized' => $idNormalized,
        'originalId' => $item['id'] ?? '',
        'episode' => $episode,
        'audio' => (string) ($item['audio'] ?? ''),
        'image' => $item['image'] ?? '/assets/images/placeholder.jpg',
        'href' => $item['url'] ?? '',
        'type' => $item['contentType'] ?? ($item['type'] ?? ''),
    ];
}

/**
 * Normalize animedekho anime (series) detail to the view contract.
 */
function pa_animedekho_anime(array $data): ?array {
    $title = $data['title'] ?? '';
    if ($title === '') return null;

    $genres = [];
    foreach ($data['genres'] ?? [] as $g) {
        $name = is_array($g) ? ($g['name'] ?? '') : (string) $g;
        if ($name !== '') $genres[] = $name;
    }

    $allEpisodes = [];
    $seasons = [];
    foreach ($data['seasons'] ?? [] as $season) {
        $seasonEps = [];
        foreach ($season['episodes'] ?? [] as $ep) {
            $epSlug = $ep['slug'] ?? pa_slug_from_url($ep['url'] ?? '');
            $epNum = $ep['label'] ?? ($ep['number'] ?? '');
            $allEpisodes[] = [
                'slug' => $epSlug,
                'number' => $epNum,
                'title' => $ep['title'] ?? '',
                'thumbnail' => $ep['thumbnail'] ?? '',
                'watched' => false,
            ];
            $seasonEps[] = [
                'label' => trim(($ep['label'] ?? '') . ' ' . ($ep['title'] ?? '')),
                'url' => $ep['url'] ?? '',
                'thumbnail' => $ep['thumbnail'] ?? '',
            ];
        }
        $seasons[] = [
            'season' => $season['seasonNumber'] ?? null,
            'title' => $season['name'] ?? 'Season',
            'episode_count' => count($seasonEps) . ' Episodes',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'episodes' => $seasonEps,
        ];
    }

    // Movies / single-playable unit: provider returns no seasons. Only
    // synthesize a movie when data is actually a movie (explicit type or
    // slug hints). Otherwise keep seasons empty so series parsing failures
    // don't masquerade as "Full Movie" (was treating every empty seasons as movie).
    $isMovieType = ($data['contentType'] ?? '') === 'movie' || ($data['type'] ?? '') === 'movie'
        || (isset($data['slug']) && str_contains((string)$data['slug'], 'movie'))
        || (isset($data['url']) && str_contains((string)$data['url'], '/movie'))
        || (($data['totalEpisodes'] ?? null) === 1 && empty($data['seasons']));
    if (empty($seasons) && $isMovieType) {
        $slug = $data['slug'] ?? '';
        $epUrl = $slug !== '' ? '/' . $slug . '/' : '';
        $movieEp = [
            'slug' => $slug,
            'number' => 'Movie',
            'title' => $title,
            'thumbnail' => $data['image'] ?? '',
            'watched' => false,
        ];
        $allEpisodes[] = $movieEp;
        $seasons[] = [
            'season' => 1,
            'title' => 'Movie',
            'episode_count' => '1 Episodes',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'episodes' => [[
                'label' => 'Movie ' . $title,
                'url' => $epUrl,
                'thumbnail' => $data['image'] ?? '',
            ]],
        ];
    }

    $latestEp = null;
    if (!empty($allEpisodes)) {
        $first = $allEpisodes[0];
        $latestEp = [
            'slug' => $first['slug'],
            'label' => $first['title'] ?: ('Episode ' . $first['number']),
        ];
    }

    return [
        'anime' => [
            'title' => $title,
            'slug' => $data['slug'] ?? '',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'image' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'type' => '',
            'status' => '',
            'episodes' => (string) max(1, (int) ($data['totalEpisodes'] ?? count($allEpisodes))),
            'aired' => $data['year'] ?? '',
            'duration' => $data['duration'] ?? '',
            'quality' => '',
            'audio' => '',
            'subtitle' => '',
            'studio' => '',
            'encode' => '',
            'rating' => $data['rating'] ?? '',
            'views' => '',
            'description' => $data['description'] ?? '',
            'genres' => $genres,
            'latest_episode' => $latestEp,
            'external_ids' => ['mal' => null, 'tmdb' => null],
        ],
        'episodes' => $allEpisodes,
        'seasons' => $seasons,
        'recommendations' => $data['recommendations'] ?? $data['related'] ?? [],
    ];
}

/**
 * Normalize animedekho movie detail to the watch view contract.
 * Movies always have exactly 1 playable episode (the movie itself).
 */
function pa_animedekho_movie(array $data, string $slug): ?array {
    $streamServers = [];
    $downloadServers = [];
    foreach ($data['servers'] ?? [] as $i => $s) {
        if (!is_array($s)) continue;
        // A REST row may be an embed (streamable) and/or a download. Twin
        // rows are emitted twice (one show_embed, one show_download), so read
        // the two flags independently: otherwise download-only rows such as
        // GDFlix/Hubcloud leak into the streaming tab list.
        $showEmbed = array_key_exists('showEmbed', $s) ? (bool) $s['showEmbed'] : (($s['kind'] ?? 'embed') !== 'download');
        $showDownload = array_key_exists('showDownload', $s) ? (bool) $s['showDownload'] : (($s['download'] ?? '') !== '');
        $source = $s['originalSrc'] ?? $s['src'] ?? ($s['source'] ?? null);
        if ($showEmbed) {
            $streamServers[] = [
                'name'   => $s['name'] ?? ('Server ' . ($i + 1)),
                'active' => count($streamServers) === 0,
                'url'    => $s['src'] ?? ($s['url'] ?? ''),
                // The direct player URL is only resolved for the active server
                // up front (see AnimeDekho::withActiveOriginalSrc). The rest are
                // resolved lazily by the tab-switch probe, so mark whether this
                // row still holds the raw wrapper.
                'source' => $source,
                'resolved' => !empty($s['originalSrc']),
                'auto'   => false,
                'cdn21'  => false,
            ];
        }
        if ($showDownload) {
            // Only the raw download.php|download2.php endpoint is known here.
            // Resolving it to the real file (file.php / external mirror) is
            // deferred to the Download modal so it costs nothing until asked.
            $downloadServers[] = [
                'name'    => $s['name'] ?? ('Download ' . ($i + 1)),
                'url'     => $s['downloadSrc'] ?? '',
                'raw'     => $s['download'] ?? '',
                'lazy'    => true,
                'quality' => $s['quality'] ?? '',
                'language' => $s['language'] ?? '',
            ];
        }
    }
    $servers = $streamServers;

    $playerSource = '';
    if (!empty($servers)) {
        $playerSource = $servers[0]['source'] ?? ($servers[0]['url'] ?? '');
    }

    return [
        'id' => $slug,
        'hash' => $slug,
        'accent' => 'Full Movie',
        'title' => $data['title'] ?? '',
        'animeSlug' => $slug,
        'backLabel' => 'Back to Movie',
        'number' => 'Movie',
        'rating' => $data['rating'] ?? '',
        'runtime' => $data['duration'] ?? '',
        'description' => $data['description'] ?? '',
        'player_url' => '',
        'player_source' => $playerSource,
        'next_episode_url' => '',
        'prev_episode_url' => '',
        'download_links' => [],
        'download_servers' => $downloadServers,
        'servers' => $servers,
        'episodes' => [],
        'seasons' => [],
        'recommendations' => [],
    ];
}

// ══════════════════════════════════════════════════════════════════════
//  WATCHHENTAI ADAPTERS
// ══════════════════════════════════════════════════════════════════════

/**
 * Normalize a watchhentai card (from home/search/listing) to the
 * standard card view contract: {title, slug, episode, audio, image}.
 */
function pa_watchhentai_card(array $item): array {
    // Hardcode href parsing: remove base + videos/series/movies/3d prefix
    $href = $item['url'] ?? '';
    $slug = $item['slug'] ?? '';
    $origSlug = $slug;
    if ($href !== '') {
        $path = parse_url($href, PHP_URL_PATH) ?? $href;
        $path = ltrim($path, '/');
        $path = preg_replace('#^(videos|episode|series|movies|3d)/#', '', $path);
        $slug = rtrim($path, '/');
        $origSlug = $slug;
    } else {
        $slug = $item['slug'] ?? pa_slug_from_url($href);
        $origSlug = $slug;
    }
    $normalizedSlug = preg_replace('/-episode-\d+-id-\d+$/', '', $slug);
    if ($normalizedSlug === '' || $normalizedSlug === null) $normalizedSlug = $slug;
    $id = $item['id'] ?? '';
    $idNormalized = preg_replace('/-episode-\d+-id-\d+$/', '', $id);
    $epLabel = $item['episode'] ?? '';
    $badge = $item['badge'] ?? '';
    $contentType = $item['contentType'] ?? '';

    // Build a display episode string from badge + episode
    $episode = trim("$badge $epLabel");

    return [
        'title' => $item['title'] ?? '',
        'originalSlug' => $origSlug,
        'normalizedSlug' => $normalizedSlug,
        'id' => $id,
        'idNormalized' => $idNormalized,
        'originalId' => $item['id'] ?? '',
        'episode' => $episode,
        'year' => $item['year'] ?? '',
        'audio' => 'Sub',
        'image' => $item['image'] ?? '/assets/images/placeholder.jpg',
        'href' => ($contentType === 'episode' ? '/ep/' : '/') . $slug . ($contentType ? '?type=' . $contentType : ''),
        'type' => $contentType,
        'slug' => ($contentType === 'episode' ? 'ep/' : '') . $slug . ($contentType ? '?type=' . $contentType : ''),
    ];
}

/**
 * Normalize watchhentai home data to the view contract.
 */
function pa_watchhentai_home(array $data): array {
    // Spotlight: use featured cards — limit 4-6, use title text (no logo)
    $spotlight = [];
    foreach (array_slice($data['featured'] ?? [], 0, 6) as $f) {
        $slug = pa_slug_from_url($f['url'] ?? '');
        $title = $f['title'] ?? '';
        $spotlight[] = [
            'title' => $title,
            'slug' => $slug,
            'image' => $f['image'] ?? '/assets/images/placeholder.jpg',
            'logo' => '', // no logo → use title
            'audio' => $f['badge'] ?? '',
            'genres' => '',
            'description' => $f['title'] ?? '',
        ];
    }

    // Latest: separate episodes vs series vs movies
    $latestEpisodes = [];
    foreach (($data['latestEpisodes'] ?? []) as $item) {
        $latestEpisodes[] = pa_watchhentai_card($item);
    }
    $latestSeries = [];
    foreach (($data['latestSeries'] ?? []) as $item) {
        $latestSeries[] = pa_watchhentai_card($item);
    }
    $movies = [];
    foreach (($data['movies'] ?? []) as $item) {
        $movies[] = pa_watchhentai_card($item);
    }
    // For "All" keep episodes + series
    $latestAll = array_merge($latestEpisodes, $latestSeries);

    // Extra sections use the source site's original section names.
    // The episodes section carries a landscape layout flag so the view
    // can render 16:9 cards instead of the default portrait grid.
    $extraSections = [];
    if ($latestEpisodes) $extraSections[] = ['title' => 'Recent Episodes', 'layout' => 'episodes-landscape', 'items' => array_slice($latestEpisodes, 0, 12)];
    if ($latestSeries) $extraSections[] = ['title' => 'Hentai Series', 'items' => array_slice($latestSeries, 0, 12)];

    $top = ['day' => [], 'week' => [], 'month' => []];

    return [
        'spotlight' => $spotlight,
        'latest' => ['all' => $latestAll, 'series' => $latestSeries, 'movies' => []],
        'top' => $top,
        'extraSections' => $extraSections,
        'movies' => [],
    ];
}

/**
 * Normalize watchhentai search results to card list.
 */
function pa_watchhentai_search(array $data): array {
    $items = [];
    foreach ($data['results'] ?? [] as $item) {
        $items[] = pa_watchhentai_card($item);
    }
    return $items;
}

/**
 * Normalize watchhentai series detail to the anime detail view contract.
 */
function pa_watchhentai_anime(array $data): ?array {
    if (empty($data['title'])) return null;

    $genres = [];
    foreach ($data['categories'] ?? [] as $cat) {
        if (is_array($cat) && !empty($cat['name'])) {
            $genres[] = $cat['name'];
        } elseif (is_string($cat)) {
            $genres[] = $cat;
        }
    }

    // Flatten seasons → episodes for the view
    $allEpisodes = [];
    foreach ($data['seasons'] ?? [] as $season) {
        foreach ($season['episodes'] ?? [] as $ep) {
            $epSlug = $ep['slug'] ?? pa_slug_from_url($ep['url'] ?? '');
            $epNum = $ep['episodeNumber'] ?? '';
            $allEpisodes[] = [
                'slug' => $epSlug,
                'number' => $epNum,
                'title' => $ep['title'] ?? '',
                'thumbnail' => $ep['image'] ?? '',
                'watched' => false,
            ];
        }
    }

    // Build seasons array for the view
    $seasons = [];
    foreach ($data['seasons'] ?? [] as $season) {
        $seasonEps = [];
        foreach ($season['episodes'] ?? [] as $ep) {
            $seasonEps[] = [
                'label' => ($ep['episodeNumber'] ?? '') . ' ' . ($ep['title'] ?? ''),
                'url' => $ep['url'] ?? '',
                'thumbnail' => $ep['image'] ?? '',
            ];
        }
        $seasons[] = [
            'season' => $season['seasonNumber'] ?? null,
            'title' => $season['name'] ?? 'Season',
            'episode_count' => count($seasonEps) . ' Episodes',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'episodes' => $seasonEps,
        ];
    }

    // Build the latest_episode from first episode in first season
    $latestEp = null;
    if (!empty($allEpisodes)) {
        $first = $allEpisodes[0];
        $latestEp = [
            'slug' => $first['slug'],
            'label' => $first['title'] ?: ('Episode ' . $first['number']),
        ];
    }

    return [
        'anime' => [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'poster' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'image' => $data['image'] ?? '/assets/images/placeholder.jpg',
            'type' => '',
            'status' => '',
            'episodes' => (string) max(1, (int) ($data['totalEpisodes'] ?? count($allEpisodes))),
            'aired' => $data['year'] ?? '',
            'duration' => $data['duration'] ?? '',
            'quality' => '',
            'audio' => '',
            'subtitle' => '',
            'studio' => '',
            'encode' => '',
            'rating' => $data['rating'] ?? '',
            'views' => '',
            'description' => $data['description'] ?? '',
            'genres' => $genres,
            'latest_episode' => $latestEp,
            'external_ids' => ['mal' => null, 'tmdb' => null],
        ],
        'episodes' => $allEpisodes,
        'seasons' => $seasons,
        'recommendations' => $data['recommendations'] ?? $data['related'] ?? [],
    ];
}

/**
 * Normalize watchhentai episode data to the watch page view contract.
 */
function pa_watchhentai_episode(array $data, string $hash): ?array {
    if (empty($data['title']) && empty($data['servers'])) return null;

    $navigation = $data['navigation'] ?? [];
    $prevUrl = $navigation['previousEpisode'] ?? '';
    $nextUrl = $navigation['nextEpisode'] ?? '';
    $seriesUrl = $navigation['seriesPage'] ?? '';

    // Normalize servers to the view contract
    $servers = [];
    foreach ($data['servers'] ?? [] as $i => $s) {
        $servers[] = [
            'name'   => $s['name'] ?? ('Server ' . ($i + 1)),
            'active' => ($s['isActive'] ?? ($i === 0)),
            'url'    => $s['src'] ?? '',
            'source' => $s['originalSrc'] ?? $s['src'] ?? null,
            'sources' => $s['sources'] ?? [],
            'auto'   => false,
            'cdn21'  => false,
        ];
    }

    // Player source: prefer originalSrc, then src
    $playerSource = null;
    if (!empty($servers)) {
        $first = reset($servers);
        $playerSource = $first['source'] ?? null;
    }

    $animeSlug = pa_slug_from_url($seriesUrl);

        return [
            'id' => $hash,
            'hash' => $hash,
            'accent' => '',
            'title' => $data['title'] ?? '',
            'animeSlug' => $animeSlug,
            'backLabel' => 'Back to Series',
            'number' => '',
            'rating' => $data['rating'] ?? '',
            'runtime' => $data['duration'] ?? '',
            'description' => $data['description'] ?? '',
            'player_url' => '',
            'player_source' => $playerSource,
            'next_episode_url' => $nextUrl !== '' ? '/ep/' . pa_slug_from_url($nextUrl) . '/' : '',
            'prev_episode_url' => $prevUrl !== '' ? '/ep/' . pa_slug_from_url($prevUrl) . '/' : '',
            'download_links' => [],
            'servers' => $servers,
            'episodes' => [],
            'seasons' => [],
            'recommendations' => $data['recommendations'] ?? $data['related'] ?? [],
            'animePoster' => '',
        ];
}
