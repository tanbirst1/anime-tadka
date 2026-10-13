<?php
class PageController {
    public function show($slug) {
        $data = [
            'title' => ucwords(str_replace('-', ' ', $slug)) . ' - ' . Config::get('site_name'),
            'slug' => $slug,
            'content' => '',
            'bodyClass' => 'page-' . $slug,
        ];
        View::render('utility/page', $data, 'main');
    }
}
