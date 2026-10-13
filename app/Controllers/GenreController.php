<?php
/**
 * Genre listing controller — live data from the API.
 */
class GenreController {
    public function index($genre) {
        // Router gives us the raw genre slug (e.g. "action" or "sci-fi")
        $genreKey = strtolower(trim($genre, '/'));
        $filter = self::typeFilter();

        $cat = api_category_data('genre', $genreKey, 1, $filter);

        $data = [
            'title' => ucwords(str_replace('-', ' ', $genreKey)) . ' Anime - ' . Config::get('site_name'),
            'genre' => $genreKey,
            'genreLabel' => $cat['label'] ?: 'Genre Collection',
            'genreTitle' => $cat['title'] ?: (ucwords(str_replace('-', ' ', $genreKey)) . ' Animes'),
            'anime' => $cat['items'],
            'pagination' => ['current' => 1, 'total' => 1],
            'activeType' => $filter,
            'toggleBase' => '/genre/' . $genreKey,
        ];
        View::render('catalog/genre', $data, 'main');
    }

    private static function typeFilter(): string {
        $filter = strtolower(trim($_GET['type'] ?? ''));
        return in_array($filter, ['series', 'movies'], true) ? $filter : 'series';
    }
}
