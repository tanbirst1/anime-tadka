<?php
/**
 * Hentai section controller — watchhentai-powered pages under /hentai/
 * (also reachable via /watchhentai/home). The main site provider is
 * untouched; this controller temporarily switches provider + link base
 * and reuses the standard controllers/views.
 *
 * Routes (see routes/web.php):
 *   /hentai, /hentai/home, /watchhentai/home → home
 *   /hentai/all, /hentai/all/page/{page}     → all
 *   /hentai/ep/{id}                          → episode (watch)
 *   /hentai/{slug}                           → series detail
 */
class HentaiController {
    /**
     * Run $fn with watchhentai as the active provider and '/hentai' as
     * the link base (View::hurl prefixes content links, api_* uses the
     * watchhentai backend, sidebar menu shows hentai genres).
     */
    private function section(callable $fn) {
        $prevProvider = Config::get('provider', 'animetadka');
        $prevBase = $GLOBALS['__link_base'] ?? '';
        Config::set('provider', 'watchhentai');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/hentai', PHP_URL_PATH) ?: '/hentai';
        // Mirror the prefix the visitor is on: /hentai/* or /watchhentai/*.
        $GLOBALS['__link_base'] = str_starts_with($path, '/watchhentai') ? '/watchhentai' : '/hentai';
        api_provider_reset();
        try {
            return $fn();
        } finally {
            Config::set('provider', $prevProvider);
            $GLOBALS['__link_base'] = $prevBase;
            api_provider_reset();
        }
    }

    public function home() {
        $this->section(function () {
            (new HomeController())->index();
        });
    }

    public function all($page = 1) {
        $page = max(1, (int) $page);
        $this->section(function () use ($page) {
            (new AllController())->index($page);
        });
    }

    public function series($slug) {
        $this->section(function () use ($slug) {
            (new AnimeController())->show($slug);
        });
    }

    public function episode($id) {
        $this->section(function () use ($id) {
            (new EpisodeController())->show($id);
        });
    }

    public function catalog($type, $page = null) {
        $this->section(function () use ($type, $page) {
            (new CatalogController())->index($type, $page);
        });
    }

    public function genre($genre, $page = null) {
        $this->section(function () use ($genre, $page) {
            (new CatalogController())->genre($genre, $page);
        });
    }

    public function letter($letter, $page = null) {
        $this->section(function () use ($letter, $page) {
            (new LetterController())->index($letter, $page, false);
        });
    }

    public function year($year, $page = null) {
        $this->section(function () use ($year, $page) {
            $yearKey = trim((string) $year);
            $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));
            $cat = api_category_data('year', $yearKey, $page);
            View::render('catalog/catalog', [
                'title' => $yearKey . ' Anime - ' . Config::get('site_name'),
                'genreLabel' => 'Recently Added',
                'genreTitle' => $cat['title'] ?: $yearKey,
                'anime' => $cat['items'],
                'activeType' => '',
                'toggleBase' => '/hentai/year/' . $yearKey,
                'showToggle' => false,
                'pagination' => array_merge($cat['pagination'] ?? ['current' => 1, 'total' => 1], ['baseUrl' => '/hentai/year/' . $yearKey]),
            ], 'main');
        });
    }
}
