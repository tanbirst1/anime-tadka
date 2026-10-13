<?php
class SkipAdsController {
    public function index() {
        $data = [
            'title' => 'Skip Ads - ' . Config::get('site_name'),
        ];
        // Standalone page — the original has no site header/footer.
        View::render('utility/skip-ads', $data);
    }
}