<?php
class StudioController {
    public function index($studio) {
        $studioKey = strtolower(trim($studio));
        $filter = self::typeFilter();
        $cat = api_category_data('studio', $studioKey, 1, $filter);

        $data = [
            'title' => ucwords(str_replace('-', ' ', $studio)) . ' Anime - ' . Config::get('site_name'),
            'studio' => $studio,
            'genreLabel' => $cat['label'] ?: 'Browse Collection',
            'genreTitle' => $cat['title'] ?: ('Studio: <span>' . ucwords(str_replace('-', ' ', $studio)) . '</span>'),
            'anime' => $cat['items'],
            'pagination' => ['current' => 1, 'total' => 1],
            'activeType' => $filter,
            'toggleBase' => '/studio/' . $studioKey,
        ];
        View::render('catalog/studio', $data, 'main');
    }

    private static function typeFilter(): string {
        $filter = strtolower(trim($_GET['type'] ?? ''));
        return in_array($filter, ['series', 'movies'], true) ? $filter : 'series';
    }
}