<?php
/**
 * Catalog Controller — unified catalog page that works with all providers.
 *
 * Routes:
 *   /catalog/series   → series listing with toggle to /catalog/movie
 *   /catalog/movie    → movie listing with toggle to /catalog/series
 *   /catalog/genre/{slug} → genre listing
 *   /catalog/season/{slug} → season listing
 */
class CatalogController {
    /**
     * Show catalog listing by type (series, movie) or category (latest-updated, most-viewed, etc.)
     */
    public function index(string $type, $page = null) {
        $typeKey = strtolower(trim($type));
        $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));

        $categories = ['latest-updated', 'new-release', 'most-viewed', 'home', 'trending'];
        $isCategory = in_array($typeKey, $categories, true);

        if ($isCategory) {
            $cat = api_category_data('category', $typeKey, $page);
        } else {
            $filter = 'series';
            $apiType = $typeKey;
            if (in_array($typeKey, ['movie', 'movies'], true)) {
                $filter = 'movies';
                $apiType = 'movie';
            } elseif ($typeKey === 'tv') {
                $filter = 'series';
                $apiType = 'tv';
            } elseif (in_array($typeKey, ['series'], true)) {
                $filter = 'series';
                $apiType = 'series';
            }

            $cat = api_category_data('type', $apiType, $page, $filter);
        }

        $data = [
            'title' => ucwords(str_replace('-', ' ', $typeKey)) . ' Animes - ' . Config::get('site_name'),
            'genreLabel' => $cat['label'] ?: 'Content Type',
            'genreTitle' => $cat['title'] ?: ucwords(str_replace('-', ' ', $typeKey)) . ' Animes',
            'anime' => $cat['items'],
            'activeType' => $isCategory ? '' : ($filter ?? ''),
            'toggleBase' => '/catalog',
            'showToggle' => false,
            'pagination' => array_merge($cat['pagination'] ?? ['current' => 1, 'total' => 1], ['baseUrl' => View::hurl('/catalog/' . $typeKey)]),
        ];

        View::render('catalog/catalog', $data, 'main');
    }

    /**
     * Show genre listing.
     */
    public function genre(string $genre, $page = null) {
        $genreKey = strtolower(trim($genre));
        $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));

        $cat = api_category_data('genre', $genreKey, $page);

        $data = [
            'title' => ucwords(str_replace('-', ' ', $genreKey)) . ' Animes - ' . Config::get('site_name'),
            'genreLabel' => $cat['label'] ?: 'Genre Collection',
            'genreTitle' => $cat['title'] ?: ucwords(str_replace('-', ' ', $genreKey)),
            'anime' => $cat['items'],
            'activeType' => '',
            'toggleBase' => '/catalog/genre/' . $genreKey,
            'showToggle' => false,
            'pagination' => array_merge($cat['pagination'] ?? ['current' => 1, 'total' => 1], ['baseUrl' => View::hurl('/catalog/genre/' . $genreKey)]),
        ];

        View::render('catalog/catalog', $data, 'main');
    }

    /**
     * Show season listing (winter, spring, summer, fall).
     */
    public function season(string $season, $page = null) {
        $seasonKey = strtolower(trim($season));
        $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));

        $cat = api_category_data('season', $seasonKey, $page);

        $data = [
            'title' => ucwords($seasonKey) . ' Season Animes - ' . Config::get('site_name'),
            'genreLabel' => $cat['label'] ?: 'Season',
            'genreTitle' => $cat['title'] ?: ucwords($seasonKey) . ' Season',
            'anime' => $cat['items'],
            'activeType' => '',
            'toggleBase' => '/catalog/season/' . $seasonKey,
            'showToggle' => false,
            'pagination' => $cat['pagination'] ?? ['current' => 1, 'total' => 1],
        ];

        View::render('catalog/catalog', $data, 'main');
    }
}
