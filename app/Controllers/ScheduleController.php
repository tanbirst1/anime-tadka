<?php
class ScheduleController {
    public function index() {
        $data = api_schedule_data();

        $viewData = [
            'title' => 'Schedule - ' . Config::get('site_name'),
            'days'  => $data['days'] ?? [],
        ];
        View::render('pages/schedule', $viewData, 'main');
    }
}
