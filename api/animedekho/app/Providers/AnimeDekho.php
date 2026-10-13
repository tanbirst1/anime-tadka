<?php

declare(strict_types=1);

namespace AnimeDekhoApi\Providers;

use DOMElement;
use DOMXPath;
use AnimeDekhoApi\Core\ScrapeException;
use AnimeDekhoApi\Utils\Arr;
use AnimeDekhoApi\Utils\Dom;
use AnimeDekhoApi\Utils\HttpClient;
use AnimeDekhoApi\Utils\Url;

/**
 * AnimeDekho provider: every endpoint maps to real pages of the target site.
 *
 * Site map (verified against animedekho.app — WordPress + toronites theme):
 *   /home/                        → content grid sections + schedule
 *   /                             → static landing page
 *   /?s={q}                       → search results (pagination /page/N/?s=q)
 *   /series-hindi/{slug}/         → details + all seasons/episodes (server-rendered)
 *   /movie-hindi/{slug}/          → movie detail (same header layout)
 *   /epi/{slug}/                  → player page; ad-gated until the verify URL
 *                                   sets the toronites_server cookie (~24h)
 *   /movies-hindi/, /series-hindi/→ movie / series listings
 *   /category/{path}/             → category listings
 *   /cast/{name}/                 → cast listings
 *   /letter/{letter}/             → alphabetical listings
 *   /embed/{postId}/{s}-{e}       → VidStream wrapper around the real player
 */
final class AnimeDekho
{
    private HttpClient $http;
    private string $base;

    public function __construct(HttpClient $http, string $base)
    {
        $this->http = $http;
        $this->base = $base;
    }

    // ------------------------------------------------------------------
    // Homepage & landing
    // ------------------------------------------------------------------

