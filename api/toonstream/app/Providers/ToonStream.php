<?php

declare(strict_types=1);

namespace ToonStreamApi\Providers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ToonStreamApi\Core\Config;
use ToonStreamApi\Utils\Arr;
use ToonStreamApi\Utils\Dom;
use ToonStreamApi\Utils\HttpClient;
use ToonStreamApi\Utils\Url;

/**
 * ToonStream provider: every endpoint maps to real pages of the target site.
 *
 * Site map (verified against toon-stream.site):
 *   /home                         → sections of cards (Fresh Drop, Random series, …)
 *   /series/{slug}                → details + seasons + season-1 episodes
 *   /series/{slug}/season/{n}     → episode list of season n (renders into #episode_by_temp)
 *   /episode/{slug}               → player with per-language server iframes
 *   /s?q={q}[&page={n}]           → search results
 *   /category/{path}[?type=all&page={n}] → category / genre / network / language listings
 *   /cast/{name}[?type=all&page={n}]    → cast listings
 *   /letter/{letter}[?page={n}]         → alphabetical listings
 *   /movies/{slug}                → movie detail (same layout as series)
 */
final class ToonStream
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
        $html = $this->http->get($this->base . '/home');
        [$latestEpisodes, $latestSeries, $movies, $featured] = [[], [], [], []];

        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        foreach (Dom::q($xp, "//section[contains(@class,'wdgt-home')]") as $section) {
            $name = Dom::text(Dom::one($xp, ".//*[contains(@class,'section-title')]", $section));

            $cards = [];
            foreach (Dom::q($xp, ".//article[contains(@class,'post')]", $section) as $article) {
                $card = $this->parseCard($xp, $article);
                if ($card !== null) {
                    $cards[] = $card;
                }
            }
            // "Watch History" is a client-side JS list — always empty server-side.
            if ($name === '' || $name === 'Watch History' || $cards === []) {
                continue;
            }
            foreach ($cards as $card) {
                if ($card['contentType'] === 'episode') {
                    $latestEpisodes[] = $card;
                } elseif ($card['contentType'] === 'series') {
                    $latestSeries[] = $card;
                } else {
                    $movies[] = $card;
                }
            }
            if ($featured === []) {
                $featured = $cards;
            }
        }

        $languages = [];
        foreach (Dom::q($xp, "//a[starts-with(@href,'/category/language/')]") as $anchor) {
            $href = Dom::href($anchor);
            // Skip the bare "Language" dropdown parent — it has no language slug.
            if ($href === null || preg_match('#/category/language/?$#', $href)) {
                continue;
            }
            $languages[] = [
                'name' => Dom::text($anchor),
                'url' => Url::absolute($this->base, $href),
            ];
        }

        // Least anime fix: for episode cards, provide both original slug with -{num}x{num} and normalized series slug without suffix (movies not stripped)
        foreach ($latestEpisodes as &$c) {
            $orig = $c['slug'] ?? '';
            $c['originalSlug'] = $orig;
            $c['originalId'] = $c['id'] ?? '';
            // Hardcode href parsing: remove baseUrl + episode/ prefix, then strip suffix for series link (movies keep)
            if (($c['contentType'] ?? '') !== 'movies') {
                $c['slug'] = preg_replace('/-\d+x\d+$/', '', $orig);
                $c['id'] = preg_replace('/-\d+x\d+$/', '', $c['id'] ?? '');
            }
            $c['episodeSlug'] = $orig; // keep original for /episode playback if needed
        }
        unset($c);
        return [
            'latestEpisodes' => Arr::uniqueBy($latestEpisodes, 'url'),
            'latestSeries' => Arr::uniqueBy($latestSeries, 'url'),
            'languages' => Arr::uniqueBy($languages, 'url'),
            'featured' => Arr::uniqueBy($featured, 'url'),
            'movies' => Arr::uniqueBy($movies, 'url'),
        ];
    }

    /**
     * The site's index page (https://toon-stream.site/) is a static
     * landing/FAQ article, not a content grid. Return it as structured
     * sections: intro paragraphs, then h2/h3 headings with their paragraphs.
     */
    public function index(): array
    {
        $html = $this->http->get($this->base . '/');
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $titleNode = Dom::one($xp, "//title");
        $title = $titleNode !== null ? Dom::text($titleNode) : 'ToonStream';

        $container = Dom::one($xp, "//div[@id='home-article']");
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
    }

    // ------------------------------------------------------------------
    // Series / movie detail
    // ------------------------------------------------------------------

    /**
     * @param string $slug         series (or movie) slug
     * @param string|null $seasons '1' (default) | 'all' | '1,2,3' | '2-4'
     * @param bool $withSources    when true, fetch each episode page and attach iframe servers
     * @param string|null $server  server filter: '0,1,2' | '0-4' | 'all' | 'x,ruby'
     * @param string $kind         'series' or 'movies' (both share the same layout)
     */
    public function detail(string $slug, ?string $seasons, bool $withSources, ?string $server, string $kind = 'series'): array
    {
        $html = $this->http->get($this->base . '/' . $kind . '/' . $slug);
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $titleNode = Dom::one($xp, "//h1[contains(@class,'entry-title')]");
        $title = $titleNode !== null ? Dom::text($titleNode) : $slug;

        $meta = $this->meta($xp);

        // Seasons
        $availableSeasons = [];
        $seasonUrls = [];
        $inlineSeason = null;
        foreach (Dom::q($xp, "//a[contains(@class,'season-btn')]") as $button) {
            $number = (int) (Dom::attr($button, 'data-season') ?? 0);
            $availableSeasons[] = [
                'seasonNumber' => $number,
                'name' => Dom::text($button),
            ];
            $url = Dom::attr($button, 'data-url');
            if ($url !== null) {
                $seasonUrls[$number] = $url;
            }
            if (str_contains((string) $button->getAttribute('class'), 'active')) {
                $inlineSeason = $number;
            }
        }
        $totalSeasons = count($availableSeasons);
        $seasonNumbers = array_column($availableSeasons, 'seasonNumber');

        $requested = $this->parseSeasons($seasons, $seasonNumbers);
        $hasSeasonUi = $totalSeasons > 0;
        $inlineSeason = $inlineSeason ?? ($requested[0] ?? 1);

        $seasons = [];
        $fetchedEpisodes = 0;
        foreach ($requested as $number) {
            if (!$hasSeasonUi) {
                // Movie-style detail: no season buttons, single episode list.
                $list = Dom::one($xp, "//ul[@id='episode_by_temp']");
                $episodes = $list !== null ? $this->parseEpisodeList($xp, $list, $title) : [];
                if ($episodes === [] && $kind === 'movies') {
                    // The movie itself is the playable unit, hosted by the
                    // page's own player — no separate episode page exists.
                    $episodes = [[
                        'title' => $title,
                        'episodeNumber' => 'Movie',
                        'image' => $meta['image'],
                        'url' => $this->base . '/' . $kind . '/' . $slug,
                        'slug' => $slug,
                    ]];
                    $pageServers = $this->withOriginalSrcs($this->filterServers($this->servers($xp), $server));
                    foreach ($episodes as &$episode) {
                        $episode['servers'] = $pageServers;
                    }
                    unset($episode);
                }
            } elseif ($number === $inlineSeason && !$withSources) {
                $list = Dom::one($xp, "//ul[@id='episode_by_temp']");
                $episodes = $list !== null ? $this->parseEpisodeList($xp, $list, $title) : [];
            } else {
                $seasonPath = $seasonUrls[$number] ?? ('/' . $kind . '/' . $slug . '/season/' . $number);
                $episodes = $this->parseSeasonPage(Url::absolute($this->base, $seasonPath), $title);
            }
            $fetchedEpisodes += count($episodes);
            $seasons[] = [
                'seasonNumber' => $number,
                'name' => $kind === 'movies' && !$hasSeasonUi ? 'Movie' : $this->seasonName($availableSeasons, $number),
                'episodes' => $episodes,
            ];
        }
        // Movie with no season UI and empty requested (fresh cache) - synthesize single movie episode with servers
        if ($kind === 'movies' && empty($seasons)) {
            $pageServers = $this->withOriginalSrcs($this->filterServers($this->servers($xp), $server));
            $seasons[] = [
                'seasonNumber' => 1,
                'name' => 'Movie',
                'episodes' => [[
                    'title' => $title,
                    'episodeNumber' => 'Movie',
                    'image' => $meta['image'],
                    'url' => $this->base . '/' . $kind . '/' . $slug,
                    'slug' => $slug,
                    'servers' => $pageServers,
                ]],
            ];
        }

        // Optional: server sources per episode (parallel fetch, cached).
        if ($withSources && $kind !== 'movies') {
            $urls = [];
            foreach ($seasons as $season) {
                foreach ($season['episodes'] as $episode) {
                    $urls[] = $episode['url'];
                }
            }
            $bodies = $this->http->getMany(array_values($urls), 6);
            foreach ($seasons as &$season) {
                foreach ($season['episodes'] as &$episode) {
                    $body = $bodies[$episode['url']] ?? null;
                    $episode['servers'] = $body !== null
                        ? $this->withOriginalSrcs($this->filterServers($this->serversFromHtml($body), $server))
                        : [];
                }
            }
            unset($season, $episode);
        }

        $results = [
            'slug' => $slug,
            'title' => $title,
            'image' => $meta['image'],
            'rating' => $meta['rating'],
            'year' => $meta['year'],
            'duration' => $meta['duration'],
            'description' => $meta['description'],
            'totalSeasons' => $totalSeasons,
            'categories' => $meta['categories'],
            'cast' => $meta['cast'],
            'tags' => $meta['tags'],
            'availableSeasons' => $availableSeasons,
            'seasons' => $seasons,
        ];
        if ($hasSeasonUi) {
            // README contract: totalEpisodes is always present on series
            // detail. Count episodes across every season (fetching only the
            // season pages that weren't requested).
            $total = $fetchedEpisodes;
            foreach ($seasonNumbers as $number) {
                if (!in_array($number, $requested, true)) {
                    $seasonPath = $seasonUrls[$number] ?? ('/' . $kind . '/' . $slug . '/season/' . $number);
                    $total += count($this->parseSeasonPage(Url::absolute($this->base, $seasonPath), $title));
                }
            }
            $results['totalEpisodes'] = $total;
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Episode
    // ------------------------------------------------------------------

    public function episode(string $slug, ?string $server): array
    {
        $html = $this->http->get($this->base . '/episode/' . $slug);
        $doc = Dom::load($html);
        $xp = Dom::xp($doc);

        $titleNode = Dom::one($xp, "//h1[contains(@class,'entry-title')]");
        $title = $titleNode !== null ? Dom::text($titleNode) : $slug;
        $meta = $this->meta($xp);

        // Navigation: prev/next episode + series page. The player nav bar
        // places the anchors right after <!-- PREVIOUS --> / <!-- NEXT -->
        // comments, so a missing previous episode (episode 1) can't be
        // confused with the next one.
        $previousEpisode = null;
        $nextEpisode = null;
        $seriesPage = null;
        foreach (Dom::q($xp, "//comment()[contains(.,'PREVIOUS') or contains(.,'NEXT')]") as $comment) {
            $isPrev = stripos((string) $comment->nodeValue, 'PREVIOUS') !== false;
            // Walk only whitespace text nodes: the next element OR the next
            // marker comment (site emits back-to-back markers when an
            // episode is missing) stops the walk. nodeType checks are used
            // instead of instanceof — PHP 8.4+ DOM wrappers returned via
            // nextSibling fail instanceof for comments.
            $sibling = $comment->nextSibling;
            while ($sibling !== null && $sibling->nodeType !== XML_ELEMENT_NODE && $sibling->nodeType !== XML_COMMENT_NODE) {
                $sibling = $sibling->nextSibling;
            }
            $href = $sibling !== null && $sibling->nodeType === XML_ELEMENT_NODE ? Dom::href($sibling) : null;
            if ($href !== null && str_starts_with($href, '/episode/')) {
                if ($isPrev) {
                    $previousEpisode = Url::absolute($this->base, $href);
                } else {
                    $nextEpisode = Url::absolute($this->base, $href);
                }
            }
        }
        if ($seriesPage === null) {
            $nav = Dom::one($xp, "//*[contains(@class,'epsdsnv')]");
            $anchor = $nav !== null
                ? Dom::one($xp, ".//a[starts-with(@href,'/series/')]", $nav)
                : null;
            $anchor ??= Dom::one($xp, "//a[starts-with(@href,'/series/')]");
            if ($anchor !== null) {
                $seriesPage = Url::absolute($this->base, Dom::href($anchor));
            }
        }

        $servers = $this->servers($xp);
        $filtered = $this->withOriginalSrcs($this->filterServers($servers, $server));

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
            'servers' => $filtered,
        ];
    }

    // ------------------------------------------------------------------
    // Search & listings (category / cast / letter / movies)
    // ------------------------------------------------------------------

    public function search(string $query, int $page = 1): array
    {
        $url = $this->base . '/s?q=' . rawurlencode($query);
        if ($page > 1) {
            $url .= '&page=' . $page;
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
    }

    /**
     * Shared listing for category / cast / letter / movies pages.
     *
     * @param bool $typeParam append the site's ?type=all filter (category/cast only)
     */
    public function listing(string $path, int $page = 1, bool $typeParam = true): array
    {
        $url = $this->base . $path;
        if ($typeParam) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'type=all';
        }
        if ($page > 1) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'page=' . $page;
        }
        $html = $this->http->get($url);
        [$items, $totalPages] = $this->parseListing($html);
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
     * Common header metadata shared by series, movie and episode pages.
     *
     * @return array<string, mixed>
     */
    private function meta(DOMXPath $xp): array
    {
        $image = null;
        $img = Dom::one($xp, "//div[contains(@class,'post-thumbnail') and contains(@class,'alg-ss')]//img");
        if ($img !== null) {
            $image = Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src');
        }

        $num = Dom::one($xp, "//span[contains(@class,'vote')]//span[contains(@class,'num')]");
        $rating = $num !== null ? Dom::text($num) : null;

        $yearEl = Dom::one($xp, "//div[contains(@class,'entry-meta')]//span[contains(@class,'year')]");
        $year = $yearEl !== null ? Dom::text($yearEl) : null;

        $durEl = Dom::one($xp, "//div[contains(@class,'entry-meta')]//span[contains(@class,'duration')]");
        $duration = $durEl !== null ? Dom::text($durEl) : null;

        $descEl = Dom::one($xp, "//div[contains(@class,'description')]");
        $description = $descEl !== null ? Dom::text($descEl) : null;

        $categories = [];
        foreach (Dom::q($xp, "//span[contains(@class,'genres')]//a") as $anchor) {
            $categories[] = [
                'name' => Dom::text($anchor),
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        $tags = [];
        foreach (Dom::q($xp, "//span[contains(@class,'tag')]//a") as $anchor) {
            $tags[] = [
                'name' => Dom::text($anchor),
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        $cast = [];
        foreach (Dom::q($xp, "//ul[contains(@class,'cast-lst')]//a[contains(@href,'/cast/')]") as $anchor) {
            $cast[] = [
                'name' => Dom::text($anchor),
                'url' => Url::absolute($this->base, Dom::href($anchor)),
            ];
        }

        return [
            'image' => Url::absolute($this->base, $image),
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
        $vote = Dom::one($xp, ".//*[contains(@class,'vote')]", $article);

        if (str_starts_with($href, '/episode')) {
            $type = 'episode';
        } elseif (str_starts_with($href, '/movies')) {
            $type = 'movies';
        } else {
            $type = 'series';
        }

        $card = [
            'id' => 'post-' . Url::slug($href),
            'title' => Dom::text($titleNode),
            'image' => $img !== null ? Url::absolute($this->base, Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src')) : null,
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
            'rating' => $vote !== null ? Arr::firstNumber(Dom::text($vote)) : null,
            'contentType' => $type,
            'categories' => [],
            'tags' => [],
        ];

        $badge = Dom::one($xp, ".//*[contains(@class,'post-ql')]", $article);
        if ($badge !== null && Dom::text($badge) !== '') {
            $card['badge'] = Dom::text($badge);
        }
        $epLabel = Dom::one($xp, ".//*[contains(@class,'year')]", $article);
        if ($epLabel !== null && Dom::text($epLabel) !== '') {
            $card['episode'] = Dom::text($epLabel);
        }

        return $card;
    }

    /**
     * One episode row from a season list (article.post.episodes).
     *
     * @return array<string, mixed>|null
     */
    private function parseEpisodeItem(DOMXPath $xp, DOMElement $article, string $seriesTitle): ?array
    {
        $link = Dom::one($xp, ".//a[contains(@class,'lnk-blk')]", $article);
        $href = Dom::href($link);
        if ($href === null) {
            return null;
        }

        $numEl = Dom::one($xp, ".//*[contains(@class,'num-epi')]", $article);
        $episodeNumber = Dom::text($numEl);

        $img = Dom::one($xp, ".//figure//img", $article);
        $image = $img !== null ? (Dom::attr($img, 'src') ?? Dom::attr($img, 'data-src')) : null;

        return [
            'title' => $seriesTitle . ($episodeNumber !== '' ? ' ' . $episodeNumber : ''),
            'episodeNumber' => $episodeNumber,
            'image' => Url::absolute($this->base, $image),
            'url' => Url::absolute($this->base, $href),
            'slug' => Url::slug($href),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function parseEpisodeList(DOMXPath $xp, DOMNode $context, string $seriesTitle): array
    {
        $episodes = [];
        foreach (Dom::q($xp, ".//li//article[contains(@class,'post')][.//a[contains(@class,'lnk-blk')]]", $context) as $article) {
            $episode = $this->parseEpisodeItem($xp, $article, $seriesTitle);
            if ($episode !== null) {
                $episodes[] = $episode;
            }
        }
        return $episodes;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseSeasonPage(string $url, string $seriesTitle): array
    {
        $html = $this->http->get($url);
        $doc = Dom::load($html);
        return $this->parseEpisodeList(Dom::xp($doc), $doc, $seriesTitle);
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
        foreach (Dom::q($xp, "//nav[contains(@class,'pagination')]//a[contains(@class,'page-link')]") as $anchor) {
            $href = Dom::href($anchor);
            if ($href !== null && preg_match('/[?&]page=(\d+)/', $href, $m)) {
                $totalPages = max($totalPages, (int) $m[1]);
            }
        }
        return [$items, $totalPages];
    }

    // ------------------------------------------------------------------
    // Streaming servers
    // ------------------------------------------------------------------

    /**
     * Server tabs live under per-language blocks (.lrt), each listing
     * anchors to player iframes (#options-N).
     *
     * @return array<int, array<string, mixed>>
     */
    private function servers(DOMXPath $xp): array
    {
        $langNames = [];
        foreach (Dom::q($xp, "//div[contains(@class,'btr')]//span[contains(@class,'btn')][@tab]") as $tab) {
            $langNames[Dom::attr($tab, 'tab')] = Dom::text($tab);
        }

        $servers = [];
        $index = 0;
        foreach (Dom::q($xp, "//div[contains(@class,'lrt')][@id]") as $block) {
            $language = $langNames[$block->getAttribute('id')] ?? 'Unknown';
            foreach (Dom::q($xp, ".//a[starts-with(@href,'#options-')]", $block) as $tab) {
                $optionId = (string) substr((string) Dom::href($tab), 1); // '#options-0' -> 'options-0'
                $nameEl = Dom::one($xp, ".//*[contains(@class,'server')]", $tab);
                $name = $nameEl !== null ? Dom::text($nameEl) : 'Server';
                $active = str_contains((string) $tab->getAttribute('class'), 'on');

                $iframe = Dom::one($xp, "//div[@id='" . $optionId . "']//iframe");
                $src = $iframe !== null ? (Dom::attr($iframe, 'src') ?? Dom::attr($iframe, 'data-src')) : null;

                $servers[] = [
                    'serverNumber' => $index,
                    'displayNumber' => $index + 1,
                    'name' => $name,
                    'language' => $language,
                    'src' => Url::absolute($this->base, $src),
                    'originalSrc' => null,
                    'isActive' => $active,
                ];
                $index++;
            }
        }
        return $servers;
    }

    /** @return array<int, array<string, mixed>> */
    private function serversFromHtml(string $html): array
    {
        $doc = Dom::load($html);
        return $this->servers(Dom::xp($doc));
    }

    /**
     * Resolve each server's direct player URL. toon-stream /embed/ pages
     * are thin wrappers around a nested player iframe (rubystm.com,
     * gdmirrorbot.nl, …), so we fetch that page one step further and expose
     * the real src as `originalSrc`. Servers whose src already targets an
     * external player keep it as originalSrc; unresolvable wrappers leave
     * originalSrc null.
     *
     * @param array<int, array<string, mixed>> $servers
     * @return array<int, array<string, mixed>>
     */
    public function withOriginalSrcs(array $servers): array
    {
        $embedUrls = [];
        foreach ($servers as $server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && str_starts_with($src, $this->base . '/embed/')) {
                $embedUrls[] = $src;
            }
        }
        $bodies = $embedUrls !== [] ? $this->http->getMany($embedUrls, 6) : [];
        foreach ($servers as &$server) {
            $src = $server['src'] ?? null;
            if (is_string($src) && $src !== '' && isset($bodies[$src]) && $bodies[$src] !== null) {
                $server['originalSrc'] = $this->embedOriginalSrc($src, (string) $bodies[$src]);
            } elseif (is_string($src) && $src !== '') {
                // Not a toon-stream wrapper — the src already is the original.
                $server['originalSrc'] = $src;
            }
        }
        unset($server);
        return $servers;
    }

    /**
     * Extract the nested player URL from a toon-stream /embed/ wrapper page.
     * Prefers the player iframe inside .Video, falls back to any iframe,
     * then to a <video> <source>.
     */
    private function embedOriginalSrc(string $embedUrl, string $html): ?string
    {
        $xp = Dom::xp(Dom::load($html));
        $frames = Dom::q($xp, "//div[contains(@class,'Video')]//iframe");
        if ($frames === []) {
            $frames = Dom::q($xp, "//iframe");
        }
        if ($frames === []) {
            $frames = Dom::q($xp, "//video//source");
        }
        if ($frames === []) {
            return null;
        }
        $src = Dom::attr($frames[0], 'src') ?? Dom::attr($frames[0], 'data-src');
        if ($src === null || $src === '') {
            return null;
        }
        return Url::absolute($embedUrl, $src);
    }

    /**
     * Server spec: 'all' (default), numeric list '0,1,2', range '0-4',
     * or comma-separated server names 'x,ruby'.
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
