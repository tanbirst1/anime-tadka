<?php

declare(strict_types=1);

namespace AnimeDekhoApi\Providers;

use DOMXPath;
use AnimeDekhoApi\Core\ScrapeException;
use AnimeDekhoApi\Utils\Arr;
use AnimeDekhoApi\Utils\Dom;
use AnimeDekhoApi\Utils\HttpClient;
use AnimeDekhoApi\Utils\Url;

/**
 * AnimeDekho provider: every endpoint maps to real pages of the target site.
 *
 * The site (animedekho.tv, WordPress + the "dgt" theme) is JS-driven: pages
 * ship a `data-snapshot` JSON blob for the title and load episodes/player
 * sources over a REST API under /wp-json/dgt/v1/.
 *
 * Site map (verified against animedekho.tv):
 *   /home/                        → content rows (dgt-row) + spotlight
 *   /                             → static landing page
 *   /browse/?q={q}&type=&genre=&sort=&letter=&pg=N → search / browse grid
 *   /series-hindi/{slug}/         → series detail (snapshot + season tabs)
 *   /movie-hindi/{slug}/          → movie detail (?watch=1 for the player)
 *   /epi/{slug}/                  → episode watch page (snapshot + dgt-watch)
 *   /series-hindi/, /movie-hindi/ → series / movie listings
 *   /genre/{slug}/                → genre listings
 *   REST /wp-json/dgt/v1/episodes?series={id}&season={n} → episode list (JSON)
 *   REST /wp-json/dgt/v1/watch    → player sources for {post_id,episode_id}
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
     * The real content grid lives at /home/. Each section is a
     * <section class="dgt-row"> with an <h2 class="dgt-h2"> title followed
     * by <a class="dgt-card"> cards; the hero carousel is #dgt-spot.
     */
    public function home(): array
    {
        $html = $this->http->get($this->base . '/home/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $spotlight = [];
        foreach (Dom::q($xp, "//section[contains(@class,'dgt-spot')]//article[contains(@class,'dgt-spot__slide')]") as $slide) {
            $details = null;
            foreach (Dom::q($xp, ".//div[contains(@class,'dgt-actions')]//a", $slide) as $anchor) {
                $href = Dom::href($anchor);
                if ($href !== null && !str_contains($href, 'watch=1')) {
                    $details = Url::absolute($this->base, $href);
                    break;
                }
            }
            if ($details === null) {
                continue;
            }
            $style = (string) Dom::attr($slide, 'style');
            $image = null;
            if (preg_match("#url\('([^']+)'\)#", $style, $m)) {
                $image = $m[1];
            }
            $kicker = Dom::one($xp, ".//div[contains(@class,'dgt-spot__kicker')]", $slide);
            $spotlight[] = [
                'title' => Dom::text(Dom::one($xp, ".//h2[contains(@class,'dgt-spot__title')]", $slide)),
                'url' => $details,
                'slug' => Url::slug($details),
                'image' => $image,
                'poster' => $image,
                'kicker' => $kicker !== null ? Dom::text($kicker) : '',
                'description' => Dom::text(Dom::one($xp, ".//p[contains(@class,'dgt-spot__text')]", $slide)),
            ];
        }

        $sections = [];
        foreach (Dom::q($xp, "//section[contains(@class,'dgt-row')]") as $section) {
            $titleNode = Dom::one($xp, ".//h2[contains(@class,'dgt-h2')]", $section);
            $title = $titleNode !== null ? Dom::text($titleNode) : '';
            if ($title === '') {
                continue;
            }
            $items = [];
            foreach (Dom::q($xp, ".//a[contains(@class,'dgt-card')]", $section) as $card) {
                $parsed = $this->parseCard($xp, $card);
                if ($parsed !== null) {
                    $items[] = $parsed;
                }
            }
            if ($items !== []) {
                $sections[] = ['title' => $title, 'items' => $items];
            }
        }

        return ['spotlight' => $spotlight, 'sections' => $sections, 'schedule' => []];
    }

    /**
     * The root page is a static landing/splash page; return its headings and
     * paragraphs as structured sections.
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
        foreach (Dom::q($xp, "//h1 | //h2 | //h3 | //p") as $node) {
            $text = Dom::text($node);
            if ($text === '') {
                continue;
            }
            if (in_array($node->nodeName, ['h1', 'h2', 'h3'], true)) {
                $current = [
                    'heading' => $text,
                    'type' => $node->nodeName === 'h1' ? 'section' : 'subsection',
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
     * @param bool $withSources    when true, attach player servers to each episode
     * @param string|null $server  server filter: '0,1,2' | '0-4' | 'all' | 'vidstream'
     * @param string $kind         'series' or 'movies' (both share the header layout)
     */
    public function detail(string $slug, ?string $seasons, bool $withSources, ?string $server, string $kind = 'series'): array
    {
        $prefix = $kind === 'movies' ? '/movie-hindi/' : '/series-hindi/';
        $html = $this->http->get($this->base . $prefix . rawurlencode($slug) . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $snapshot = $this->snapshot($xp);
        $meta = $this->meta($xp);

        $seriesId = (int) ($snapshot['id'] ?? 0);
        $tabs = $this->seasonTabs($xp);

        $requested = $this->parseSeasons($seasons, array_keys($tabs));
        if ($requested === [] && $tabs !== []) {
            $requested = [array_key_first($tabs)];
        }

        $seasonsOut = [];
        $availableSeasons = [];
        foreach ($tabs as $number => $label) {
            $availableSeasons[] = [
                'seasonNumber' => $number,
                'name' => $label,
                'episodeCount' => null,
                'poster' => Url::absolute($this->base, $meta['image']),
            ];
        }

        foreach ($requested as $number) {
            $episodes = $seriesId > 0 ? $this->fetchEpisodes($seriesId, $number) : [];
            $seasonsOut[] = [
                'seasonNumber' => $number,
                'name' => $tabs[$number] ?? ('Season ' . $number),
                'episodes' => $episodes,
            ];
        }

        if ($withSources && $seasonsOut !== []) {
            foreach ($seasonsOut as &$season) {
                foreach ($season['episodes'] as &$episode) {
                    $episode['servers'] = $this->withOriginalSrcs(
                        $this->filterServers($this->fetchServers($seriesId, (int) $episode['id']), $server)
                    );
                }
                unset($episode);
            }
            unset($season);
        }

        $firstEpisode = $seasonsOut[0]['episodes'][0] ?? null;
        $totalEpisodes = 0;
        foreach ($seasonsOut as $season) {
            $totalEpisodes += count($season['episodes']);
        }

        return [
            'slug' => $slug,
            'id' => $seriesId,
            'contentType' => (string) ($snapshot['type'] ?? 'series'),
            'title' => $meta['title'] !== '' ? $meta['title'] : ($snapshot['title'] ?? $slug),
            'image' => $meta['image'] ?? ($snapshot['poster'] ?? null),
            'rating' => $meta['rating'] ?? ($snapshot['rating'] ?? null),
            'year' => $meta['year'] ?? ($snapshot['year'] ?? null),
            'duration' => $meta['duration'] ?? ($snapshot['runtime'] ?? null),
            'audio' => (string) ($snapshot['audio'] ?? ''),
            'views' => $meta['views'],
            'description' => $meta['description'],
            'categories' => $meta['categories'],
            'genres' => $meta['genres'] !== [] ? $meta['genres'] : $this->genresFromSnapshot($snapshot),
            'cast' => $meta['cast'],
            'latestEpisode' => $firstEpisode !== null ? [
                'label' => $firstEpisode['label'],
                'url' => $firstEpisode['url'],
            ] : null,
            'batchDownloadUrl' => null,
            'totalEpisodes' => $totalEpisodes,
            'totalSeasons' => count($availableSeasons),
            'availableSeasons' => $availableSeasons,
            'seasons' => $seasonsOut,
        ];
    }

    /**
     * Movie detail: same header layout as series but no season blocks. The
     * movie is a single playable unit; sources come from the ?watch=1 page.
     */
    public function movie(string $slug, ?string $server = null): array
    {
        $html = $this->http->get($this->base . '/movie-hindi/' . rawurlencode($slug) . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $snapshot = $this->snapshot($xp);
        $meta = $this->meta($xp);
        $postId = (int) ($snapshot['id'] ?? 0);

        $servers = $this->fetchServers($postId, 0);
        $filtered = $this->filterServers($servers, $server);

        return [
            'slug' => $slug,
            'id' => $postId,
            'contentType' => (string) ($snapshot['type'] ?? 'movie'),
            'title' => $meta['title'] !== '' ? $meta['title'] : ($snapshot['title'] ?? $slug),
            'image' => $meta['image'] ?? ($snapshot['poster'] ?? null),
            'rating' => $meta['rating'] ?? ($snapshot['rating'] ?? null),
            'year' => $meta['year'] ?? ($snapshot['year'] ?? null),
            'duration' => $meta['duration'] ?? ($snapshot['runtime'] ?? null),
            'views' => $meta['views'],
            'description' => $meta['description'],
            'categories' => $meta['categories'],
            'genres' => $meta['genres'] !== [] ? $meta['genres'] : $this->genresFromSnapshot($snapshot),
            'cast' => $meta['cast'],
            'latestEpisode' => null,
            'batchDownloadUrl' => null,
            'totalEpisodes' => 1,
            'episodes' => [],
            'servers' => $this->withOriginalSrcs($filtered),
        ];
    }

    // ------------------------------------------------------------------
    // Episode
    // ------------------------------------------------------------------

    /**
     * Player page. The watch root carries the series/episode ids in its
     * data attributes; sources are resolved over the REST API.
     */
    public function episode(string $slug, ?string $server): array
    {
        $url = $this->base . '/epi/' . rawurlencode($slug) . '/';
        $html = $this->http->get($url);
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $seriesId = (int) (Dom::attr(Dom::one($xp, "//*[@id='dgt-watch']"), 'data-series')
            ?? Dom::attr(Dom::one($xp, "//*[@id='dgt-title']"), 'data-series')
            ?? '0');
        $episodeId = (int) (Dom::attr(Dom::one($xp, "//*[@id='dgt-watch']"), 'data-term') ?? '0');
        $episodeData = $this->jsonAttr($xp, "//*[@id='dgt-watch']", 'data-episode');
        $snapshot = $this->snapshot($xp);

        $seriesPage = null;
        $crumb = Dom::one($xp, "//div[contains(@class,'dgt-crumbs')]//a");
        if ($crumb !== null) {
            $seriesPage = Url::absolute($this->base, Dom::href($crumb));
        }

        $label = $episodeData['label'] ?? null;
        $name = $episodeData['name'] ?? '';

        $servers = $this->withOriginalSrcs($this->filterServers($this->fetchServers($seriesId, $episodeId), $server));

        return [
            'slug' => $slug,
            'id' => $episodeId,
            'seriesId' => $seriesId,
            'title' => $name !== '' ? $name : ($snapshot['title'] ?? $slug),
            'seasonEpisodeLabel' => $label,
            'image' => $episodeData['still'] ?? ($snapshot['poster'] ?? null),
            'navigation' => [
                'seriesPage' => $seriesPage,
            ],
            'servers' => $servers,
        ];
    }

    // ------------------------------------------------------------------
    // Ad-gate token
    // ------------------------------------------------------------------

    /**
     * Expose the site's ad-gate verify links for an episode (/epi/) or movie
     * (/movie-hindi/) page. Kept for compatibility: the redesigned site no
     * longer gates playback, so `gated` is normally false and the servers are
     * returned directly.
     */
    public function token(string $slug, string $kind = 'episode'): array
    {
        $this->http->clearCookieJar();

        $url = $kind === 'movie'
            ? $this->base . '/movie-hindi/' . rawurlencode($slug) . '/'
            : $this->base . '/epi/' . rawurlencode($slug) . '/';

        $html = $this->http->get($url);
        $gated = str_contains($html, '24hr/verify.php');

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

        $servers = [];
        if (!$gated) {
            $xp = Dom::xp(Dom::load($html));
            $seriesId = (int) (Dom::attr(Dom::one($xp, "//*[@id='dgt-watch']"), 'data-series')
                ?? Dom::attr(Dom::one($xp, "//*[@id='dgt-title']"), 'data-series')
                ?? '0');
            $episodeId = (int) (Dom::attr(Dom::one($xp, "//*[@id='dgt-watch']"), 'data-term') ?? '0');
            $servers = $this->withOriginalSrcs($this->fetchServers($seriesId, $episodeId));
        }

        return [
            'slug' => $slug,
            'kind' => $kind,
            'pageUrl' => $url,
            'gated' => $gated,
            'cookieUnlocked' => !$gated,
            'activatedVerifyUrl' => null,
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
        $url = $this->base . '/browse/?q=' . rawurlencode($query);
        if ($page > 1) {
            $url .= '&pg=' . $page;
        }
        [$items, $totalPages] = $this->parseListing($this->http->get($url));

        return [
            'searchQuery' => $query,
            'hasResults' => $items !== [],
            'page' => $page,
            'totalPages' => $totalPages,
            'results' => $items,
        ];
    }

    /**
     * Shared listing for series/movies/genre/category pages. Archive pages
     * paginate by path (/series-hindi/page/N/); the browse grid uses ?pg=N.
     *
     * @param string $path site path beginning with "/"
     * @param array<string, string> $params extra query params (e.g. letter)
     */
    public function listing(string $path, int $page = 1, array $params = []): array
    {
        $path = rtrim($path, '/');
        $url = $this->base . $path . '/';
        if ($page > 1) {
            $url .= str_contains($path, 'browse') ? '?pg=' . $page : 'page/' . $page . '/';
        }
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }
        [$items, $totalPages] = $this->parseListing($this->http->get($url));
        return [
            'page' => $page,
            'totalPages' => $totalPages,
            'items' => $items,
        ];
    }

    // ------------------------------------------------------------------
    // REST API
    // ------------------------------------------------------------------

    /**
     * Episode list for one season, straight from the site's REST API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchEpisodes(int $seriesId, int $season): array
    {
        $data = $this->http->postJson($this->base . '/wp-json/dgt/v1/episodes?series=' . $seriesId . '&season=' . $season, []);
        // Some WP builds reject POST on a GET route — retry as a plain GET.
        if ($data === null) {
            $raw = null;
            try {
                $raw = $this->http->get($this->base . '/wp-json/dgt/v1/episodes?series=' . $seriesId . '&season=' . $season);
            } catch (ScrapeException $e) {
                return [];
            }
            $data = json_decode($raw, true);
        }
        if (!is_array($data)) {
            return [];
        }

        $episodes = [];
        foreach ($data['episodes'] ?? [] as $ep) {
            if (!is_array($ep)) {
                continue;
            }
            $url = (string) ($ep['url'] ?? '');
            $episodes[] = [
                'id' => (int) ($ep['id'] ?? 0),
                'slug' => $url !== '' ? Url::slug($url) : '',
                'url' => $url,
                'number' => $ep['episode'] ?? null,
                'label' => (string) ($ep['label'] ?? ''),
                'title' => (string) ($ep['name'] ?? ''),
                'overview' => (string) ($ep['overview'] ?? ''),
                'airDate' => (string) ($ep['air_date'] ?? ''),
                'thumbnail' => (string) ($ep['still'] ?? ''),
            ];
        }
        return $episodes;
    }

    /**
     * Player sources for one episode (or a movie when $episodeId is 0),
     * from the site's REST API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchServers(int $postId, int $episodeId): array
    {
        if ($postId <= 0) {
            return [];
        }
        $data = $this->http->postJson(
            $this->base . '/wp-json/dgt/v1/watch',
            ['post_id' => $postId, 'episode_id' => $episodeId]
        );
        if (!is_array($data) || !empty($data['gated'])) {
            return [];
        }

        $servers = [];
        $index = 0;
        foreach ($data['sources'] ?? [] as $source) {
            if (!is_array($source)) {
                continue;
            }
            $embed = (string) ($source['embed'] ?? '');
            if ($embed === '') {
                continue;
            }
            $download = (string) ($source['download'] ?? '');
            $kind = (string) ($source['kind'] ?? 'embed');
            // REST marks each source as an embed (streamable) and/or a
            // download (a download.php|download2.php link). Twin entries are
            // emitted twice: one show_embed row and one show_download row, so
            // the two roles must be read separately or download-only servers
            // leak into the streaming tab list.
            $showEmbed = array_key_exists('show_embed', $source)
                ? (bool) $source['show_embed']
                : ($kind !== 'download');
            $showDownload = array_key_exists('show_download', $source)
                ? (bool) $source['show_download']
                : ($download !== '');
            $servers[] = [
                'serverNumber' => $index,
                'displayNumber' => $index + 1,
                'name' => (string) ($source['server'] ?? ('Server ' . ($index + 1))),
                'src' => $embed,
                'download' => $download,
                'kind' => $kind,
                'showEmbed' => $showEmbed,
                'showDownload' => $showDownload,
                'language' => (string) ($source['language'] ?? ''),
                'quality' => (string) ($source['quality'] ?? ''),
                'originalSrc' => null,
                'isActive' => $index === 0,
            ];
            $index++;
        }
        return $servers;
    }

    // ------------------------------------------------------------------
    // Parsers
    // ------------------------------------------------------------------

    /**
     * The `data-snapshot` JSON blob present on detail/watch pages.
     *
     * @return array<string, mixed>
     */
    private function snapshot(DOMXPath $xp): array
    {
        foreach (['//*[@id="dgt-title"]', '//*[@id="dgt-watch"]'] as $expr) {
            $data = $this->jsonAttr($xp, $expr, 'data-snapshot');
            if ($data !== []) {
                return $data;
            }
        }
        return [];
    }

    /**
     * Decode a JSON-encoded data attribute on the first matching element.
     *
     * @return array<string, mixed>
     */
    private function jsonAttr(DOMXPath $xp, string $expr, string $attr): array
    {
        $node = Dom::one($xp, $expr);
        $raw = $node !== null ? Dom::attr($node, $attr) : null;
        if ($raw === null || $raw === '') {
            return [];
        }
        $data = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Season tabs from the detail page's data-tabs JSON.
     *
     * @return array<int, string> season number => label
     */
    private function seasonTabs(DOMXPath $xp): array
    {
        $tabs = $this->jsonAttr($xp, "//div[contains(@class,'dgt-tabs')]", 'data-tabs');
        if ($tabs === []) {
            $tabs = $this->jsonAttr($xp, "//*[@id='dgt-watch']", 'data-tabs');
        }
        $out = [];
        foreach ($tabs as $tab) {
            if (!is_array($tab) || !isset($tab['season'])) {
                continue;
            }
            $number = (int) $tab['season'];
            $out[$number] = (string) ($tab['label'] ?? ('Season ' . $number));
        }
        ksort($out);
        return $out;
    }

    /**
     * Common header metadata shared by series, movie and landing pages.
     *
     * @return array<string, mixed>
     */
    private function meta(DOMXPath $xp): array
    {
        $titleNode = Dom::one($xp, "//h1[contains(@class,'dgt-h1')]");
        $title = $titleNode !== null ? Dom::text($titleNode) : '';

        $img = Dom::one($xp, "//div[contains(@class,'dgt-hero__poster')]//img");
        $image = $img !== null ? (Dom::attr($img, 'data-src') ?? Dom::attr($img, 'src')) : null;

        $rating = null;
        $year = null;
        $duration = null;
        $metaNode = Dom::one($xp, "//div[contains(@class,'dgt-meta')]");
        if ($metaNode !== null) {
            $text = Dom::text($metaNode);
            if (preg_match('#([0-9]+(?:\.[0-9]+)?)#', $text, $m)) {
                $rating = $m[1];
            }
            if (preg_match('#\b(19|20)\d{2}\b#', $text, $m)) {
                $year = $m[0];
            }
            if (preg_match('#(\d+)\s*min#i', $text, $m)) {
                $duration = $m[1] . ' min';
            }
        }

        $descNode = Dom::one($xp, "//p[contains(@class,'dgt-overview')]");
        $description = $descNode !== null ? Dom::text($descNode) : null;

        $genres = [];
        $categories = [];
        foreach (Dom::q($xp, "//div[contains(@class,'dgt-chips')]//a[contains(@class,'dgt-chip')]") as $anchor) {
            $name = Dom::text($anchor);
            if ($name === '') {
                continue;
            }
            $genres[] = ['name' => $name, 'url' => Url::absolute($this->base, Dom::href($anchor))];
            $categories[] = ['name' => $name, 'url' => Url::absolute($this->base, Dom::href($anchor))];
        }

        $cast = [];

        return [
            'title' => $title,
            'image' => Url::absolute($this->base, $image),
            'rating' => $rating,
            'year' => $year,
            'duration' => $duration,
            'views' => null,
            'description' => $description,
            'categories' => $categories,
            'genres' => $genres,
            'cast' => $cast,
        ];
    }

    /** @param array<string, mixed> $snapshot @return array<int, array<string, mixed>> */
    private function genresFromSnapshot(array $snapshot): array
    {
        $out = [];
        foreach ($snapshot['genres'] ?? [] as $g) {
            if (is_string($g) && $g !== '') {
                $out[] = ['name' => $g, 'url' => null];
            }
        }
        return $out;
    }

    /**
     * A listing/search card (a.dgt-card).
     *
     * @return array<string, mixed>|null
     */
    private function parseCard(DOMXPath $xp, \DOMNode $card): ?array
    {
        $href = Dom::href($card);
        if ($href === null) {
            return null;
        }
        $url = Url::absolute($this->base, $href);
        $slug = Url::slug((string) $url);
        $contentType = str_contains((string) $url, '/movie-') ? 'movies' : 'series';

        $title = Dom::text(Dom::one($xp, ".//div[contains(@class,'dgt-card__title')]", $card));
        if ($title === '') {
            $title = Dom::text(Dom::one($xp, ".//img", $card));
        }

        $img = Dom::one($xp, ".//div[contains(@class,'dgt-card__poster')]//img", $card);
        $image = $img !== null ? (Dom::attr($img, 'data-src') ?? Dom::attr($img, 'src')) : null;

        $episodeLabel = Dom::text(Dom::one($xp, ".//span[contains(@class,'dgt-badge--ep')]", $card));
        $audio = Dom::text(Dom::one($xp, ".//span[contains(@class,'dgt-badge--audio')]", $card));
        $rating = Dom::text(Dom::one($xp, ".//span[contains(@class,'dgt-badge--rating')]", $card));

        $year = null;
        $metaNode = Dom::one($xp, ".//div[contains(@class,'dgt-card__meta')]", $card);
        if ($metaNode !== null && preg_match('#\b(19|20)\d{2}\b#', Dom::text($metaNode), $m)) {
            $year = $m[0];
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'url' => $url,
            'id' => (string) ($card instanceof \DOMElement ? ($card->getAttribute('data-id') ?: '') : ''),
            'image' => $image,
            'episode' => $episodeLabel,
            'episodeLabel' => $episodeLabel,
            'audio' => $audio,
            'rating' => $rating,
            'year' => $year,
            'contentType' => $contentType,
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function parseListing(string $html): array
    {
        $xp = Dom::xp(Dom::load($html));
        $items = [];
        foreach (Dom::q($xp, "//a[contains(@class,'dgt-card')]") as $card) {
            $parsed = $this->parseCard($xp, $card);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        $totalPages = 1;
        foreach (Dom::q($xp, "//nav[contains(@class,'dgt-pagination')]//a[contains(@class,'page-numbers')]") as $anchor) {
            $href = (string) Dom::href($anchor);
            if (preg_match('#[?&]pg=(\d+)#', $href, $m)) {
                $totalPages = max($totalPages, (int) $m[1]);
            }
        }
        return [Arr::uniqueBy($items, 'url'), $totalPages];
    }

    // ------------------------------------------------------------------
    // Source resolution
    // ------------------------------------------------------------------

    /**
     * Resolve each server's direct player URL. The site hides the real player
     * behind wrappers (player.php, /aaa/myth/play2.php) that expose it in
     * plain HTML — fetch them and extract the external player src.
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<int, array<string, mixed>>
     */
    private function withOriginalSrcs(array $servers): array
    {
        // Wrapper pages (player.php / play2.php) are followed up to a few
        // hops: player.php -> aaa/get/play.php -> real file, and play2.php
        // wraps a fetch.php JSON API. Anything not on the site's own host is
        // treated as the final embed/player URL (can't be fetched from here).
        $pending = [];
        foreach ($servers as $server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && $this->isWrapperSrc($src)) {
                $pending[] = $src;
            }
        }
        $jsonCache = [];
        $bodies = $this->fetchWrappers($pending, 2, $jsonCache);

        // Resolve download endpoints (download.php / download2.php) in one
        // batch: each points at an intermediate file.php link or an external
        // mirror that serves the actual file.
        $dlUrls = [];
        foreach ($servers as $server) {
            $dl = $server['download'] ?? null;
            if (is_string($dl) && $dl !== '' && $this->isWrapperSrc($dl)) {
                $dlUrls[] = $dl;
            }
        }
        $dlBodies = $this->fetchWrappers($dlUrls, 1, $jsonCache);

        foreach ($servers as &$server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && isset($bodies[$src]) && $bodies[$src] !== null) {
                $server['originalSrc'] = $this->resolveSrc($src, $bodies, $jsonCache, 4);
            }
            $dl = $server['download'] ?? null;
            if (is_string($dl) && $dl !== '' && isset($dlBodies[$dl]) && $dlBodies[$dl] !== null) {
                $server['downloadSrc'] = $this->resolveDownloadHref($dl, (string) $dlBodies[$dl]) ?? $dl;
            }
        }
        unset($server);
        return $servers;
    }

    /**
     * Fetch wrapper URLs plus their same-site wrappers, parallel per level.
     * Also pre-fetches each level's JSON source API into `$jsonCache` so the
     * per-server walk never issues a sequential request.
     */
    private function fetchWrappers(array $urls, int $depth, array &$jsonCache): array
    {
        $urls = array_values(array_unique(array_filter($urls)));
        $out = [];
        while ($depth-- > 0 && $urls !== []) {
            $bodies = $this->http->getMany($urls, 6);
            $next = [];
            $jsonUrls = [];
            foreach ($urls as $url) {
                $body = $bodies[$url] ?? null;
                if ($body === null) {
                    continue;
                }
                $out[$url] = $body;
                foreach ($this->wrapperJsonCandidates($url, $body) as $j) {
                    if (!array_key_exists($j, $jsonCache)) {
                        $jsonUrls[$j] = true;
                    }
                }
                foreach ($this->nextWrapperUrls($url, $body) as $n) {
                    if (!isset($out[$n]) && !in_array($n, $next, true)) {
                        $next[] = $n;
                    }
                }
            }
            if ($jsonUrls !== []) {
                $jbodies = $this->http->getMany(array_keys($jsonUrls), 6);
                foreach ($jbodies as $u => $b) {
                    $decoded = is_string($b) ? json_decode($b, true) : null;
                    $jsonCache[$u] = is_array($decoded) ? $decoded : null;
                }
            }
            $urls = $next;
        }
        return $out;
    }

    /** Same-site wrapper URLs referenced by a wrapper body (one hop). */
    private function nextWrapperUrls(string $base, string $html): array
    {
        $found = [];
        $xp = Dom::xp(Dom::load($html));
        foreach (Dom::q($xp, '//iframe[@src]') as $frame) {
            $src = Dom::attr($frame, 'src');
            if ($src === null || $src === '') {
                continue;
            }
            $abs = Url::absolute($base, $src);
            if ($abs !== null && $this->isSameBase($abs) && $this->isWrapperSrc($abs)) {
                $found[] = $abs;
            }
        }
        return $found;
    }

    /** Whether a URL is on the provider's own host. */
    private function isSameBase(string $url): bool
    {
        return str_starts_with($url, $this->base);
    }

    /** Scheme+host of a URL, used to resolve relative paths without query. */
    private function originOf(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    }

    /** Resolve a download wrapper page to its actual download target. */
    private function resolveDownloadHref(string $base, string $html): ?string
    {
        // Self-hosted downloads point at file.php. Hosted mirrors (gdflix,
        // hubcloud, …) hand back an external URL, so accept any absolute link
        // that is not the provider's own page.
        $xp = Dom::xp(Dom::load($html));
        foreach (Dom::q($xp, '//a[@href]') as $a) {
            $href = Dom::attr($a, 'href');
            if ($href === null || $href === '') {
                continue;
            }
            if (str_contains($href, '/file.php')) {
                return Url::absolute($base, $href);
            }
            if (preg_match('#^https?://#i', $href) && !str_starts_with($href, $this->base)) {
                return $href;
            }
        }
        return null;
    }

    /** Whether a server src is one of the site's own player wrappers. */
    private function isWrapperSrc(string $src): bool
    {
        return str_starts_with($src, $this->base . '/player.php')
            || str_contains($src, '/player.php?url=')
            || str_contains($src, '/aaa/myth/play2.php')
            || str_contains($src, '/aaa/myth/dl2.php')
            || str_contains($src, '/download.php?')
            || str_contains($src, '/download2.php?')
            || str_starts_with($src, $this->base . '/aaa/get/')
            || str_starts_with($src, $this->base . '/embed/');
    }

    /**
     * Walk a wrapper chain to the final playable URL. `$bodies` holds the
     * fetched HTML of each wrapper (keyed by URL); each hop's body is scanned
     * for the next URL. A hop to another same-site wrapper continues; anything
     * else (external player/embed or a raw media file) is the result.
     */
    private function resolveSrc(string $src, array $bodies, array $jsonCache, int $depth): ?string
    {
        while ($depth-- > 0) {
            $body = $bodies[$src] ?? null;
            if ($body === null) {
                return null;
            }
            $next = $this->extractFromBody($src, (string) $body, $jsonCache);
            if ($next === null || $next === $src) {
                return null;
            }
            if ($this->isWrapperSrc($next)) {
                $src = $next;
                continue;
            }
            return $next;
        }
        return null;
    }

    /**
     * Extract the next playable/player URL from one wrapper body: a fetch()
     * JSON API (fetch.php / the wrapper's own URL with &fetch=1), an explicit
     * `file:` JS source, the first iframe src, or a raw media URL. May return
     * another same-site wrapper URL for the caller to follow.
     */
    private function extractFromBody(string $embedUrl, string $html, array $jsonCache): ?string
    {
        // Wrapper pages resolve their sources through a JSON API called with
        // fetch()/ajax: either an explicit fetch.php endpoint (play2.php) or
        // the wrapper's own URL with &fetch=1 (aaa/get/play.php).
        foreach ($this->wrapperJsonCandidates($embedUrl, $html) as $jurl) {
            $json = $jsonCache[$jurl] ?? null;
            if (!is_array($json)) {
                continue;
            }
            $file = $this->pickFile($json['sources'] ?? null)
                ?? $this->pickResults($json['results'] ?? null);
            if ($file !== null) {
                return $file;
            }
        }
        // Explicit `file:` JSON/JS source (some wrappers inline it).
        if (preg_match('#file\s*:\s*(?:\[[^\]]*?)?[\"\']([^\"\']+)#i', $html, $m)) {
            $file = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if (preg_match('#^https?://#i', $file)) {
                return $file;
            }
        }
        // An iframe src: another same-site wrapper (continue) or the external
        // player (stop).
        $xp = Dom::xp(Dom::load($html));
        foreach (Dom::q($xp, '//iframe[@src]') as $frame) {
            $src = Dom::attr($frame, 'src') ?? Dom::attr($frame, 'data-src');
            if ($src !== null && $src !== '') {
                $abs = Url::absolute($embedUrl, $src);
                if ($abs !== null) {
                    return $abs;
                }
            }
        }
        // Last: a raw media URL anywhere in the body.
        if (preg_match('#https?://[^\s"\'<>\\\\]+\.(?:m3u8|mp4|webm)[^\s"\'<>\\\\]*#i', $html, $m)) {
            return $m[0];
        }
        return null;
    }

    /** Pick the smallest/plainest file from a playerjs sources array. */
    private function pickFile($sources): ?string
    {
        if (!is_array($sources) || $sources === []) {
            return null;
        }
        $best = null;
        foreach ($sources as $s) {
            $url = is_array($s) ? ($s['url'] ?? $s['file'] ?? '') : (string) $s;
            if (!is_string($url) || $url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            // Prefer non-HEVC; otherwise keep the first.
            if ($best === null) {
                $best = $url;
            }
            if (!str_contains(strtolower($url), 'hevc')) {
                return $url;
            }
        }
        return $best;
    }

    /** Pick a file URL from a {name: {url,label}} results map. */
    private function pickResults($results): ?string
    {
        if (!is_array($results)) {
            return null;
        }
        // Prefer direct/cloud sources over download mirrors.
        $order = ['cloud_r2', 'direct_mgt', 'instant_dl'];
        foreach ($order as $key) {
            $url = $results[$key]['url'] ?? null;
            if (is_string($url) && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
        foreach ($results as $r) {
            $url = is_array($r) ? ($r['url'] ?? null) : null;
            if (is_string($url) && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
        return null;
    }

    /**
     * Candidate JSON source APIs for a wrapper page. Handles the two shapes
     * seen in the wild: an explicit fetch("…/fetch.php?id=…") call, and the
     * wrapper's own URL re-requested with &fetch=1. Returns the list of
     * absolute same-site URLs whose JSON holds the sources.
     */
    private function wrapperJsonCandidates(string $embedUrl, string $html): array
    {
        $candidates = [];
        if (preg_match('#fetch\(\s*["\']([^"\']+)["\']#i', $html, $m)) {
            $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            // Skip the self-referential `?url=…&fetch=1` style: handled below.
            if (!str_starts_with($raw, '?')) {
                $abs = Url::absolute($this->originOf($embedUrl), $raw);
                if ($abs !== null && $this->isSameBase($abs)) {
                    $candidates[] = $abs;
                }
            }
        }
        if (str_contains($html, 'fetch(') && str_contains($html, 'fetch=1') && str_contains($embedUrl, '?')) {
            $candidates[] = $embedUrl . '&fetch=1';
        }
        return array_values(array_unique($candidates));
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
