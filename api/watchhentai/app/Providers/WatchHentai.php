<?php

declare(strict_types=1);

namespace WatchHentaiApi\Providers;

use DOMElement;
use DOMNode;
use DOMXPath;
use WatchHentaiApi\Core\Config;
use WatchHentaiApi\Utils\Arr;
use WatchHentaiApi\Utils\Dom;
use WatchHentaiApi\Utils\HttpClient;
use WatchHentaiApi\Utils\Url;

/**
 * WatchHentai provider: every endpoint maps to real pages of the target site.
 *
 * Site map (verified against watchhentai.net):
 *   /                               → cards (article.item.se.episodes) + featured (#home-featured-tvshows .hero-tv-slide)
 *   /?s={q}[&paged={n}]             → search results
 *   /series/{slug}/                 → details (#single.dtsingle) + episodes (#seasons .episodios li a)
 *   /videos/{slug}-episode-{n}-id-{id}/ → player with iframe.metaframe (data-primary-player-url /player/{id}/{ep}/mp4/)
 *   /3d/                             → 3D/movies listing (article cards, ?paged=N)
 *   /3d/{slug}/                      → movie detail (#single + iframe.metaframe, single unit)
 *   /category/{path} /genre/{path}  → category / genre listings
 *
 * Every public method degrades to an empty-shape result on failure so the
 * site never 500s when the source site is unreachable.
 */
final class WatchHentai
{
    private HttpClient $http;
    private string $base;

    public function __construct(HttpClient $http, string $base)
    {
        $this->http = $http;
        $this->base = $base;
    }

    public static function cacheNamespace(string $kind): string
    {
        return $kind;
    }

    // ------------------------------------------------------------------
    // Homepage
    // ------------------------------------------------------------------

    public function home(): array
    {
        try {
            $html = $this->http->get($this->base . '/');
            $doc = Dom::load($html);
            $xp = Dom::xp($doc);

            $latestEpisodes = [];
            $latestSeries = [];
            $movies = [];
            $featured = [];

            // Main grid cards.
            foreach ($this->cardNodes($xp) as $article) {
                $card = $this->parseCard($xp, $article);
                if ($card === null) {
                    continue;
                }
                if ($card['contentType'] === 'episode') {
                    $latestEpisodes[] = $card;
                } elseif ($card['contentType'] === 'series') {
                    $latestSeries[] = $card;
                } else {
                    $movies[] = $card;
                }
            }

            // Featured hero slider.
            foreach (Dom::q($xp, "//*[@id='home-featured-tvshows']//*[contains(@class,'hero-tv-slide')]") as $slide) {
                $card = $this->parseFeatured($xp, $slide);
                if ($card !== null) {
                    $featured[] = $card;
                }
            }
            if ($featured === []) {
                $featured = array_merge($latestEpisodes, $latestSeries);
            }

            $languages = [];
            foreach (Dom::q($xp, "//a[contains(@href,'/language/') or contains(@href,'/audio/')]") as $anchor) {
                $href = Dom::href($anchor);
                if ($href === null) {
                    continue;
                }
                $text = Dom::text($anchor);
                if ($text === '') {
                    continue;
                }
                $languages[] = [
                    'name' => $text,
                    'url' => Url::absolute($this->base, $href),
                ];
            }

            return [
                'latestEpisodes' => Arr::uniqueBy($latestEpisodes, 'url'),
                'latestSeries' => Arr::uniqueBy($latestSeries, 'url'),
                'languages' => Arr::uniqueBy($languages, 'url'),
                'featured' => Arr::uniqueBy($featured, 'url'),
                'movies' => Arr::uniqueBy($movies, 'url'),
            ];
        } catch (\Throwable $e) {
            return [
                'latestEpisodes' => [],
                'latestSeries' => [],
                'languages' => [],
                'featured' => [],
                'movies' => [],
            ];
        }
    }

    /**
     * The site's index page is the homepage itself. Return it as structured
     * sections so the /index contract stays intact.
     */
    public function index(): array
    {
        try {
            $html = $this->http->get($this->base . '/');
            $doc = Dom::load($html);
            $xp = Dom::xp($doc);

            $titleNode = Dom::one($xp, '//title');
            $title = $titleNode !== null ? Dom::text($titleNode) : 'WatchHentai';

            $container = Dom::one($xp, "//*[@id='home-featured-tvshows']");
            if ($container === null) {
                return ['title' => $title, 'url' => $this->base . '/', 'intro' => [], 'sections' => []];
            }

            $intro = [];
            $sections = [];
            $current = null;
            foreach (Dom::q($xp, ".//*[self::h2 or self::h3 or self::p]", $container) as $node) {
                $text = Dom::text($node);
                if ($text === '') {
                    continue;
                }
                if ($node->nodeName === 'h2' || $node->nodeName === 'h3') {
                    $current = [
                        'heading' => $text,
                        'type' => $node->nodeName === 'h2' ? 'section' : 'subsection',
                        'paragraphs' => [],
                    ];
                    $sections[] = $current;
                } elseif ($current === null) {
                    $intro[] = $text;
                } else {
                    $sections[count($sections) - 1]['paragraphs'][] = $text;
                }
            }

            return [
                'title' => $title,
                'url' => $this->base . '/',
                'intro' => $intro,
                'sections' => $sections,
            ];
        } catch (\Throwable $e) {
            return ['title' => 'WatchHentai', 'url' => $this->base . '/', 'intro' => [], 'sections' => []];
        }
    }

    // ------------------------------------------------------------------
    // Series detail
    // ------------------------------------------------------------------

