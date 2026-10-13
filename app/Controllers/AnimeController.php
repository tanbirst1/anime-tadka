<?php
/**
 * Anime detail page controller — live data from the API.
 */
class AnimeController {
    public function show($slug) {
        $data = api_anime_data($slug);

        if ($data === null) {
            http_response_code(404);
            require ROOT_PATH . '/app/Views/errors/404.php';
            return;
        }

        $anime = $data['anime'];
        $episodes = $data['episodes'];

        $seasons = [];
        foreach ($data['seasons'] ?? [] as $s) {
            if (!is_array($s)) continue;
            $seasonEps = [];
            foreach ($s['episodes'] ?? [] as $ep) {
                $parts = api_episode_label_parts((string) ($ep['label'] ?? ''));
                $seasonEps[] = [
                    'slug' => api_provider() === 'anikoto' ? ($anime['slug'] ?? $slug) . '/ep/' . ($parts['number'] ?? $ep['number']) : api_slug_from_url($ep['url'] ?? ''),
                    'number' => $parts['number'],
                    'title' => $parts['title'],
                    'thumbnail' => $ep['thumbnail'] ?? '',
                    'watched' => false,
                ];
            }
            $seasons[] = [
                'season' => $s['season'] ?? null,
                'title' => $s['title'] ?? 'Season',
                'episode_count' => $s['episode_count'] ?? count($seasonEps) . ' Episodes',
                'poster' => $s['poster'] ?? $anime['poster'],
                'episodes' => $seasonEps,
            ];
        }

        $viewData = [
            'title' => $anime['title'] . ' - ' . Config::get('site_name'),
            'anime' => $anime,
            'episodes' => $episodes,
            'seasons' => $seasons,
            'breadcrumb' => [
                ['name' => 'Home', 'url' => '/home'],
                ['name' => 'Anime', 'url' => null],
            ],
        ];

        View::render('anime/detail', $viewData, 'main');
    }
}
