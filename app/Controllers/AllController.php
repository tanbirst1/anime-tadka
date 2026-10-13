<?php
class AllController {
    public function index($page = 1) {
        $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));
        $data = api_all_items($page);

        $viewData = [
            'title' => 'All Anime - ' . Config::get('site_name'),
            'anime' => $data['items'],
            'pagination' => [
                'current' => $data['pagination']['current'] ?? $page,
                'total' => $data['pagination']['total'] ?? 1,
                'baseUrl' => View::hurl('/all'),
            ],
        ];
        View::render('pages/all', $viewData, 'main');
    }
}