    /**
     * @param string $slug         series slug
     * @param string|null $seasons '1' (default) | 'all' | '1,2,3' | '2-4'
     * @param bool $withSources    when true, fetch each episode page and attach iframe servers
     * @param string|null $server  server filter: '0,1,2' | '0-4' | 'all' | 'primary,alternate'
     * @param string $kind         'series' (/series/{slug}/) | 'movies' (/3d/{slug}/, single unit)
     */
    public function detail(string $slug, ?string $seasons, bool $withSources, ?string $server, string $kind = 'series'): array
    {
        try {
            $slug = $this->normalizeSlug($slug);
            // Movies live under /3d/{slug}/, series under /series/{slug}/.
            $section = $kind === 'movies' ? '3d' : 'series';
            // An episode slug (…-episode-N-…) has no /series/ page — resolve it
            // through its /videos/ page to the parent series first.
            // (3D movies are single units, never episode slugs — skip this.)
            if ($kind !== 'movies' && $this->isEpisodeSlug($slug)) {
                $resolved = $this->seriesSlugForEpisode($slug);
                if ($resolved !== null) {
                    $slug = $resolved;
                }
            }
            $html = $this->http->get($this->base . '/' . $section . '/' . rawurlencode($slug) . '/');
            $doc = Dom::load($html);
            $xp = Dom::xp($doc);

            $titleNode = Dom::one($xp, "//*[@id='single']//div[contains(@class,'sheader')]//div[contains(@class,'data')]//h1");
            $titleNode ??= Dom::one($xp, "//*[@id='single']//h1");
            $titleNode ??= Dom::one($xp, '//h1');
            $title = $titleNode !== null ? Dom::text($titleNode) : $slug;

            $meta = $this->meta($xp);

            // Episodes live under #seasons .episodios li a. The /series/ page
            // does not render the list statically — fall back to the first
            // /videos/ page (\"Watch Now\" link), which carries #seasons.
            $episodes = $this->parseEpisodeList($xp, $doc, $title);
            if ($episodes === []) {
                $firstVideo = $this->firstVideoHref($xp);
                if ($firstVideo !== null) {
                    try {
                        $videoHtml = $this->http->get(Url::absolute($this->base, $firstVideo) ?? ($this->base . $firstVideo));
                        $videoDoc = Dom::load($videoHtml);
                        $episodes = $this->parseEpisodeList(Dom::xp($videoDoc), $videoDoc, $title);
                    } catch (\Throwable $e) {
                        $episodes = [];
                    }
                }
            }

            // Movies (/3d/) are single-unit players: no #seasons list exists.
            // Synthesize one "Movie" episode from the page's iframe servers
            // so detail pages get a watch link (mirrors toonstream movies).
            $movieServers = [];
            if ($kind === 'movies' && $episodes === []) {
                $movieServers = $this->filterServers($this->servers($xp), null);
                $episodes[] = [
                    'title' => $title,
                    'episodeNumber' => 'Movie',
                    'image' => $meta['image'],
                    'url' => $this->base . '/3d/' . rawurlencode($slug) . '/',
                    'slug' => $slug,
                    'servers' => $movieServers,
                ];
            }

            $availableSeasons = [];
            if ($episodes !== []) {
                $availableSeasons[] = ['seasonNumber' => 1, 'name' => 'Season 1'];
            }
            $seasonNumbers = array_column($availableSeasons, 'seasonNumber');
            if ($seasonNumbers === []) {
                $seasonNumbers = [1];
            }

            $requested = $this->parseSeasons($seasons, $seasonNumbers);

            $seasonList = [];
            foreach ($requested as $number) {
                $seasonList[] = [
                    'seasonNumber' => $number,
                    'name' => $this->seasonName($availableSeasons, $number),
                    'episodes' => $number === 1 ? $episodes : [],
                ];
            }

            // Optional: server sources per episode (parallel fetch, cached).
            if ($withSources && $episodes !== []) {
                $urls = [];
                foreach ($seasonList as $season) {
                    foreach ($season['episodes'] as $episode) {
                        $urls[] = $episode['url'];
                    }
                }
                $bodies = $this->http->getMany(array_values($urls), 6);
                foreach ($seasonList as &$season) {
                    foreach ($season['episodes'] as &$episode) {
                        $body = $bodies[$episode['url']] ?? null;
                        $episode['servers'] = $body !== null
                            ? $this->filterServers($this->serversFromHtml($body), $server)
                            : [];
                    }
                }
                unset($season, $episode);
            }

            return [
                'slug' => $slug,
                'title' => $title,
                'image' => $meta['image'],
                'rating' => $meta['rating'],
                'year' => $meta['year'],
                'duration' => $meta['duration'],
                'description' => $meta['description'],
                'totalSeasons' => count($availableSeasons),
                'totalEpisodes' => count($episodes),
                'categories' => $meta['categories'],
                'cast' => $meta['cast'],
                'tags' => $meta['tags'],
                'availableSeasons' => $availableSeasons,
                'seasons' => $seasonList,
            ];
        } catch (\Throwable $e) {
            return [
                'slug' => $slug,
                'title' => $slug,
                'image' => null,
                'rating' => null,
                'year' => null,
                'duration' => null,
                'description' => null,
                'totalSeasons' => 0,
                'totalEpisodes' => 0,
                'categories' => [],
                'cast' => [],
                'tags' => [],
                'availableSeasons' => [],
                'seasons' => [],
            ];
        }
    }

    // ------------------------------------------------------------------
    // Episode
    // ------------------------------------------------------------------

    public function episode(string $slug, ?string $server): array
    {
        try {
            $slug = $this->normalizeSlug($slug);
            // A series slug has no /videos/ page — resolve it to its first
            // episode via the series page \"Watch Now\" link.
            if (!$this->isEpisodeSlug($slug)) {
                $resolved = $this->episodeSlugForSeries($slug);
                if ($resolved !== null) {
                    $slug = $resolved;
                }
            }
            $html = $this->http->get($this->base . '/videos/' . rawurlencode($slug) . '/');
            $doc = Dom::load($html);
            $xp = Dom::xp($doc);

            // /videos/ pages have no #single wrapper — the title is a bare
            // h1.module-title inside .ep-player-info.
            $titleNode = Dom::one($xp, "//h1[contains(@class,'module-title')]");
            $titleNode ??= Dom::one($xp, "//*[@id='single']//h1");
            $titleNode ??= Dom::one($xp, '//h1');
            $title = $titleNode !== null ? Dom::text($titleNode) : $slug;
            $meta = $this->meta($xp);

            // Navigation: prev/next episode + series page under .pag_episodes.
            $previousEpisode = null;
            $nextEpisode = null;
            $seriesPage = null;
            $nav = Dom::one($xp, "//*[contains(@class,'pag_episodes')]");
            if ($nav !== null) {
                $anchors = Dom::q($xp, './/a', $nav);
                foreach ($anchors as $anchor) {
                    $href = Dom::href($anchor);
                    // Disabled pager links use href="#" — not real pages.
                    if ($href === null || $href === '' || $href === '#') {
                        continue;
                    }
                    $abs = Url::absolute($this->base, $href);
                    $cls = strtolower((string) ($anchor->getAttribute('class') ?? ''));
                    $txt = strtolower(Dom::text($anchor));
                    if (str_contains($href, '/series/')) {
                        $seriesPage = $abs;
                    } elseif (str_contains($cls, 'prev') || str_contains($txt, 'prev')) {
                        $previousEpisode = $abs;
                    } elseif (str_contains($cls, 'next') || str_contains($txt, 'next')) {
                        $nextEpisode = $abs;
                    }
                }
                // Positional fallback: [prev, series, next].
                // Skip disabled pager links (href="#" / .nonex / aria-disabled).
                if ($previousEpisode === null && $nextEpisode === null && count($anchors) >= 2) {
                    $firstHref = Dom::href($anchors[0]);
                    $lastHref = Dom::href($anchors[count($anchors) - 1]);
                    if ($this->isNavigable($anchors[0], $firstHref) && !str_contains($firstHref, '/series/')) {
                        $previousEpisode = Url::absolute($this->base, $firstHref);
                    }
                    if ($this->isNavigable($anchors[count($anchors) - 1], $lastHref) && !str_contains($lastHref, '/series/')) {
                        $nextEpisode = Url::absolute($this->base, $lastHref);
                    }
                }
            }
            if ($seriesPage === null) {
                $anchor = Dom::one($xp, "//a[contains(@href,'/series/')]");
                if ($anchor !== null) {
                    $seriesPage = Url::absolute($this->base, Dom::href($anchor));
                }
            }

            $servers = $this->filterServers($this->servers($xp), $server);

            return [
                'slug' => $slug,
                'title' => $title,
                'image' => $meta['image'],
                'description' => $meta['description'],
                'rating' => $meta['rating'],
                'year' => $meta['year'],
                'duration' => $meta['duration'],
                'categories' => $meta['categories'],
                'cast' => $meta['cast'],
                'tags' => $meta['tags'],
                'navigation' => [
                    'previousEpisode' => $previousEpisode,
                    'nextEpisode' => $nextEpisode,
                    'seriesPage' => $seriesPage,
                ],
                'servers' => $servers,
                'failed' => false,
            ];
        } catch (\Throwable $e) {
            // Failure skeleton: flag it so App::cached() skips persisting it
            // and the site side treats it as a miss (clean 404) instead of
            // rendering a page with an empty server list.
            return [
                'slug' => $slug,
                'title' => $slug,
                'image' => null,
                'description' => null,
                'rating' => null,
                'year' => null,
                'duration' => null,
                'categories' => [],
                'cast' => [],
                'tags' => [],
                'navigation' => [
                    'previousEpisode' => null,
                    'nextEpisode' => null,
                    'seriesPage' => null,
                ],
                'servers' => [],
                'failed' => true,
            ];
        }
    }

