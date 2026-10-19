<?php
/**
 * Anivo-style fixed bottom navigation — ties the main site and the hentai
 * section together under one 5-tab bar (Movies / Series / Home / Hentai /
 * Adult). Styled with this site's own theme tokens (--accent, --bg, glass)
 * so it blends with the rest of the UI rather than copying Anivo's palette.
 *
 * Included from layouts/main.php so it is present on every main-site and
 * /hentai page. Active tab is derived from the current request path.
 */
$navPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navInHentai = str_starts_with($navPath, '/hentai') || str_starts_with($navPath, '/watchhentai');
$navIsAdult = $navInHentai && str_contains($navPath, 'uncensored');
$navIsMovies = !$navInHentai && (bool) preg_match('#^/(type|catalog|genre)/(movie|movies)#', $navPath);
$navIsSeries = !$navInHentai && (bool) preg_match('#^/(type|catalog|genre)/(tv|series|tv-series)#', $navPath);
$navIsHome = !$navInHentai && !$navIsMovies && !$navIsSeries;

$navHomeHref = $navInHentai
    ? '/hentai/home'
    : rtrim((string) Config::get('base_url'), '/') . '/home';

// Bottom-bar mark: its own `down_logo`, falling back to the header `logo`
// when unset so existing deployments keep rendering without a config change.
$navLogo = (string) Config::get('down_logo', '');
if ($navLogo === '') {
    $navLogo = (string) Config::get('logo');
}
?>
<nav class="at-bnav" aria-label="Primary">
    <a href="/catalog/movie" class="at-bnav-item<?= $navIsMovies ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        <span>Movies</span>
    </a>
    <a href="/catalog/tv" class="at-bnav-item<?= $navIsSeries ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
        <span>Series</span>
    </a>
    <a href="<?= View::escape($navHomeHref) ?>" class="at-bnav-item at-bnav-home<?= $navIsHome ? ' is-active' : '' ?>">
        <span class="at-bnav-logo">
            <img src="<?= View::escape($navLogo) ?>" alt="<?= View::escape(Config::get('site_name')) ?>">
        </span>
        <span>Home</span>
    </a>
    <a href="/hentai/home" class="at-bnav-item<?= ($navInHentai && !$navIsAdult) ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a5 5 0 0 1 5 5v3a5 5 0 0 1-10 0V7a5 5 0 0 1 5-5z"></path><path d="M4 12a8 8 0 0 0 16 0"></path><line x1="9" y1="19" x2="15" y2="19"></line><line x1="12" y1="15" x2="12" y2="22"></line></svg>
        <span>Hentai</span>
    </a>
    <a href="/hentai/catalog/genre/uncensored" class="at-bnav-item<?= $navIsAdult ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span>Adult</span>
    </a>
</nav>
