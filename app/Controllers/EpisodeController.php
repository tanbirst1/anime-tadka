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
}
