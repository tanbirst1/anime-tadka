<?php
/**
 * Language listing controller — live data from the API.
 */
class LanguageController {
    public function index($language) {
        $languageKey = strtolower(trim($language));
        $filter = self::typeFilter();
        $cat = api_category_data('lang', $languageKey, 1, $filter);

        $data = [
            'title' => ucfirst($language) . ' Dubbed Anime - ' . Config::get('site_name'),
            'language' => $language,
            'genreLabel' => $cat['label'] ?: 'Language Collection',
            'genreTitle' => $cat['title'] ?: ('Anime in ' . ucfirst($language)),
            'anime' => $cat['items'],
            'pagination' => ['current' => 1, 'total' => 1],
            'activeType' => $filter,
            'toggleBase' => '/lang/' . $languageKey,
        ];
        View::render('catalog/language', $data, 'main');
    }

    private static function typeFilter(): string {
        $filter = strtolower(trim($_GET['type'] ?? ''));
        return in_array($filter, ['series', 'movies'], true) ? $filter : 'series';
    }
}