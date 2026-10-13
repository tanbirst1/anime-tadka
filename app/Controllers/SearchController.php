<?php
class SearchController {
    public function index() {
        $query = $_GET['s'] ?? '';
        $query = trim((string) $query);

        $data = [
            'title' => ($query ? "Search: {$query}" : 'Search') . ' - ' . Config::get('site_name'),
            'query' => $query,
            'spotlight' => api_search_spotlight(),
            'latest' => $query !== '' ? api_search_items($query) : [],
            'bodyClass' => 'page-search',
        ];
        View::render('pages/search', $data, 'main');
    }
}
