<?php
/**
 * Episode / Watch page controller — live data from the API.
 */
class EpisodeController {
    public function show($id) {
        $episode = api_episode_data($id);

        if ($episode === null) {
            http_response_code(404);
            require ROOT_PATH . '/app/Views/errors/404.php';
            return;
        }

        $data = [
            'title' => 'Watch Episode - ' . Config::get('site_name'),
            'episode' => $episode,
            'servers' => $episode['servers'],
            'bodyClass' => 'single single-ep',
        ];

        View::render('watch/episode', $data, 'main');
    }

    /**
     * Lazy download resolution: the Download modal calls this with the episode
     * slug and gets back the real download links. Keeps the wrapper fetches off
     * the episode page until a visitor actually asks for a download.
     */
    public function downloads() {
        header('Content-Type: application/json; charset=utf-8');
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug === '') {
            echo json_encode(['ok' => false, 'downloads' => []]);
            return;
        }
        $downloads = api_try(fn () => api_resolve_downloads($slug), []);
        echo json_encode(['ok' => true, 'downloads' => is_array($downloads) ? $downloads : []]);
    }
}