    // ------------------------------------------------------------------
    // Search & listings (category / genre / letter)
    // ------------------------------------------------------------------

    public function search(string $query, int $page = 1): array
    {
        try {
            $url = $this->base . '/?s=' . rawurlencode($query);
            if ($page > 1) {
                // Site paginates via /page/N/ path segments, not ?paged=.
                $url = $this->base . '/page/' . $page . '/?s=' . rawurlencode($query);
            }
            $html = $this->http->get($url);
            [$items, $totalPages] = $this->parseListing($html);

            $seriesCount = 0;
            foreach ($items as $item) {
                if ($item['contentType'] === 'series') {
                    $seriesCount++;
                }
            }

            return [
                'searchQuery' => $query,
                'hasResults' => $items !== [],
                'page' => $page,
                'totalPages' => $totalPages,
                'results' => $items,
            ];
        } catch (\Throwable $e) {
            return [
                'searchQuery' => $query,
                'hasResults' => false,
                'page' => $page,
                'totalPages' => 1,
                'results' => [],
            ];
        }
    }

    /**
     * Shared listing for category / genre / letter pages.
     *
     * @param bool $typeParam append the site's ?type=all filter (kept for parity; unused here)
     */
    public function listing(string $path, int $page = 1, bool $typeParam = true): array
    {
        try {
            $url = $this->base . $path;
            if ($page > 1) {
                // Site paginates via /page/N/ path segments, not ?paged=.
                // Keep any query string (?orderby=views) after the segment.
                $urlPath = $url;
                $urlQuery = '';
                if (str_contains($url, '?')) {
                    [$urlPath, $urlQuery] = explode('?', $url, 2);
                    $urlQuery = '?' . $urlQuery;
                }
                $url = rtrim($urlPath, '/') . '/page/' . $page . '/' . $urlQuery;
            }
            $html = $this->http->get($url);
            [$items, $totalPages] = $this->parseListing($html);
            return [
                'page' => $page,
                'totalPages' => $totalPages,
                'items' => $items,
            ];
        } catch (\Throwable $e) {
            return [
                'page' => $page,
                'totalPages' => 1,
                'items' => [],
            ];
        }
    }

    // ------------------------------------------------------------------
    // Extra menu data (links + taxonomy from the homepage)
    // ------------------------------------------------------------------

    /**
     * Full menu payload for the client: fixed section links, genre links
     * (/genre/…) plus language/audio links (/language/… or /audio/…) collected
     * from the homepage, and the /release/{year}/ archive years.
     * `types` and `statuses` have no source equivalent on this site and stay
     * empty arrays so consumers always receive every key.
     *
     * @return array{
     *     links: array<int, array{name: string, url: string}>,
     *     types: array<int, array{name: string, url: string}>,
     *     statuses: array<int, array{name: string, url: string}>,
     *     years: array<int, array{name: string, url: string}>,
     *     genres: array<int, array{name: string, url: string|null}>,
     *     languages: array<int, array{name: string, url: string|null}>
     * }
     */
    public function extra(): array
    {
        try {
            $links = [
                ['name' => 'Home', 'url' => $this->base . '/'],
                ['name' => 'Updated', 'url' => $this->base . '/videos/'],
                ['name' => 'Popular', 'url' => $this->base . '/series/?orderby=views'],
                ['name' => 'Genres', 'url' => $this->base . '/genre/censored/'],
            ];

            $years = [];
            for ($y = (int) date('Y'); $y >= 2010; $y--) {
                $years[] = [
                    'name' => (string) $y,
                    'url' => $this->base . '/release/' . $y . '/',
                ];
            }

            $html = $this->http->get($this->base . '/');
            $doc = Dom::load($html);
            $xp = Dom::xp($doc);

            $genres = [];
            foreach (Dom::q($xp, "//a[contains(@href,'/genre/')]") as $anchor) {
                $text = Dom::text($anchor);
                if ($text === '') {
                    continue;
                }
                $genres[] = [
                    'name' => $text,
                    'url' => Url::absolute($this->base, Dom::href($anchor)),
                ];
            }

            $languages = [];
            foreach (Dom::q($xp, "//a[contains(@href,'/language/') or contains(@href,'/audio/')]") as $anchor) {
                $text = Dom::text($anchor);
                if ($text === '') {
                    continue;
                }
                $languages[] = [
                    'name' => $text,
                    'url' => Url::absolute($this->base, Dom::href($anchor)),
                ];
            }

            return [
                'links' => $links,
                'types' => [],
                'statuses' => [],
                'years' => $years,
                'genres' => Arr::uniqueBy($genres, 'url'),
                'languages' => Arr::uniqueBy($languages, 'url'),
            ];
        } catch (\Throwable $e) {
            return [
                'links' => [],
                'types' => [],
                'statuses' => [],
                'years' => [],
                'genres' => [],
                'languages' => [],
            ];
        }
    }

    // ------------------------------------------------------------------
    // Parsers
    // ------------------------------------------------------------------

