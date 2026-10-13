<?php

declare(strict_types=1);

namespace AnimeTadka\Api\Controllers;

use AnimeTadka\Api\Response;

final class AnikotoController
{
    private bool $loaded = false;

    public function __construct()
    {
        $this->loadModule();
    }

    private function loadModule(): void
    {
        if ($this->loaded) {
            return;
        }
        // Mirror anikoto/index.php: suppress error display so PHP 8.5
        // curl_close() deprecations from anikoto/http.php don't corrupt JSON.
        ini_set('display_errors', '0');
        $base = dirname(__DIR__, 3) . '/anikoto';
        require_once $base . '/config.php';
        require_once $base . '/cache.php';
        require_once $base . '/http.php';
        require_once $base . '/dom.php';
        require_once $base . '/providers/anikoto.php';
        $this->loaded = true;
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     */
    public function home(array $params, array $query): Response
    {
        return $this->handle(fn () => $this->cached('HOME', fn () => anikotoGetHome()));
    }

    public function index(array $params, array $query): Response
    {
        return $this->handle(fn () => $this->cached('HOME', fn () => anikotoGetIndex()));
    }

    public function nav(array $params, array $query): Response
    {
        return $this->handle(fn () => $this->cached('NAV', fn () => ['header' => anikotoGetNavMenu('anikoto')]));
    }

    public function search(array $params, array $query): Response
    {
        $q = trim((string)($query['q'] ?? ''));
        if ($q === '') {
            return Response::error('MISSING_PARAM', 'Missing query parameter: q', 400);
        }
        $page = max(1, (int)($query['page'] ?? 1));
        $filters = array_diff_key($query, array_flip(['q', 'page', 'provider', 'path', 'nocache']));
        return $this->handle(fn () => $this->cached('SEARCH', fn () => anikotoSearch($q, $page, $filters)));
    }

    public function browse(array $params, array $query): Response
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $filters = array_diff_key($query, array_flip(['page', 'provider', 'path', 'nocache']));
        return $this->handle(fn () => $this->cached('BROWSE', fn () => anikotoBrowse($filters, $page)));
    }

    public function azlist(array $params, array $query): Response
    {
        $sort = $params['sort'] ?? 'all';
        $page = max(1, (int)($query['page'] ?? 1));
        return $this->handle(fn () => $this->cached('AZLIST', fn () => anikotoGetAzList($sort, $page)));
    }

    public function genre(array $params, array $query): Response
    {
        $name = $params['name'] ?? '';
        $page = max(1, (int)($query['page'] ?? 1));
        $sort = $query['sort'] ?? null;
        return $this->handle(fn () => $this->cached('GENRE', function () use ($name, $page, $sort) {
            $d = anikotoGetGenre($name, $page, $sort);
            return ['genreName' => $d['title'] ?? $name, 'animes' => $d['animes'], 'currentPage' => $d['currentPage'], 'totalPages' => $d['totalPages'], 'hasNextPage' => $d['hasNextPage']];
        }));
    }

    public function category(array $params, array $query): Response
    {
        $name = $params['name'] ?? '';
        $page = max(1, (int)($query['page'] ?? 1));
        $sort = $query['sort'] ?? null;
        return $this->handle(fn () => $this->cached('GENRE', function () use ($name, $page, $sort) {
            $d = anikotoGetCategory($name, $page, $sort);
            return ['category' => $d['title'] ?? $name, 'animes' => $d['animes'], 'currentPage' => $d['currentPage'], 'totalPages' => $d['totalPages'], 'hasNextPage' => $d['hasNextPage']];
        }));
    }

    public function type(array $params, array $query): Response
    {
        $name = $params['name'] ?? '';
        $page = max(1, (int)($query['page'] ?? 1));
        $sort = $query['sort'] ?? null;
        return $this->handle(fn () => $this->cached('GENRE', function () use ($name, $page, $sort) {
            $d = anikotoGetType($name, $page, $sort);
            return ['type' => $d['title'] ?? $name, 'animes' => $d['animes'], 'currentPage' => $d['currentPage'], 'totalPages' => $d['totalPages'], 'hasNextPage' => $d['hasNextPage']];
        }));
    }

