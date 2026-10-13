<?php
class TypeController {
    public function index($type) {
        $typeKey = strtolower(trim($type));
        $filter = self::typeFilter();
        $cat = api_category_data('type', $typeKey, 1, $filter);

        $data = [
            'title' => ucwords(str_replace('-', ' ', $type)) . ' Animes - ' . Config::get('site_name'),
            'genreLabel' => $cat['label'] ?: 'Content Type',
            'genreTitle' => $cat['title'] ?: (ucwords(str_replace('-', ' ', $type)) . ' Animes'),
            'anime' => $cat['items'],
            'activeType' => $filter,
            'toggleBase' => '/type/' . $typeKey,
            'showToggle' => true,
        ];
        View::render('catalog/type', $data, 'main');
    }

    private static function typeFilter(): string {
        $filter = strtolower(trim($_GET['type'] ?? ''));
        return in_array($filter, ['series', 'movies'], true) ? $filter : 'series';
    }
}