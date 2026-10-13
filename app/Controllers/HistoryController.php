<?php
class HistoryController {
    public function index() {
        $data = [
            'title' => 'Watch History - ' . Config::get('site_name'),
            'history' => [],
        ];
        View::render('pages/history', $data, 'main');
    }
}
