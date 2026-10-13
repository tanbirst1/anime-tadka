<?php
/**
 * Subtitle listing controller — live data from the API (/sub/* pages).
 */
class SubtitleController {
    public function index($subtitle, $page = 1) {
        $subtitleKey = strtolower(trim($subtitle));
        $page = max(1, (int) $page);
        $cat = api_category_data('sub', $subtitleKey, $page);

        $data = [
            'title' => 'Anime with ' . ucfirst($subtitle) . ' Subtitles - Watch Online - ' . Config::get('site_name'),
            'subtitle' => $subtitle,
            'genreLabel' => $cat['label'] ?: 'Subtitle Collection',
            'genreTitle' => $cat['title'] ?: ('Subtitled in ' . ucfirst($subtitle)),
            'anime' => $cat['items'],
            'pagination' => [
                'current' => $cat['pagination']['current'] ?? $page,
                'total' => $cat['pagination']['total'] ?? 1,
                'baseUrl' => '/sub/' . $subtitleKey,
            ],
        ];
        View::render('catalog/subtitle', $data, 'main');
    }
}