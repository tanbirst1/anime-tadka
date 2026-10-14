<?php
/**
 * Home page controller — main site at /home
 * Content comes live from the bundled API (api/) — see api/docs/api.md.
 */
class HomeController {
    public function index() {
        try { $home = api_home_data(); } catch (Throwable $e) { error_log((string)$e); $home = ['spotlight'=>[],'latest'=>['all'=>[],'series'=>[],'movies'=>[]],'top'=>['day'=>[],'week'=>[],'month'=>[]]]; }

        // Spotlight limit 4-6 already enforced in adapters, but ensure here too
        $spotlight = array_slice($home['spotlight'] ?? [], 0, 6);

        // Put the "Latest episodes" row first (top of the Latest Animes block);
        // the provider's other category rows keep their original order.
        $episodes = [];
        $rest = [];
        foreach ($home['extraSections'] ?? [] as $sec) {
            if (stripos($sec['title'] ?? '', 'episode') !== false) {
                $episodes[] = $sec;
            } else {
                $rest[] = $sec;
            }
        }
        $extraSections = array_merge($episodes, $rest);

        $data = [
            'title' => Config::get('site_name') . ' - Your Ultimate Destination for Premium Regional Dubbed Anime',
            'spotlight' => $spotlight,
            'latestAll' => $home['latest']['all'] ?? [],
            'latestSeries' => $home['latest']['series'] ?? [],
            'latestMovies' => $home['latest']['movies'] ?? ($home['movies'] ?? []),
            'topDay' => $home['top']['day'] ?? [],
            'topWeek' => $home['top']['week'] ?? [],
            'topMonth' => $home['top']['month'] ?? [],
            'extraSections' => $extraSections,
            'provider' => api_provider(),
        ];

        View::render('pages/home', $data, 'main');
    }
}