    /** @return DOMElement[] */
    private function cardNodes(DOMXPath $xp, ?DOMNode $context = null): array
    {
        // Every listing card (episodes, series, movies/3d) — never return
        // just one subgroup or latestSeries/movies buckets stay empty.
        // Scoped to <main> minus sidebars/widgets: their "popular" <article>
        // cards must not leak into listings.
        $scope = ".//main//article[contains(@class,'item')][not(ancestor::aside)][not(ancestor::*[contains(@class,'widget')])]";
        $nodes = Dom::q($xp, $scope, $context);
        if ($nodes !== []) {
            return $nodes;
        }
        return Dom::q($xp, './/main//article[not(ancestor::aside)][not(ancestor::*[contains(@class,\'widget\')])]', $context);
    }

    /**
     * Common header metadata shared by series and episode pages.
     *
     * @return array<string, mixed>
     */
    private function meta(DOMXPath $xp): array
    {
        $image = null;
        $img = Dom::one($xp, "//*[@id='single']//div[contains(@class,'sheader')]//div[contains(@class,'poster')]//img");
        $img ??= Dom::one($xp, "//*[@id='single']//div[contains(@class,'poster')]//img");
        $img ??= Dom::one($xp, "//*[contains(@class,'ep-series-card__poster')]//img");
        $img ??= Dom::one($xp, "//meta[@property='og:image']");
        if ($img !== null) {
            if ($img->nodeName === 'meta') {
                $image = Dom::attr($img, 'content');
            } else {
                $image = Dom::attr($img, 'data-src')
                    ?? Dom::attr($img, 'data-lazy')
                    ?? Dom::attr($img, 'src');
            }
        }
        // Video pages render the backdrop in a .skeleton inline style.
        if ($image === null || str_starts_with($image, 'data:')) {
            $skeleton = Dom::one($xp, "//*[contains(@class,'skeleton')]");
            $style = Dom::attr($skeleton, 'style');
            if ($style !== null && preg_match("/url\(['\"]?([^'\")]+)['\"]?\)/i", $style, $m)) {
                $image = $m[1];
            } else {
                $image = null;
            }
        }
        if ($image !== null && str_starts_with($image, 'data:')) {
            // Lazy-load placeholder, not a real image.
            $image = null;
        }

        $ratingNode = Dom::one($xp, "//*[contains(@class,'starstruck')]//*[contains(@class,'dt_rating_vgs')]");
        $ratingNode ??= Dom::one($xp, "//*[contains(@class,'starstruck')]//*[contains(@class,'rating')]");
        $ratingNode ??= Dom::one($xp, "//*[contains(@class,'dt_rating_vgs')]");
        $ratingNode ??= Dom::one($xp, "//*[contains(@class,'wextra')]//b");
        $rating = $ratingNode !== null ? Arr::firstNumber(Dom::text($ratingNode)) : null;

        $yearEl = Dom::one($xp, "//*[@id='single']//*[contains(@class,'sdate') or contains(@class,'year') or contains(@class,'date')]");
        $yearEl ??= Dom::one($xp, "//*[contains(@class,'ep-series-card')]//*[contains(@class,'year')]");
        $yearEl ??= Dom::one($xp, "//*[contains(@class,'wextra')]//*[contains(@class,'year')]");
        $year = null;
        if ($yearEl !== null) {
            $yearText = Dom::text($yearEl);
            // Dates render as "Aug. 28, 2026" — keep the 4-digit year.
            if (preg_match('/(19|20)\d{2}/', $yearText, $m)) {
                $year = $m[0];
            } elseif ($yearText !== '') {
                $year = $yearText;
            }
        }

        $durEl = Dom::one($xp, "//*[@id='single']//*[contains(@class,'duration') or contains(@class,'runtime') or contains(@class,'time')]");
        $duration = $durEl !== null ? Dom::text($durEl) : null;

        $descParts = [];
        foreach (Dom::q($xp, "//*[contains(@class,'tv-single-synopsis__body')]//p") as $p) {
            $text = Dom::text($p);
            if ($text !== '') {
                $descParts[] = $text;
            }
        }
        if ($descParts === []) {
            $descEl = Dom::one($xp, "//*[contains(@class,'tv-single-synopsis__body')]");
            if ($descEl !== null && Dom::text($descEl) !== '') {
                $descParts[] = Dom::text($descEl);
            }
        }
        if ($descParts === []) {
            // Video pages carry a truncated series synopsis card.
            $synEl = Dom::one($xp, "//p[contains(@class,'ep-series-card__synopsis')]");
            if ($synEl !== null && Dom::text($synEl) !== '') {
                $descParts[] = Dom::text($synEl);
            }
        }
        if ($descParts === []) {
            $ogDesc = Dom::one($xp, "//meta[@property='og:description']");
            $ogText = Dom::attr($ogDesc, 'content');
            if ($ogText !== null && trim($ogText) !== '') {
                $descParts[] = (string) preg_replace('/\s+/u', ' ', $ogText);
            }
        }
        $description = $descParts !== [] ? implode("\n\n", $descParts) : null;

        $categories = [];
        foreach (Dom::q($xp, "//*[contains(@class,'sgeneros')]//a") as $anchor) {
            $text = Dom::text($anchor);
            if ($text === '') {
                continue;
            }
            $categories[] = [
                'name' => $text,
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        $tags = [];
        foreach (Dom::q($xp, "//*[contains(@class,'tags')]//a | //*[@id='single']//*[contains(@class,'tag')]//a") as $anchor) {
            $text = Dom::text($anchor);
            if ($text === '') {
                continue;
            }
            $tags[] = [
                'name' => $text,
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        $cast = [];
        foreach (Dom::q($xp, "//*[@id='single']//a[contains(@href,'/cast/') or contains(@href,'/actor/') or contains(@href,'/studio/')]") as $anchor) {
            $text = Dom::text($anchor);
            if ($text === '') {
                continue;
            }
            $cast[] = [
                'name' => $text,
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        return [
            'image' => Url::absolute($this->base, $this->fullImage($image)),
            'rating' => $rating,
            'year' => $year,
            'duration' => $duration,
            'description' => $description,
            'categories' => $categories,
            'tags' => $tags,
            'cast' => $cast,
        ];
    }

    /**
     * Card art ships as small *_thumb.jpg; the full-size original lives at
     * the same path without the suffix (verified: 364px thumbs -> up to
     * 1920px originals). Restricted to watchhentai.net hosts.
     */
    private function fullImage(?string $url): ?string
    {
        if ($url === null || $url === '' || !str_contains($url, 'watchhentai.net')) {
            return $url;
        }
        $full = preg_replace('/_thumb(\.[a-zA-Z0-9]+(?:\?.*)?)$/', '$1', $url);
        return is_string($full) && $full !== '' ? $full : $url;
    }

    /**
     * A listing/search card (article.item.se.episodes with .data h3 a).
     *
     * @return array<string, mixed>|null
     */
    private function parseCard(DOMXPath $xp, DOMElement $article): ?array
    {
        $link = Dom::one($xp, ".//div[contains(@class,'data')]//h3//a", $article);
        $link ??= Dom::one($xp, ".//div[contains(@class,'data')]//a[@href]", $article);
        $link ??= Dom::one($xp, './/h3//a', $article);
        $link ??= Dom::one($xp, './/a[@href]', $article);
        $href = Dom::href($link);
        if ($href === null) {
            return null;
        }

        // Episode cards wrap <span class="serie">Title</span><h3>Episode N</h3>
        // inside one anchor with no whitespace between them — join the parts
        // explicitly so the full title keeps its separator (h3 alone would
        // give just "Episode N").
        $title = '';
        $dataAnchor = Dom::one($xp, ".//div[contains(@class,'data')]//a[@href]", $article);
        if ($dataAnchor !== null) {
            $serieEl = Dom::one($xp, ".//span[contains(@class,'serie')]", $dataAnchor);
            $headEl = Dom::one($xp, './/h3', $dataAnchor);
            $headEl ??= Dom::one($xp, './/h2', $dataAnchor);
            $serie = Dom::text($serieEl);
            $head = Dom::text($headEl);
            if ($serie !== '' && $head !== '') {
                $title = str_contains($head, $serie) || str_contains($serie, $head)
                    ? (strlen($head) >= strlen($serie) ? $head : $serie)
                    : $serie . ' ' . $head;
            } elseif (Dom::text($dataAnchor) !== '') {
                $title = Dom::text($dataAnchor);
            }
        }
        if ($title === '') {
            $titleNode = Dom::one($xp, ".//div[contains(@class,'data')]//h3", $article);
            $titleNode ??= $link;
            $title = Dom::text($titleNode);
        }
        if ($title === '') {
            return null;
        }

        $img = Dom::one($xp, ".//img[contains(@class,'ep-bd-base')]", $article);
        $img ??= Dom::one($xp, ".//div[contains(@class,'poster')]//img", $article);
        $img ??= Dom::one($xp, './/img', $article);
        $image = $img !== null
            ? (Dom::attr($img, 'data-src') ?? Dom::attr($img, 'data-lazy') ?? Dom::attr($img, 'src'))
            : null;
        if ($image !== null && str_starts_with($image, 'data:')) {
            // Lazy-load placeholder, not a real image.
            $image = null;
        }

        $vote = Dom::one($xp, ".//*[contains(@class,'vote') or contains(@class,'rating')]", $article);

        // Card year/status stamp (e.g. "2026", "Upcoming") shown on /series/.
        $yearEl = Dom::one($xp, ".//*[contains(@class,'buttonyear')]", $article);
        $year = Dom::text($yearEl);

        if (str_contains($href, '/videos/') || str_starts_with($href, 'videos/')) {
            $type = 'episode';
        } elseif (str_contains($href, '/movies') || str_contains($href, '/movie') || str_contains($href, '/3d/')) {
            $type = 'movies';
        } else {
            $type = 'series';
        }

        $card = [
            'id' => 'post-' . Url::slug($href),
            'title' => $title,
            'image' => Url::absolute($this->base, $this->fullImage($image)),
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
            'rating' => $vote !== null ? Arr::firstNumber(Dom::text($vote)) : null,
            'year' => $year !== '' ? $year : null,
            'contentType' => $type,
            'categories' => [],
            'tags' => [],
        ];

        $badge = Dom::one($xp, ".//*[contains(@class,'quality') or contains(@class,'post-ql') or contains(@class,'badge')]", $article);
        if ($badge !== null && Dom::text($badge) !== '') {
            $card['badge'] = Dom::text($badge);
        }
        // Note: no 'year' class here — the year stamp has its own field above.
        $epLabel = Dom::one($xp, ".//*[contains(@class,'episode') or contains(@class,'eptitle')]", $article);
        if ($epLabel !== null && Dom::text($epLabel) !== '') {
            $card['episode'] = Dom::text($epLabel);
        }

        return $card;
    }

    /**
     * A featured hero slide (#home-featured-tvshows .hero-tv-slide with .hero-tv-title a).
     *
     * @return array<string, mixed>|null
     */
    private function parseFeatured(DOMXPath $xp, DOMElement $slide): ?array
    {
        $link = Dom::one($xp, ".//*[contains(@class,'hero-tv-title')]//a", $slide);
        $link ??= Dom::one($xp, './/a[@href]', $slide);
        $href = Dom::href($link);
        if ($href === null) {
            return null;
        }
        $title = Dom::text($link);
        if ($title === '') {
            $heading = Dom::one($xp, ".//*[contains(@class,'hero-tv-title')]", $slide);
            $title = Dom::text($heading);
        }
        if ($title === '') {
            return null;
        }

        $img = Dom::one($xp, './/img', $slide);
        $image = $img !== null
            ? (Dom::attr($img, 'data-src') ?? Dom::attr($img, 'data-lazy') ?? Dom::attr($img, 'src'))
            : null;
        if ($image !== null && str_starts_with($image, 'data:')) {
            $image = null;
        }

        $ratingNode = Dom::one($xp, ".//*[contains(@class,'hero-tv-rating')]", $slide);
        $ratingNode ??= Dom::one($xp, ".//*[contains(@class,'vote') or contains(@class,'rating')]", $slide);
        $rating = $ratingNode !== null ? Arr::firstNumber(Dom::text($ratingNode)) : null;

        // "Censored • Creampie • Large Breasts" → category entries.
        $categories = [];
        $genresNode = Dom::one($xp, ".//*[contains(@class,'hero-tv-genres')]", $slide);
        if ($genresNode !== null) {
            foreach (preg_split('/[•|,]/u', Dom::text($genresNode)) ?: [] as $part) {
                $name = trim($part);
                if ($name !== '') {
                    $categories[] = ['name' => $name, 'url' => null];
                }
            }
        }

        return [
            'id' => 'post-' . Url::slug($href),
            'title' => $title,
            'image' => Url::absolute($this->base, $this->fullImage($image)),
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
            'rating' => $rating,
            'contentType' => 'series',
            'categories' => $categories,
            'tags' => [],
        ];
    }

    /**
     * One episode row from #seasons .episodios li a (href + .eptitle + img).
     *
     * @return array<string, mixed>|null
     */
    private function parseEpisodeItem(DOMXPath $xp, DOMElement $anchor, string $seriesTitle): ?array
    {
        $href = Dom::href($anchor);
        if ($href === null) {
            return null;
        }

        $numEl = Dom::one($xp, ".//*[contains(@class,'eptitle')]", $anchor);
        $epTitle = Dom::text($numEl);
        if ($epTitle === '') {
            $epTitle = Dom::text($anchor);
        }

        $episodeNumber = '';
        if (preg_match('/episode[-_ ]?(\d+)/i', $href, $m)) {
            $episodeNumber = $m[1];
        } elseif (preg_match('/(\d+)/', $epTitle, $m)) {
            $episodeNumber = $m[1];
        }

        $dateEl = Dom::one($xp, ".//*[contains(@class,'date')]", $anchor);
        $date = Dom::text($dateEl) !== '' ? Dom::text($dateEl) : null;

        $img = Dom::one($xp, './/img', $anchor);
        $image = $img !== null
            ? (Dom::attr($img, 'data-src') ?? Dom::attr($img, 'data-lazy') ?? Dom::attr($img, 'src'))
            : null;
        if ($image !== null && str_starts_with($image, 'data:')) {
            $image = null;
        }

        // Prefer the full "Series – Episode N" label over a bare "Episode N".
        $label = $epTitle;
        if ($seriesTitle !== '' && $epTitle !== '' && !str_contains(mb_strtolower($epTitle), mb_strtolower($seriesTitle))) {
            $label = $seriesTitle . ' – ' . $epTitle;
        }
        if ($label === '') {
            $label = $seriesTitle . ($episodeNumber !== '' ? ' Episode ' . $episodeNumber : '');
        }

        $item = [
            'title' => $label,
            'episodeNumber' => $episodeNumber !== '' ? $episodeNumber : $label,
            'image' => Url::absolute($this->base, $this->fullImage($image)),
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
        ];
        if ($date !== null) {
            $item['date'] = $date;
        }
        return $item;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseEpisodeList(DOMXPath $xp, DOMNode $context, string $seriesTitle): array
    {
        $anchors = Dom::q($xp, ".//*[@id='seasons']//*[contains(@class,'episodios')]//li//a[@href]", $context);
        if ($anchors === []) {
            $anchors = Dom::q($xp, ".//*[contains(@class,'episodios')]//a[@href]", $context);
        }
        if ($anchors === []) {
            $anchors = Dom::q($xp, ".//*[@id='seasons']//a[@href]", $context);
        }
        $episodes = [];
        foreach ($anchors as $anchor) {
            if ($anchor->nodeType !== XML_ELEMENT_NODE || !($anchor instanceof DOMElement)) {
                continue;
            }
            $episode = $this->parseEpisodeItem($xp, $anchor, $seriesTitle);
            if ($episode !== null) {
                $episodes[] = $episode;
            }
        }
        return $episodes;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: int} [items, totalPages] */
    private function parseListing(string $html): array
    {
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $items = [];
        foreach ($this->cardNodes($xp, $doc) as $article) {
            $card = $this->parseCard($xp, $article);
            if ($card !== null) {
                $items[] = $card;
            }
        }

        $totalPages = 1;
        // Primary: explicit "Page X of Y" status text.
        $statusNode = Dom::one($xp, "//*[contains(@class,'pagination__status')]");
        if ($statusNode !== null && preg_match('/of\s+(\d+)/i', Dom::text($statusNode), $mStatus)) {
            $totalPages = max($totalPages, (int) $mStatus[1]);
        }
        foreach (Dom::q($xp, "//nav[contains(@class,'pagination')]//a[contains(@class,'page-link')]") as $anchor) {
            $href = Dom::href($anchor);
            if ($href === null) {
                continue;
            }
            if (preg_match('/[?&](?:paged|page)=(\d+)/', $href, $m) === 1
                || preg_match('#/page/(\d+)/?#', $href, $m) === 1) {
                $totalPages = max($totalPages, (int) $m[1]);
            }
        }
        if ($totalPages === 1) {
            foreach (Dom::q($xp, "//*[contains(@class,'pagination')]//a[@href]") as $anchor) {
                $href = Dom::href($anchor);
                if ($href === null) {
                    continue;
                }
                if (preg_match('/[?&](?:paged|page)=(\d+)/', $href, $m) === 1
                    || preg_match('#/page/(\d+)/?#', $href, $m) === 1) {
                    $totalPages = max($totalPages, (int) $m[1]);
                }
            }
        }
        return [$items, $totalPages];
    }

    // ------------------------------------------------------------------
    // Streaming servers
    // ------------------------------------------------------------------

    /**
     * Player servers live in iframe.metaframe: data-primary-player-url
     * (/player/{id}/{ep}/mp4/) plus data-alternate-player-url.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servers(DOMXPath $xp): array
    {
        $iframe = Dom::one($xp, "//iframe[contains(@class,'metaframe')]");
        if ($iframe === null) {
            // Never fall back to a random (ad) iframe — only one that
            // actually carries a player URL.
            foreach (Dom::q($xp, '//iframe') as $candidate) {
                if (!$candidate instanceof DOMElement) {
                    continue;
                }
                $playerUrl = Dom::attr($candidate, 'data-primary-player-url')
                    ?? Dom::attr($candidate, 'data-alternate-player-url')
                    ?? Dom::attr($candidate, 'data-src')
                    ?? Dom::attr($candidate, 'src');
                if ($playerUrl !== null && str_contains($playerUrl, 'player')) {
                    $iframe = $candidate;
                    break;
                }
            }
        }
        if ($iframe === null) {
            return [];
        }

        $primary = Dom::attr($iframe, 'data-primary-player-url');
        $alternate = Dom::attr($iframe, 'data-alternate-player-url');
        $src = Dom::attr($iframe, 'data-src') ?? Dom::attr($iframe, 'src');

        $servers = [];
        $index = 0;
        $primaryAbs = Url::absolute($this->base, $primary) ?? Url::absolute($this->base, $src);
        if ($primaryAbs !== null) {
            $servers[] = [
                'serverNumber' => $index,
                'displayNumber' => $index + 1,
                'name' => 'Primary',
                'language' => 'Sub',
                'src' => $primaryAbs,
                'originalSrc' => $primaryAbs,
                'isActive' => true,
            ];
            $index++;
        }
        $alternateAbs = Url::absolute($this->base, $alternate);
        if ($alternateAbs !== null && $alternateAbs !== $primaryAbs) {
            $servers[] = [
                'serverNumber' => $index,
                'displayNumber' => $index + 1,
                'name' => 'Alternate',
                'language' => 'Sub',
                'src' => $alternateAbs,
                'originalSrc' => $alternateAbs,
                'isActive' => false,
            ];
            $index++;
        }
        $srcAbs = Url::absolute($this->base, $src);
        if ($srcAbs !== null && $srcAbs !== $primaryAbs && $srcAbs !== $alternateAbs) {
            $servers[] = [
                'serverNumber' => $index,
                'displayNumber' => $index + 1,
                'name' => 'Embed',
                'language' => 'Sub',
                'src' => $srcAbs,
                'originalSrc' => $srcAbs,
                'isActive' => false,
            ];
        }
        // One step deeper: resolve each /player/... page to its direct
        // video file so originalSrc is playable without an extra hop.
        // Player pages are fetched in parallel; failures degrade to the
        // player URL (previous behaviour).
        $playerUrls = [];
        foreach ($servers as $server) {
            $playerUrl = (string) ($server['src'] ?? '');
            if ($playerUrl !== '' && str_contains($playerUrl, '/player')) {
                $playerUrls[] = $playerUrl;
            }
        }
        $playerBodies = $playerUrls !== [] ? $this->http->getMany(array_values(array_unique($playerUrls)), 6) : [];
        // getMany has no per-URL retries: re-fetch misses sequentially
        // (full retry/backoff) so one transient failure can't kill a server.
        foreach (array_values(array_unique($playerUrls)) as $playerUrl) {
            if (isset($playerBodies[$playerUrl])) {
                continue;
            }
            try {
                $playerBodies[$playerUrl] = $this->http->get($playerUrl);
            } catch (\Throwable $e) {
                continue;
            }
        }
        foreach ($servers as &$server) {
            $playerUrl = (string) ($server['src'] ?? '');
            if ($playerUrl === '' || !str_contains($playerUrl, '/player')) {
                $server['sources'] = [];
                continue;
            }
            $resolved = $this->resolvePlayerHtml($playerBodies[$playerUrl] ?? null);
            if ($resolved === null || ($resolved['direct'] ?? null) === null) {
                $server['sources'] = $resolved['sources'] ?? [];
                continue;
            }
            $server['originalSrc'] = $resolved['direct'];
            $server['sources'] = $resolved['sources'];
        }
        unset($server);
        // Drop servers that never resolved to a playable file: offering a
        // dead server breaks playback the moment it is picked.
        $servers = array_values(array_filter($servers, static function (array $server): bool {
            $src = (string) ($server['originalSrc'] ?? $server['src'] ?? '');
            if ($src === '') {
                return false;
            }
            if (!empty($server['sources'])) {
                return true;
            }
            return !str_contains($src, '/player');
        }));
        // Renumber so server tabs and ?server=N stay positional.
        foreach ($servers as $index => &$server) {
            $server['serverNumber'] = $index;
            $server['displayNumber'] = $index + 1;
        }
        unset($server);
        return $servers;
    }

    /**
     * Fetch a /player/... page and extract its direct video sources.
     *
     * The page embeds obfuscated URLs decoded client-side by
     * whDecodeMediaUrl(): base64url → XOR((13 + i%17)&255) → reverse →
     * base64. This is the PHP port of that routine.
     *
     * @return array{direct: string|null, sources: array<int, array{label: string, file: string}>}|null
     */
    private function resolvePlayer(string $playerUrl): ?array
    {
        try {
            $html = $this->http->get($playerUrl);
        } catch (\Throwable $e) {
            return null;
        }
        return $this->resolvePlayerHtml($html);
    }

    /**
     * Extract direct video sources from an already-fetched /player/... page
     * (batch counterpart of resolvePlayer for parallel fetching).
     *
     * @return array{direct: string|null, sources: array<int, array{label: string, file: string}>}|null
     */
    private function resolvePlayerHtml(?string $html): ?array
    {
        if ($html === null || $html === '') {
            return null;
        }

        $sources = [];
        // whJwSources / Sources JSON: file+label in either order.
        if (preg_match_all('/"label"\s*:\s*"([^"]+)"\s*,\s*"file"\s*:\s*"([^"]+)"/', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $file = $this->decodePlayerUrl($row[2]) ?? $row[2];
                if (!str_starts_with($file, 'http')) {
                    continue;
                }
                $sources[] = ['label' => $row[1], 'file' => $file];
            }
        }
        if (preg_match_all('/"file"\s*:\s*"([^"]+)"[^}]{0,200}?"label"\s*:\s*"([^"]+)"/', $html, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $row) {
                $file = $this->decodePlayerUrl($row[1]) ?? $row[1];
                if (!str_starts_with($file, 'http')) {
                    continue;
                }
                $label = $row[2];
                foreach ($sources as $existing) {
                    if ($existing['file'] === $file) {
                        continue 2;
                    }
                }
                $sources[] = ['label' => $label, 'file' => $file];
            }
        }
        // Plain direct files (fallback / noscript mirrors).
        if ($sources === [] && preg_match_all('#https?://[^\s"\'<>]+\.(?:mp4|m3u8|webm)(?:\?[^\s"\'<>]*)?#i', $html, $m3)) {
            foreach (array_values(array_unique($m3[0])) as $url) {
                $sources[] = ['label' => 'direct', 'file' => $url];
            }
        }
        if ($sources === []) {
            return ['direct' => null, 'sources' => []];
        }

        // Codec sniffing (one 64KB probe per unique file, cached): HEVC
        // files don't play in Chrome/Edge/Firefox, so the default must
        // prefer an H.264 source. Unknown/failed probes stay playable.
        foreach ($sources as &$source) {
            $source['codec'] = $this->videoCodec($source['file']);
        }
        unset($source);

        // Prefer the default/highest quality among playable (non-HEVC)
        // sources; fall back to the first entry when all are HEVC/unknown.
        $direct = null;
        foreach ($sources as $source) {
            if (($source['codec'] ?? null) === 'hevc') {
                continue;
            }
            if ($direct === null || str_contains(strtolower($source['label']), '1080')) {
                $direct = $source['file'];
            }
            if (str_contains(strtolower($source['label']), '1080') && ($source['codec'] ?? null) !== 'hevc') {
                break;
            }
        }
        if ($direct === null) {
            $direct = $sources[0]['file'];
        }
        return ['direct' => $direct, 'sources' => $sources];
    }

    /**
     * Detect a direct file's video codec from its mp4 header brands.
     * Results are cached per URL for the request. Null = unknown/playable.
     */
    private function videoCodec(?string $file): ?string
    {
        static $cache = [];
        if ($file === null || $file === '' || !str_starts_with($file, 'http')) {
            return null;
        }
        if (array_key_exists($file, $cache)) {
            return $cache[$file];
        }
        $head = $this->http->fetchHead($file, 65536);
        $codec = null;
        if (is_string($head) && $head !== '') {
            if (strpos($head, 'hev1') !== false || strpos($head, 'hvc1') !== false) {
                $codec = 'hevc';
            } elseif (strpos($head, 'avc1') !== false) {
                $codec = 'h264';
            } elseif (strpos($head, 'av01') !== false || strpos($head, 'vp09') !== false) {
                $codec = 'av1';
            }
        }
        $cache[$file] = $codec;
        return $codec;
    }

    /**
     * PHP port of the player page's whDecodeMediaUrl() JS helper.
     */
    private function decodePlayerUrl(string $encoded): ?string
    {
        if ($encoded === '' || str_starts_with($encoded, 'http')) {
            return $encoded !== '' ? $encoded : null;
        }
        $s = strtr($encoded, '-_', '+/');
        while (strlen($s) % 4 !== 0) {
            $s .= '=';
        }
        $x = base64_decode($s, true);
        if ($x === false) {
            return null;
        }
        $k = 13;
        $r = '';
        $len = strlen($x);
        for ($i = 0; $i < $len; $i++) {
            $r .= chr(ord($x[$i]) ^ (($k + $i % 17) & 255));
        }
        $out = base64_decode(strrev($r), true);
        return $out === false ? null : $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function serversFromHtml(string $html): array
    {
        $doc = Dom::load($html);
        return $this->servers(Dom::xp($doc));
    }

    /**
     * Server spec: 'all' (default), numeric list '0,1,2', range '0-4',
     * or comma-separated server names 'primary,alternate'.
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<int, array<string, mixed>>
     */
    private function filterServers(array $servers, ?string $spec): array
    {
        if ($spec === null || $spec === '' || strtolower(trim($spec)) === 'all') {
            return $servers;
        }
        $names = [];
        $numbers = [];
        foreach (explode(',', $spec) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\d+-\d+$/', $part)) {
                [$from, $to] = array_map('intval', explode('-', $part, 2));
                for ($i = $from; $i <= $to; $i++) {
                    $numbers[] = $i;
                }
            } elseif (ctype_digit($part)) {
                $numbers[] = (int) $part;
            } else {
                $names[strtolower($part)] = true;
            }
        }
        $numbers = array_flip($numbers);
        return array_values(array_filter(
            $servers,
            static fn (array $s): bool => isset($numbers[$s['serverNumber']]) || isset($names[strtolower($s['name'])])
        ));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Accept a bare slug, a site subsection path (videos/…, series/…,
     * episode/…, movies/…) or a full site URL and reduce it to the bare
     * slug (last path segment, decoded, without trailing slash).
     */
    private function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $slug;
        }
        // Full URL → path.
        if (preg_match('#^https?://#i', $slug) || str_starts_with($slug, '//')) {
            $path = parse_url($slug, PHP_URL_PATH);
            $slug = is_string($path) && $path !== '' ? $path : $slug;
        }
        $slug = trim($slug, '/');
        // Strip a leading site-section prefix.
        foreach (['videos/', 'series/', 'episode/', 'episodes/', 'movies/', 'movie/', '3d/'] as $prefix) {
            if (str_starts_with(strtolower($slug), $prefix)) {
                $slug = substr($slug, strlen($prefix));
                break;
            }
        }
        $slug = trim(rawurldecode($slug), '/');
        // Nested path → last segment.
        if (str_contains($slug, '/')) {
            $slug = basename($slug);
        }
        // Bare section names are not slugs.
        if (in_array(strtolower($slug), ['videos', 'series', 'episode', 'episodes', 'movies', 'movie', '3d'], true)) {
            return '';
        }
        return $slug;
    }

    private function isEpisodeSlug(string $slug): bool
    {
        return (bool) preg_match('/-episode-\d+/i', $slug);
    }

    /**
     * Whether a pager anchor points at a real page (not a disabled "#").
     */
    private function isNavigable(DOMElement $anchor, ?string $href): bool
    {
        if ($href === null || $href === '' || $href === '#') {
            return false;
        }
        $cls = strtolower((string) ($anchor->getAttribute('class') ?? ''));
        if (str_contains($cls, 'nonex') || str_contains($cls, 'disabled')) {
            return false;
        }
        return strtolower((string) ($anchor->getAttribute('aria-disabled') ?? '')) !== 'true';
    }

    /**
     * Resolve an episode slug to its parent series slug by loading the
     * /videos/ page and reading its /series/ link. Null when unresolvable.
     */
    private function seriesSlugForEpisode(string $episodeSlug): ?string
    {
        try {
            $html = $this->http->get($this->base . '/videos/' . rawurlencode($episodeSlug) . '/');
        } catch (\Throwable $e) {
            return null;
        }
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);
        // Prefer content links (player nav, series card) over menu links.
        $anchors = Dom::q($xp, "//*[@id='single']//a[contains(@href,'/series/')]");
        if ($anchors === []) {
            $anchors = Dom::q($xp, "//a[contains(@href,'/series/')]");
        }
        foreach ($anchors as $anchor) {
            $href = Dom::href($anchor);
            if ($href === null || $href === '' || $href === '#') {
                continue;
            }
            // Skip bare section links (/series/ itself) — want a series page.
            $path = parse_url($href, PHP_URL_PATH) ?? $href;
            if (trim($path, '/') === 'series') {
                continue;
            }
            $slug = $this->normalizeSlug($href);
            if ($slug !== '' && strtolower($slug) !== 'series' && !$this->isEpisodeSlug($slug)) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * First /videos/ href on a document (the series page \"Watch Now\" link).
     */
    private function firstVideoHref(DOMXPath $xp): ?string
    {
        foreach (Dom::q($xp, "//a[contains(@href,'/videos/')]") as $anchor) {
            $href = Dom::href($anchor);
            if ($href === null || $href === '' || $href === '#') {
                continue;
            }
            // Skip bare section links (/videos/ itself) — want an episode URL.
            $path = parse_url($href, PHP_URL_PATH) ?? $href;
            if (rtrim($path, '/') === '/videos' || trim($path, '/') === 'videos') {
                continue;
            }
            return $href;
        }
        return null;
    }

    /**
     * Resolve a series slug to its first episode slug via the series page
     * \"Watch Now\" /videos/ link. Null when unresolvable.
     */
    private function episodeSlugForSeries(string $seriesSlug): ?string
    {
        try {
            $html = $this->http->get($this->base . '/series/' . rawurlencode($seriesSlug) . '/');
        } catch (\Throwable $e) {
            return null;
        }
        $doc = Dom::load($html);
        $href = $this->firstVideoHref(Dom::xp($doc));
        if ($href === null) {
            return null;
        }
        $slug = $this->normalizeSlug($href);
        return $slug !== '' ? $slug : null;
    }

    /**
     * Seasons spec: '1' (default) | 'all' | '1,2,3' | '2-4'.
     *
     * @param array<int, int> $available
     * @return array<int, int>
     */
    private function parseSeasons(?string $spec, array $available): array
    {
        if ($spec === null || trim($spec) === '') {
            $spec = '1';
        }
        if (strtolower(trim($spec)) === 'all') {
            return array_values(array_unique($available));
        }

        $wanted = [];
        foreach (explode(',', $spec) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\d+-\d+$/', $part)) {
                [$from, $to] = array_map('intval', explode('-', $part, 2));
                for ($i = $from; $i <= $to; $i++) {
                    $wanted[] = $i;
                }
            } elseif (ctype_digit($part)) {
                $wanted[] = (int) $part;
            }
        }
        $wanted = array_values(array_unique($wanted));
        sort($wanted);
        $valid = array_values(array_intersect($wanted, $available));
        return $valid !== [] ? $valid : [($available[0] ?? 1)];
    }

    /**
     * @param array<int, array{seasonNumber: int, name: string}> $available
     */
    private function seasonName(array $available, int $number): string
    {
        foreach ($available as $season) {
            if ($season['seasonNumber'] === $number) {
                return $season['name'];
            }
        }
        return 'Season ' . $number;
    }
}