    /**
     * The real content grid lives at /home/ (the root is a static landing
     * article). Cards are bucketed under their nearest preceding section
     * title by walking the document in order.
     */
    public function home(): array
    {
        $html = $this->http->get($this->base . '/home/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $sections = [];
        $current = null;
        // Walk section titles and cards in document order and bucket cards
        // under the last seen title — robust against unknown wrapper markup.
        foreach (Dom::q($xp, "//*[contains(@class,'section-title') or (self::article and contains(@class,'post'))]") as $node) {
            if ($node->nodeName === 'article' || str_contains($node->getAttribute('class'), 'post')) {
                if ($current === null) {
                    continue;
                }
                $card = $this->parseCard($xp, $node);
                if ($card !== null) {
                    $sections[$current]['items'][] = $card;
                }
                continue;
            }
            $title = Dom::text($node);
            if ($title === '' || str_contains($title, 'Watch History')) {
                continue;
            }
            if (!isset($sections[$title])) {
                $sections[$title] = ['title' => $title, 'items' => []];
            }
            $current = $title;
        }

        $schedule = [];
        foreach (Dom::q($xp, "//*[contains(@class,'schedule-day')]") as $day) {
            $labelNode = Dom::one($xp, ".//*[contains(@class,'day')]", $day);
            $shows = [];
            foreach (Dom::q($xp, ".//li//a", $day) as $anchor) {
                $name = Dom::text($anchor);
                if ($name === '') {
                    continue;
                }
                $shows[] = [
                    'name' => $name,
                    'url' => Url::absolute($this->base, Dom::href($anchor)),
                ];
            }
            if ($shows === []) {
                // Fallback: plain text lines inside the day block.
                foreach (Dom::q($xp, ".//li", $day) as $li) {
                    $name = Dom::text($li);
                    if ($name !== '') {
                        $shows[] = ['name' => $name, 'url' => null];
                    }
                }
            }
            $schedule[] = [
                'day' => $labelNode !== null ? Dom::text($labelNode) : '',
                'shows' => $shows,
            ];
        }

        return [
            'sections' => array_values(array_map(
                static fn (array $s): array => ['title' => $s['title'], 'items' => Arr::uniqueBy($s['items'], 'url')],
                $sections
            )),
            'schedule' => $schedule,
        ];
    }

    /**
     * The root page is a static landing/FAQ article. Return it as structured
     * sections: intro paragraphs, then h2/h3 headings with their paragraphs.
     */
    public function index(): array
    {
        $html = $this->http->get($this->base . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $titleNode = Dom::one($xp, '//title');
        $title = $titleNode !== null ? Dom::text($titleNode) : 'AnimeDekho';

        $intro = [];
        $sections = [];
        $current = null;
        foreach (Dom::q($xp, "//div[contains(@class,'entry-content')]//*[self::h2 or self::h3 or self::p]") as $node) {
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
    }

    // ------------------------------------------------------------------
    // Series / movie detail
    // ------------------------------------------------------------------

    /**
     * @param string $slug         series (or movie) slug
     * @param string|null $seasons 'all' (default) | '1' | '1,2' | '2-4'
     * @param bool $withSources    when true, fetch each episode page and attach servers
     * @param string|null $server  server filter: '0,1,2' | '0-4' | 'all' | 'vidstream'
     * @param string $kind         'series' or 'movies' (both share the header layout)
     */
    public function detail(string $slug, ?string $seasons, bool $withSources, ?string $server, string $kind = 'series'): array
    {
        $prefix = $kind === 'movies' ? '/movie-hindi/' : '/series-hindi/';
        $html = $this->http->get($this->base . $prefix . rawurlencode($slug) . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $meta = $this->meta($xp);

        // Season blocks are fully server-rendered — one .seasons-bx per season.
        // NOTE: the season header carries its classes through an Alpine.js
        // ":class" binding (no plain class attribute), so we address it as
        // the block's first child div instead of a class selector.
        $availableSeasons = [];
        $seasonBlocks = [];
        foreach (Dom::q($xp, "//div[contains(@class,'seasons-bx')]") as $block) {
            $header = Dom::one($xp, './div[1]', $block);
            $numberNode = $header !== null ? Dom::one($xp, './/p//span', $header) : null;
            $number = Arr::firstNumber(Dom::text($numberNode)) ?? (string) (count($availableSeasons) + 1);
            $countNode = $header !== null ? Dom::one($xp, ".//*[contains(@class,'date')]", $header) : null;
            $poster = $header !== null ? Dom::one($xp, './/figure//img', $header) : null;

            $availableSeasons[] = [
                'seasonNumber' => (int) $number,
                'name' => 'Season ' . $number,
                'episodeCount' => $countNode !== null ? Dom::text($countNode) : null,
                'poster' => Url::absolute($this->base, Dom::attr($poster, 'src')),
            ];
            $seasonBlocks[(int) $number] = $block;
        }

        $requested = $this->parseSeasons($seasons, array_keys($seasonBlocks));

        $seasonsOut = [];
        $fetchedEpisodes = 0;
        foreach ($requested as $number) {
            $block = $seasonBlocks[$number] ?? null;
            $episodes = $block !== null ? $this->parseSeasonBlock($xp, $block) : [];
            $fetchedEpisodes += count($episodes);
            $seasonsOut[] = [
                'seasonNumber' => $number,
                'name' => 'Season ' . $number,
                'episodes' => $episodes,
            ];
        }

        // Optional: server sources per episode (parallel fetch, cached).
        if ($withSources && $seasonsOut !== []) {
            $urls = [];
            foreach ($seasonsOut as $season) {
                foreach ($season['episodes'] as $episode) {
                    $urls[] = $episode['url'];
                }
            }
            $bodies = $this->http->getMany(array_values(array_unique($urls)), 6);
            foreach ($seasonsOut as &$season) {
                foreach ($season['episodes'] as &$episode) {
                    $body = $bodies[$episode['url']] ?? null;
                    $episode['servers'] = $body !== null
                        ? $this->withOriginalSrcs($this->filterServers($this->serversFromHtml((string) $body), $server))
                        : [];
                }
            }
            unset($season, $episode);
        }

        return [
            'slug' => $slug,
            'title' => $meta['title'],
            'image' => $meta['image'],
            'rating' => $meta['rating'],
            'year' => $meta['year'],
            'duration' => $meta['duration'],
            'views' => $meta['views'],
            'description' => $meta['description'],
            'categories' => $meta['categories'],
            'genres' => $meta['genres'],
            'cast' => $meta['cast'],
            'latestEpisode' => $meta['latestEpisode'],
            'batchDownloadUrl' => $meta['batchDownloadUrl'],
            'totalSeasons' => count($availableSeasons),
            'availableSeasons' => $availableSeasons,
            'seasons' => $seasonsOut,
        ];
    }

    /**
     * Movie detail: same header layout as series but no season blocks. The
     * unlocked page hosts its own player tabs (a[data-src]) — exposed as
     * `servers`; an /epi/ link, when present, becomes a pseudo-episode.
     */
    public function movie(string $slug, ?string $server = null): array
    {
        // Movie pages are gated behind the same ad-skip wall as episodes.
        $html = $this->unlock($this->base . '/movie-hindi/' . rawurlencode($slug) . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $meta = $this->meta($xp);

        $episodes = [];
        $watchLink = null;
        foreach (Dom::q($xp, "//a[contains(@href,'/epi/')]") as $anchor) {
            $href = Dom::href($anchor);
            if ($href !== null) {
                $watchLink = Url::absolute($this->base, $href);
                break;
            }
        }
        if ($watchLink !== null) {
            $episodes[] = [
                'title' => $meta['title'],
                'episodeNumber' => 'Movie',
                'image' => $meta['image'],
                'url' => $watchLink,
                'slug' => Url::slug($watchLink),
            ];
        }

        $servers = $this->withOriginalSrcs($this->filterServers($this->servers($xp), $server));

        return [
            'slug' => $slug,
            'title' => $meta['title'],
            'image' => $meta['image'],
            'rating' => $meta['rating'],
            'year' => $meta['year'],
            'duration' => $meta['duration'],
            'views' => $meta['views'],
            'description' => $meta['description'],
            'categories' => $meta['categories'],
            'genres' => $meta['genres'],
            'cast' => $meta['cast'],
            'latestEpisode' => $meta['latestEpisode'],
            'batchDownloadUrl' => $meta['batchDownloadUrl'],
            'totalEpisodes' => count($episodes) > 0 ? count($episodes) : 1,
            'episodes' => $episodes,
            'servers' => $servers,
        ];
    }

    // ------------------------------------------------------------------
    // Episode
    // ------------------------------------------------------------------

    /**
     * Player page. Without cookies the page shows an ad-skip wall whose
     * forms carry a hidden #shortlink pointing at /24hr/verify.php — GETting
     * that URL sets the `toronites_server` cookie (~24h) which unlocks the
     * real player. The shared cookie jar makes this transparent after the
     * first request.
     */
    public function episode(string $slug, ?string $server): array
    {
        $url = $this->base . '/epi/' . rawurlencode($slug) . '/';
        $html = $this->unlock($url);
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        // Breadcrumb: [Home, Series, {series}, {episode title}].
        $seriesPage = null;
        $crumbs = Dom::q($xp, "//nav[contains(@class,'breadcrumb')]/*[self::a or self::span]");
        $episodeTitle = '';
        foreach ($crumbs as $i => $crumb) {
            $isLast = $i === count($crumbs) - 1;
            if ($isLast) {
                $episodeTitle = Dom::text($crumb);
            } elseif (str_contains((string) Dom::href($crumb), '/series')) {
                $seriesPage = Url::absolute($this->base, Dom::href($crumb));
            }
        }

        $labelNode = Dom::one($xp, "//span[contains(@class,'season-episode')]");
        $seasonEpisodeLabel = $labelNode !== null ? Dom::text($labelNode) : null;

        $servers = $this->servers($xp);
        $filtered = $this->withOriginalSrcs($this->filterServers($servers, $server));

        return [
            'slug' => $slug,
            'title' => $episodeTitle !== '' ? $episodeTitle : $slug,
            'seasonEpisodeLabel' => $seasonEpisodeLabel,
            'navigation' => [
                'seriesPage' => $seriesPage,
            ],
            'servers' => $filtered,
        ];
    }

    // ------------------------------------------------------------------
    // Ad-gate token
    // ------------------------------------------------------------------

    /**
     * Expose the site's ad-gate verify links for an episode (/epi/) or movie
     * (/movie-hindi/) page. Every skip form carries a hidden #shortlink
     * pointing at /24hr/verify.php?expires=…&token=… — GETting any one of
     * them sets the unlocking `toronites_server` cookie (~24h).
     *
     * This endpoint collects every verify URL, optionally activates the
     * first one into the shared cookie jar, and — when the page unlocks —
     * attaches the resulting player servers.
     */
    public function token(string $slug, string $kind = 'episode'): array
    {
        // Always observe the site's REAL gated state: drop any unlocking
        // cookie first, then re-activate a fresh verify link below. Other
        // endpoints are untouched — they keep the shared jar + bypass.
        $this->http->clearCookieJar();

        $url = $kind === 'movie'
            ? $this->base . '/movie-hindi/' . rawurlencode($slug) . '/'
            : $this->base . '/epi/' . rawurlencode($slug) . '/';

        $html = $this->http->get($url);
        $gated = str_contains($html, '24hr/verify.php');

        // Collect every skip form's verify link (v1/vshort, v2/gplinks,
        // v3/cuty variants share the same hidden #shortlink pattern).
        $verifyUrls = [];
        if (preg_match_all('#<form\b[^>]*action="([^"]*)"[^>]*>(.*?)</form>#s', $html, $forms, PREG_SET_ORDER)) {
            foreach ($forms as $form) {
                if (preg_match('#id="shortlink[^"]*"[^>]*value="([^"]+)"#', $form[2], $m)) {
                    $verifyUrls[] = [
                        'label' => $this->skipLabel($form[2]),
                        'action' => html_entity_decode($form[1], ENT_QUOTES | ENT_HTML5),
                        'verifyUrl' => html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5),
                    ];
                }
            }
        }

        $unlockedHtml = null;
        $activated = null;
        if (!$gated) {
            $unlockedHtml = $html;
        } elseif ($verifyUrls !== []) {
            // Activate the first verify link into the shared jar.
            try {
                $this->http->get($verifyUrls[0]['verifyUrl']);
                $activated = $verifyUrls[0]['verifyUrl'];
            } catch (ScrapeException $e) {
                // Activation failed — still report the raw links below.
            }
            $retry = $this->http->get($url);
            if (!str_contains($retry, '24hr/verify.php')) {
                $unlockedHtml = $retry;
            }
        }

        $servers = [];
        if ($unlockedHtml !== null) {
            $servers = $this->withOriginalSrcs($this->servers(Dom::xp(Dom::load($unlockedHtml))));
        }

        return [
            'slug' => $slug,
            'kind' => $kind,
            'pageUrl' => $url,
            'gated' => $gated,
            'cookieUnlocked' => $unlockedHtml !== null,
            'activatedVerifyUrl' => $activated,
            'verifyUrls' => $verifyUrls,
            'servers' => $servers,
        ];
    }

    /** Best-effort label from a skip form's button text ("Skip AD [v1] …"). */
    private function skipLabel(string $formInner): string
    {
        if (preg_match('#<button[^>]*>(.*?)</button>#s', $formInner, $m)) {
            return Dom::text(Dom::one(Dom::xp(Dom::load('<div>' . $m[1] . '</div>')), '//div')) ?: 'Skip AD';
        }
        return 'Skip AD';
    }

    /**
     * Pick a random slug for the ad-gate token flow.
     *
     * - movie: random card off the /movie-hindi/ listing.
     * - episode (default): random series card off /home/, then a random
     *   episode from its server-rendered season blocks (tries up to 5
     *   different series before giving up).
     */
    public function randomSlug(string $kind = 'episode'): string
    {
        if ($kind === 'movie') {
            $listing = $this->listing('/movie-hindi/');
            $movies = array_values(array_filter(
                $listing['items'],
                static fn (array $i): bool => ($i['contentType'] ?? '') === 'movies'
            ));
            if ($movies !== []) {
                return (string) $movies[array_rand($movies)]['slug'];
            }
            throw new ScrapeException('No movies found for a random pick.', 502);
        }

        $seriesCards = [];
        foreach ($this->home()['sections'] as $section) {
            foreach ($section['items'] as $card) {
                if (($card['contentType'] ?? '') === 'series' && isset($card['slug'])) {
                    $seriesCards[] = $card;
                }
            }
        }
        shuffle($seriesCards);

        foreach (array_slice($seriesCards, 0, 5) as $card) {
            try {
                $detail = $this->detail((string) $card['slug'], null, false, null);
            } catch (ScrapeException $e) {
                continue; // dead card — try the next one
            }
            $episodes = [];
            foreach ($detail['seasons'] as $season) {
                foreach ($season['episodes'] as $episode) {
                    $episodes[] = $episode;
                }
            }
            if ($episodes !== []) {
                return (string) $episodes[array_rand($episodes)]['slug'];
            }
        }
        throw new ScrapeException('No episodes found for a random pick.', 502);
    }

    // ------------------------------------------------------------------
    // Search & listings
    // ------------------------------------------------------------------

    public function search(string $query, int $page = 1): array
    {
        // WordPress search pagination: /page/N/?s=query
        $url = $this->base . '/';
        if ($page > 1) {
            $url .= 'page/' . $page . '/';
        }
        $url .= '?s=' . rawurlencode($query);
        [$items, $totalPages] = $this->parseListing($html = $this->http->get($url));

        return [
            'searchQuery' => $query,
            'hasResults' => $items !== [],
            'page' => $page,
            'totalPages' => $totalPages,
            'results' => $items,
        ];
    }

    /**
     * Shared listing for series/movies/category/cast/letter pages.
     *
     * @param string $path site path beginning with "/"
     */
    public function listing(string $path, int $page = 1): array
    {
        $url = $this->base . rtrim($path, '/') . '/';
        if ($page > 1) {
            $url .= 'page/' . $page . '/';
        }
        [$items, $totalPages] = $this->parseListing($this->http->get($url));
        return [
            'page' => $page,
            'totalPages' => $totalPages,
            'items' => $items,
        ];
    }

    // ------------------------------------------------------------------
    // Parsers
    // ------------------------------------------------------------------

    /**
     * Common header metadata shared by series, movie and landing pages.
     *
     * @return array<string, mixed>
     */
    private function meta(DOMXPath $xp): array
    {
        $titleNode = Dom::one($xp, "//h1[contains(@class,'entry-title')]");
        $title = $titleNode !== null ? Dom::text($titleNode) : '';

        $img = Dom::one($xp, "//aside//div[contains(@class,'post-thumbnail')]//img");
        $image = $img !== null ? (Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src')) : null;

        $ratingEl = Dom::one($xp, "//span[contains(@class,'rating')]//span");
        $rating = $ratingEl !== null ? Dom::text($ratingEl) : null;

        $yearEl = Dom::one($xp, "//span[contains(@class,'year')]");
        $year = $yearEl !== null ? Dom::text($yearEl) : null;

        $durEl = Dom::one($xp, "//span[contains(@class,'duration')]");
        $duration = $durEl !== null ? Dom::text($durEl) : null;

        $viewsEl = Dom::one($xp, "//div[@id='viewz']//span");
        $views = $viewsEl !== null ? trim(str_replace('Views', '', Dom::text($viewsEl))) : null;

        $descEl = Dom::one($xp, "//div[contains(@class,'entry-content')]//p");
        $description = $descEl !== null ? Dom::text($descEl) : null;

        $categories = [];
        foreach (Dom::q($xp, "//span[contains(@class,'categories')]//a") as $anchor) {
            $categories[] = [
                'name' => Dom::text($anchor),
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        // details-lst rows: first span = label ("Genres"/"Cast"), second = links.
        $genres = [];
        $cast = [];
        foreach (Dom::q($xp, "//ul[contains(@class,'details-lst')]//li") as $li) {
            $spans = Dom::q($xp, './span', $li);
            if (count($spans) < 2) {
                continue;
            }
            $label = strtolower(Dom::text($spans[0]));
            $links = [];
            foreach (Dom::q($xp, './/a', $spans[1]) as $anchor) {
                $links[] = [
                    'name' => Dom::text($anchor),
                    'url' => Url::absolute($this->base, Dom::href($anchor)),
                ];
            }
            if (str_contains($label, 'genre')) {
                $genres = $links;
            } elseif (str_contains($label, 'cast')) {
                $cast = $links;
            }
        }

        // Latest-episode shortcut link ("Go To Latest Episode : Season 2 …").
        $latestEpisode = null;
        $latestNode = Dom::one($xp, "//span[contains(@class,'season-episode')]//a");
        if ($latestNode !== null) {
            $latestEpisode = [
                'label' => Dom::text($latestNode),
                'url' => Url::absolute($this->base, Dom::href($latestNode)),
            ];
        }

        // Batch download link (/batch/get-links.php?id=…).
        $batchDownloadUrl = null;
        $batchNode = Dom::one($xp, "//a[contains(@href,'get-links.php')]");
        if ($batchNode !== null) {
            $batchDownloadUrl = Url::absolute($this->base, Dom::href($batchNode));
        }

        return [
            'title' => $title,
            'image' => Url::absolute($this->base, $image),
            'rating' => $rating,
            'year' => $year,
            'duration' => $duration,
            'views' => $views,
            'description' => $description,
            'categories' => $categories,
            'genres' => $genres,
            'cast' => $cast,
            'latestEpisode' => $latestEpisode,
            'batchDownloadUrl' => $batchDownloadUrl,
        ];
    }

    /**
     * A listing/search card (article.post with a lnk-blk anchor).
     *
     * @return array<string, mixed>|null
     */
    private function parseCard(DOMXPath $xp, DOMElement $article): ?array
    {
        $link = Dom::one($xp, ".//a[contains(@class,'lnk-blk')]", $article);
        $href = Dom::href($link);
        if ($href === null) {
            return null;
        }

        $titleNode = Dom::one($xp, ".//*[contains(@class,'entry-title')]", $article);
        $img = Dom::one($xp, ".//figure//img", $article);
        $vote = Dom::one($xp, ".//*[contains(@class,'rating')]", $article);
        $quality = Dom::one($xp, ".//span[contains(@class,'quality')]", $article);
        $epLabel = Dom::one($xp, ".//span[contains(@class,'season-episode')]", $article);

        if (str_starts_with($href, $this->base . '/epi/') || str_contains($href, '/epi/')) {
            $type = 'episode';
        } elseif (str_contains($href, '/movie-hindi/') || str_contains($href, '/movies-hindi/')) {
            $type = 'movies';
        } else {
            $type = 'series';
        }

        $card = [
            'id' => 'post-' . Url::slug($href),
            'title' => Dom::text($titleNode),
            'image' => Url::absolute($this->base, Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src')),
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
            'rating' => $vote !== null ? Arr::firstNumber(Dom::text($vote)) : null,
            'contentType' => $type,
        ];

        if ($quality !== null && Dom::text($quality) !== '') {
            $card['quality'] = Dom::text($quality);
        }
        if ($epLabel !== null && Dom::text($epLabel) !== '') {
            $card['episodeLabel'] = Dom::text($epLabel);
        }
        $yearEl = Dom::one($xp, ".//*[contains(@class,'entry-meta')]//span[contains(@class,'year')]", $article);
        if ($yearEl !== null && Dom::text($yearEl) !== '') {
            $card['year'] = Dom::text($yearEl);
        }

        return $card;
    }

    /**
     * One episode row from a season block (ul.seasons-lst li).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSeasonBlock(DOMXPath $xp, \DOMNode $block): array
    {
        $episodes = [];
        foreach (Dom::q($xp, ".//ul[contains(@class,'seasons-lst')]//li", $block) as $li) {
            $link = Dom::one($xp, ".//a[contains(@href,'/epi/')]", $li);
            $href = Dom::href($link);
            if ($href === null) {
                continue;
            }
            $img = Dom::one($xp, './/figure//img', $li);
            $titleNode = Dom::one($xp, ".//h3[contains(@class,'title')]", $li);
            $numSpan = $titleNode !== null ? Dom::one($xp, './span', $titleNode) : null;
            $episodeNumber = $numSpan !== null ? Dom::text($numSpan) : '';
            $episodeTitle = '';
            if ($titleNode !== null) {
                // Title text minus the leading "S2-E1" span.
                $episodeTitle = trim(substr(Dom::text($titleNode), strlen($episodeNumber)));
            }

            $episodes[] = [
                'title' => $episodeTitle,
                'episodeNumber' => $episodeNumber,
                'image' => Url::absolute($this->base, Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src')),
                'url' => Url::absolute($this->base, $href),
                'slug' => Url::slug($href),
            ];
        }
        return $episodes;
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: int} [items, totalPages] */
    private function parseListing(string $html): array
    {
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $items = [];
        foreach (Dom::q($xp, "//article[contains(@class,'post')][.//a[contains(@class,'lnk-blk')]]") as $article) {
            $card = $this->parseCard($xp, $article);
            if ($card !== null) {
                $items[] = $card;
            }
        }

        $totalPages = 1;
        foreach (Dom::q($xp, "//a[starts-with(@href,'" . $this->base . "') or contains(@href,'/page/')]") as $anchor) {
            $href = Dom::href($anchor);
            if ($href !== null && preg_match('#/page/(\d+)/?#', $href, $m)) {
                $totalPages = max($totalPages, (int) $m[1]);
            }
        }
        return [$items, $totalPages];
    }

    // ------------------------------------------------------------------
    // Streaming servers
    // ------------------------------------------------------------------

    /**
     * Server tabs live in ul.bx-lst.aa-tbs; each anchor carries the player
     * URL base64-encoded in data-src and its display name in span.num.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servers(DOMXPath $xp): array
    {
        $servers = [];
        $index = 0;
        foreach (Dom::q($xp, "//ul[contains(@class,'bx-lst')]//a[@data-src]") as $tab) {
            $encoded = (string) Dom::attr($tab, 'data-src');
            $src = $encoded !== '' ? base64_decode($encoded, true) : false;
            $nameEl = Dom::one($xp, ".//span[contains(@class,'num')]", $tab);
            $servers[] = [
                'serverNumber' => $index,
                'displayNumber' => $index + 1,
                'name' => $nameEl !== null ? Dom::text($nameEl) : 'Server',
                'src' => $src !== false && $src !== '' ? $src : null,
                'originalSrc' => null,
                'isActive' => str_contains($tab->getAttribute('class'), 'on'),
            ];
            $index++;
        }
        return $servers;
    }

    /** @return array<int, array<string, mixed>> */
    private function serversFromHtml(string $html): array
    {
        return $this->servers(Dom::xp(Dom::load($html)));
    }

    /**
     * Resolve each server's direct player URL. Three wrapper kinds hide the
     * real player behind them:
     *   - /embed/{id}/{s}-{e}          thin page wrapping a player iframe
     *   - /?trdekho=N&trid=…           thin page wrapping a player iframe
     *                                  (abyssplayer, vidmoly, …)
     *   - /aaa/myth/play.php?id=…      Playerjs shell pulling JSON sources
     *                                  from /aaa/myth/fetch.php?id=…
     * Everything else already points at an external player directly.
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<int, array<string, mixed>>
     */
    private function withOriginalSrcs(array $servers): array
    {
        $wrapperUrls = [];
        foreach ($servers as $server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && $this->isWrapperSrc($src)) {
                $wrapperUrls[] = $src;
            }
        }
        $bodies = $wrapperUrls !== [] ? $this->http->getMany(array_values(array_unique($wrapperUrls)), 6) : [];
        foreach ($servers as &$server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && isset($bodies[$src]) && $bodies[$src] !== null) {
                $body = (string) $bodies[$src];
                $server['originalSrc'] = str_contains($body, 'myth/fetch.php')
                    ? $this->mythOriginalSrc($body)
                    : $this->embedOriginalSrc($src, $body);
            }
        }
        unset($server);
        return $servers;
    }

    /** Whether a server src is one of the site's own player wrappers. */
    private function isWrapperSrc(string $src): bool
    {
        return str_starts_with($src, $this->base . '/embed/')
            || str_starts_with($src, $this->base . '/?trdekho=')
            || str_contains($src, '/aaa/myth/play.php');
    }

    /**
     * Resolve a NeoCDN (Playerjs) wrapper: pull the fetch.php token out of
     * the page JS, GET the JSON feed, return its best-quality source URL.
     */
    private function mythOriginalSrc(string $html): ?string
    {
        if (!preg_match('#/aaa/myth/fetch\.php\?id=([A-Za-z0-9%._-]+)#', $html, $m)) {
            return null;
        }
        try {
            $json = $this->http->get($this->base . '/aaa/myth/fetch.php?id=' . $m[1]);
        } catch (ScrapeException $e) {
            return null;
        }
        $data = json_decode($json, true);
        $sources = is_array($data['sources'] ?? null) ? $data['sources'] : [];
        $best = null;
        $bestRank = -1;
        $order = ['360p' => 1, '480p' => 2, '720p' => 3, '1080p' => 4];
        foreach ($sources as $source) {
            $url = is_array($source) ? ($source['url'] ?? null) : null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            $rank = $order[(string) ($source['type'] ?? '')] ?? 99;
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $url;
            }
        }
        return $best;
    }

    /**
     * Extract the nested player URL from a wrapper page (/embed/ or
     * /?trdekho=…): the first external iframe's src points at the real
     * player host.
     */
    private function embedOriginalSrc(string $embedUrl, string $html): ?string
    {
        $xp = Dom::xp(Dom::load($html));
        foreach (Dom::q($xp, '//iframe[@src]') as $frame) {
            $src = Dom::attr($frame, 'src') ?? Dom::attr($frame, 'data-src');
            // Skip self-referential/ad frames; the real player lives off-host.
            if ($src !== null && $src !== '' && !str_starts_with($src, $this->base)) {
                return Url::absolute($embedUrl, $src);
            }
        }
        return null;
    }

    /**
     * Server spec: 'all' (default), numeric list '0,1,2', range '0-4',
     * or comma-separated server names 'vidstream,sruby'.
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
     * Fetch a page, passing the ad-skip gate when present: extract the
     * hidden #shortlink verify URL, GET it once (the response sets the
     * unlocking cookie into the shared jar), then re-fetch the page.
     */
    private function unlock(string $url): string
    {
        $html = $this->http->get($url);
        if (!str_contains($html, '24hr/verify.php')) {
            return $html;
        }
        if (preg_match('#id="shortlink[^"]*"[^>]*value="([^"]+)"#', $html, $m)
            || preg_match('#value="(https?://[^"]*24hr/verify\.php[^"]*)"#', $html, $m)) {
            try {
                $this->http->get(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            } catch (ScrapeException $e) {
                // Fall through — maybe the jar already has a valid cookie.
            }
            $retry = $this->http->get($url);
            if (!str_contains($retry, '24hr/verify.php')) {
                return $retry;
            }
            return $retry; // Gate persists; caller still gets wall markup.
        }
        return $html;
    }

    /**
     * Seasons spec: 'all' (default) | '1' | '1,2' | '2-4'.
     *
     * @param array<int, int> $available
     * @return array<int, int>
     */
    private function parseSeasons(?string $spec, array $available): array
    {
        if ($spec === null || trim($spec) === '' || strtolower(trim($spec)) === 'all') {
            return array_values($available);
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
        return $valid !== [] ? $valid : array_values($available);
    }
}