    public function anime(array $params, array $query): Response
    {
        $id = $params['id'] ?? '';
        return $this->handle(fn () => $this->cached('ANIME', function () use ($id) {
            $data = anikotoGetById($id);
            // Seasons are empty from provider (both parsers hardcode []).
            // Build seasons so frontend always has data: try real episodes first, then fallback to episodesTotal.
            if (!empty($data['seasons'])) {
                return $data;
            }
            // Try to fetch real episode list to build proper season
            try {
                $epData = anikotoGetEpisodes($id);
                $eps = $epData['episodes'] ?? [];
                if (!empty($eps)) {
                    $poster = $data['anime']['poster'] ?? '';
                    $data['seasons'] = [[
                        'season' => 1,
                        'title' => 'Season 1',
                        'episode_count' => count($eps) . ' Episodes',
                        'poster' => $poster,
                        'episodes' => array_map(function ($ep) use ($poster) {
                            $num = $ep['number'] ?? '';
                            $title = $ep['title'] ?? ('Episode ' . $num);
                            return [
                                'label' => 'S1-E' . $num . ': ' . $title,
                                'url' => '',
                                'thumbnail' => !empty($ep['thumbnail'] ?? '') ? $ep['thumbnail'] : $poster,
                            ];
                        }, $eps),
                    ]];
                }
            } catch (\Throwable $e) {
                // fall through to placeholder fallback
            }
            if (empty($data['seasons'])) {
                $total = 0;
                if (!empty($data['anime']['episodesTotal'])) {
                    $total = (int) $data['anime']['episodesTotal'];
                } elseif (!empty($data['anime']['episodes']['sub'])) {
                    $total = (int) $data['anime']['episodes']['sub'];
                } elseif (!empty($data['anime']['episodes']['dub'])) {
                    $total = (int) $data['anime']['episodes']['dub'];
                }
                if ($total > 0) {
                    $poster = $data['anime']['poster'] ?? '';
                    $placeholder = [];
                    for ($i = 1; $i <= $total; $i++) {
                        $placeholder[] = [
                            'label' => 'S1-E' . $i . ': Episode ' . $i,
                            'url' => '',
                            'thumbnail' => $poster,
                        ];
                    }
                    $data['seasons'] = [[
                        'season' => 1,
                        'title' => 'Season 1',
                        'episode_count' => $total . ' Episodes',
                        'poster' => $poster,
                        'episodes' => $placeholder,
                    ]];
                }
            }
            // Synthesize multiple seasons from anikoto search (franchise entries) — same as ApiClient
            $animeName = $data['anime']['name'] ?? '';
            if (!empty($animeName) && count($data['seasons'] ?? []) === 1) {
                try {
                    $searchData = anikotoSearch($animeName);
                    if (is_array($searchData) && !empty($searchData['animes'])) {
                        $base = trim(preg_replace('/\s*\(.*\)\s*$/', '', $animeName));
                        $baseLower = mb_strtolower($base);
                        $filteredAnimes = array_values(array_filter($searchData['animes'], function($it) use ($baseLower) {
                            $n = mb_strtolower($it['name'] ?? '');
                            return $n !== '' && mb_strpos($n, $baseLower) !== false;
                        }));
                        if (empty($filteredAnimes)) $filteredAnimes = $searchData['animes'];
                        $filteredAnimes = array_slice($filteredAnimes, 0, 10);
                        $nameToSeason = [];
                        foreach ($filteredAnimes as $searchItem) {
                            $name = $searchItem['name'] ?? '';
                            if (empty($name)) continue;
                            $seasonTitle = $name;
                            if (preg_match('/^(.+?)\s+(Season \d+)$/i', $name, $m)) $seasonTitle = $name;
                            elseif (preg_match('/^(.+?)\s+\((TV|Movie|Special)\)$/i', $name, $m)) $seasonTitle = $name;
                            elseif (preg_match('/^(.+?)\s+\d+[^a-z]*Season/i', $name)) $seasonTitle = $name;
                            if (!isset($nameToSeason[$name])) {
                                $nameToSeason[$name] = [
                                    'season' => 1,
                                    'title' => $seasonTitle,
                                    'episode_count' => '0 Episodes',
                                    'poster' => $searchItem['poster'] ?? $data['anime']['poster'],
                                    'episodes' => [],
                                ];
                            }
                            $searchEpCount = 0;
                            if (!empty($searchItem['episodes']['sub'])) $searchEpCount = (int)$searchItem['episodes']['sub'];
                            elseif (!empty($searchItem['episodes']['dub'])) $searchEpCount = (int)$searchItem['episodes']['dub'];
                            if ($searchEpCount > 0 && empty($nameToSeason[$name]['episodes'])) {
                                $placeholder = [];
                                for ($i = 1; $i <= $searchEpCount; $i++) $placeholder[] = ['label' => 'S1-E' . $i . ': Episode ' . $i, 'url' => '', 'thumbnail' => $searchItem['poster'] ?? $data['anime']['poster']];
                                $nameToSeason[$name]['episode_count'] = $searchEpCount . ' Episodes';
                                $nameToSeason[$name]['episodes'] = $placeholder;
                            }
                        }
                        $searchNames = array_keys($nameToSeason);
                        if (($k = array_search($animeName, $searchNames, true)) !== false) {
                            $main = $searchNames[$k];
                            array_splice($searchNames, $k, 1);
                            array_unshift($searchNames, $main);
                        }
                        $seasonsFromSearch = [];
                        foreach ($searchNames as $n) $seasonsFromSearch[] = $nameToSeason[$n];
                        if (!empty($seasonsFromSearch)) {
                            $existing = $data['seasons'][0];
                            $hasAdditional = false;
                            foreach ($seasonsFromSearch as $s) { if ($s['episode_count'] !== $existing['episode_count'] || $s['title'] !== $existing['title']) { $hasAdditional = true; break; } }
                            if ($hasAdditional) {
                                $final = [$existing];
                                foreach ($seasonsFromSearch as $s) {
                                    $dup = false; foreach ($final as $fs) if ($fs['title'] === $s['title']) { $dup = true; break; }
                                    if (!$dup) $final[] = $s;
                                }
                                $data['seasons'] = $final;
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            // Ensure recommended/related are not empty — fallback to genre browse if provider parser found nothing
            if (empty($data['recommended']) && empty($data['related'])) {
                try {
                    $genre = $data['anime']['genres'][0] ?? null;
                    $recData = null;
                    if ($genre) {
                        try { $recData = anikotoGetGenre(strtolower($genre), 1); } catch (\Throwable $e) {}
                    }
                    if (!$recData || empty($recData['animes'])) {
                        $recData = anikotoBrowse([], 1);
                    }
                    $animes = $recData['animes'] ?? [];
                    $selfId = $data['anime']['id'] ?? '';
                    $animes = array_values(array_filter($animes, fn($a) => ($a['id'] ?? '') !== $selfId));
                    $animes = array_slice($animes, 0, 12);
                    $mapped = array_map(function($a) {
                        return [
                            'id' => $a['id'] ?? '',
                            'name' => $a['name'] ?? '',
                            'jname' => $a['jname'] ?? null,
                            'poster' => $a['poster'] ?? '',
                            'type' => $a['type'] ?? null,
                            'duration' => null,
                            'episodes' => ['sub' => null, 'dub' => null],
                            'year' => null,
                        ];
                    }, $animes);
                    if (!empty($mapped)) {
                        $data['recommended'] = $mapped;
                        $data['related'] = array_slice($mapped, 0, 6);
                    }
                } catch (\Throwable $e) {}
            } elseif (empty($data['related']) && !empty($data['recommended'])) {
                $data['related'] = array_slice($data['recommended'], 0, 6);
            } elseif (empty($data['recommended']) && !empty($data['related'])) {
                $data['recommended'] = $data['related'];
            }
            return $data;
        }));
    }

    public function episodes(array $params, array $query): Response
    {
        $id = $params['id'] ?? '';
        return $this->handle(fn () => $this->cached('EPISODES', fn () => anikotoGetEpisodes($id)));
    }

    public function episode(array $params, array $query): Response
    {
        $id = $params['id'] ?? '';
        $num = $params['num'] ?? '';
        return $this->handle(fn () => $this->cached('EPISODE', function () use ($id, $num) {
            $epData = anikotoGetEpisode($id, $num);
            // Enrich episode response with seasons + recommendations so watch page has full context
            try {
                $animeData = anikotoGetById($id);
                // Build seasons same as anime() if missing
                $seasons = $animeData['seasons'] ?? [];
                if (empty($seasons)) {
                    try {
                        $listData = anikotoGetEpisodes($id);
                        $eps = $listData['episodes'] ?? [];
                        if (!empty($eps)) {
                            $poster = $animeData['anime']['poster'] ?? '';
                            $seasons = [[
                                'season' => 1,
                                'title' => 'Season 1',
                                'episode_count' => count($eps) . ' Episodes',
                                'poster' => $poster,
                                'episodes' => array_map(function ($ep) use ($poster) {
                                    $n = $ep['number'] ?? '';
                                    $t = $ep['title'] ?? ('Episode ' . $n);
                                    return ['label' => 'S1-E' . $n . ': ' . $t, 'url' => '', 'thumbnail' => !empty($ep['thumbnail'] ?? '') ? $ep['thumbnail'] : $poster];
                                }, $eps),
                            ]];
                        }
                    } catch (\Throwable $e) {}
                    if (empty($seasons)) {
                        $total = 0;
                        if (!empty($animeData['anime']['episodesTotal'])) $total = (int)$animeData['anime']['episodesTotal'];
                        elseif (!empty($animeData['anime']['episodes']['sub'])) $total = (int)$animeData['anime']['episodes']['sub'];
                        elseif (!empty($animeData['anime']['episodes']['dub'])) $total = (int)$animeData['anime']['episodes']['dub'];
                        if ($total > 0) {
                            $poster = $animeData['anime']['poster'] ?? '';
                            $placeholder = [];
                            for ($i = 1; $i <= $total; $i++) $placeholder[] = ['label' => 'S1-E' . $i . ': Episode ' . $i, 'url' => '', 'thumbnail' => $poster];
                            $seasons = [[
                                'season' => 1,
                                'title' => 'Season 1',
                                'episode_count' => $total . ' Episodes',
                                'poster' => $poster,
                                'episodes' => $placeholder,
                            ]];
                        }
                    }
                }
                $recommended = $animeData['recommended'] ?? [];
                $related = $animeData['related'] ?? [];
                // Synthetic fallback for episode page if provider returned empty
                if (empty($recommended) && empty($related)) {
                    try {
                        $genre = $animeData['anime']['genres'][0] ?? null;
                        $recData = null;
                        if ($genre) {
                            try { $recData = anikotoGetGenre(strtolower($genre), 1); } catch (\Throwable $e) {}
                        }
                        if (!$recData || empty($recData['animes'])) {
                            $recData = anikotoBrowse([], 1);
                        }
                        $animes = $recData['animes'] ?? [];
                        $selfId = $animeData['anime']['id'] ?? '';
                        $animes = array_values(array_filter($animes, fn($a) => ($a['id'] ?? '') !== $selfId));
                        $animes = array_slice($animes, 0, 12);
                        $mapped = array_map(fn($a) => [
                            'id' => $a['id'] ?? '', 'name' => $a['name'] ?? '', 'jname' => $a['jname'] ?? null,
                            'poster' => $a['poster'] ?? '', 'type' => $a['type'] ?? null, 'duration' => null,
                            'episodes' => ['sub' => null, 'dub' => null], 'year' => null,
                        ], $animes);
                        if (!empty($mapped)) {
                            $recommended = $mapped;
                            $related = array_slice($mapped, 0, 6);
                        }
                    } catch (\Throwable $e) {}
                } elseif (empty($related) && !empty($recommended)) {
                    $related = array_slice($recommended, 0, 6);
                } elseif (empty($recommended) && !empty($related)) {
                    $recommended = $related;
                }
                $epData['seasons'] = $seasons;
                $epData['recommended'] = $recommended;
                $epData['related'] = $related;
                // Also expose anime meta for player page linking
                $epData['anime'] = $animeData['anime'] ?? null;
            } catch (\Throwable $e) {
                $epData['seasons'] = $epData['seasons'] ?? [];
                $epData['recommended'] = $epData['recommended'] ?? [];
                $epData['related'] = $epData['related'] ?? [];
            }
            return $epData;
        }));
    }

    public function cacheStats(array $params, array $query): Response
    {
        return $this->handle(fn () => cacheStats());
    }

    private function cached(string $ttlKey, callable $fn): array
    {
        $key = $_SERVER['REQUEST_URI'] ?? '/api/anikoto';
        $result = withCache($key, TTL[$ttlKey], $fn);
        return $result['data'];
    }

    private function handle(callable $fn): Response
    {
        try {
            return Response::success($fn());
        } catch (\Throwable $e) {
            return Response::error('SOURCE_ERROR', 'Failed to fetch data from the anikoto source.', 502);
        }
    }
}