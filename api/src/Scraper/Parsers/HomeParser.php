<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses the /home listing page: spotlight carousel, latest grids, languages.
 */
final class HomeParser
{
    private HtmlParser $html;

    public function __construct(HtmlParser $html)
    {
        $this->html = $html;
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $series = $this->parseLatest('series');
        $movies = $this->parseLatest('movies');

        return [
            'spotlight' => $this->parseSpotlight(),
            'latest' => [
                'all' => $this->parseLatest('all'),
                'series' => $series,
                'movies' => $movies,
            ],
            'languages' => $this->parseLanguages(),
        ];
    }

    /**
     * Latest grid for one type.
     *
     * The redesign serves two shapes for /home: page 1 carries per-section
     * blocks (`.at-sl-section[aria-label="Latest Series|Movies|Episodes"]`),
     * while page 2+ swaps them for tab-targeted grids (`#lat-grid-{all,series,movies}`).
     * Prefer the explicit grid when present, else fall back to the section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseLatest(string $type): array
    {
        $grid = $this->parseGrid(sprintf(
            '//div[@id="lat-grid-%s"]//div[contains(@class,"anime-card")]',
            $type
        ));
        if ($grid !== []) {
            return $grid;
        }

        if ($type === 'all') {
            // No "all" section exists on page 1: series + movies is the set.
            return $this->dedupeCards(array_merge(
                $this->parseLatest('series'),
                $this->parseLatest('movies')
            ));
        }

        return $this->parseSection($type);
    }

    /**
     * Cards inside the page-1 `Latest Series` / `Latest Movies` sections.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseSection(string $type): array
    {
        $label = $type === 'movies' ? 'Latest Movies' : 'Latest Series';
        $xpath = '//section[contains(@class,"at-sl-section")][@aria-label="' . $label . '"]'
            . '//div[contains(@class,"anime-card")]';

        return $this->parseGrid($xpath);
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<int, array<string, mixed>>
     */
    private function dedupeCards(array $cards): array
    {
        $seen = [];
        $out = [];
        foreach ($cards as $card) {
            $key = (string) ($card['url'] ?? $card['title'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $card;
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSpotlight(): array
    {
        $items = [];
        $slides = $this->html->query('//div[contains(@class,"sa-spotlight-swiper")]//div[contains(@class,"swiper-slide")]');

        foreach ($slides as $slide) {
            $watchUrl = $this->html->firstUrlWithin($slide, ['.//a[contains(@class,"sa-btn-main")]/@href']);
            $detailsUrl = $this->html->firstUrlWithin($slide, ['.//a[contains(@class,"sa-btn-alt")]/@href']);
            $logoAlt = $this->html->firstAttrWithin($slide, ['.//img[contains(@class,"sa-logo-img")]/@alt'], 'alt');

            $items[] = [
                'title' => $this->resolveSpotlightTitle($logoAlt, $detailsUrl, $watchUrl),
                'rank' => $this->html->firstTextWithin($slide, ['.//span[contains(@class,"sa-rank-tag")]']),
                'badge' => $this->html->firstTextWithin($slide, ['.//span[contains(@class,"sa-badge")]']),
                'genres' => $this->html->firstTextWithin($slide, ['.//span[contains(@class,"sa-genres")]']),
                'description' => $this->html->firstTextWithin($slide, ['.//p[contains(@class,"sa-description")]']),
                'watch_url' => $watchUrl,
                'details_url' => $detailsUrl,
                'slug' => $this->slugFromUrl($detailsUrl) ?? $this->slugFromUrl($watchUrl),
                'banner' => $this->html->firstImageUrlWithin($slide, ['.//img[contains(@class,"sa-card-bg")]/@data-src', './/img[contains(@class,"sa-card-bg")]/@src']),
                'poster' => $this->html->firstImageUrlWithin($slide, ['.//img[contains(@class,"sa-logo-img")]/@data-src', './/img[contains(@class,"sa-logo-img")]/@src']),
            ];
        }

        return $items;
    }

    /**
     * Spotlight slides ship no title text node: the logo <img alt> is usually
     * generic ("Logo"), so fall back to humanizing the details/watch URL slug.
     */
    private function resolveSpotlightTitle(?string $logoAlt, ?string $detailsUrl, ?string $watchUrl): ?string
    {
        if ($logoAlt !== null && !$this->isGenericAlt($logoAlt)) {
            return $logoAlt;
        }

        return $this->slugToTitle($detailsUrl ?? '') ?? $this->slugToTitle($watchUrl ?? '');
    }

    private function isGenericAlt(string $alt): bool
    {
        return in_array(strtolower(trim($alt)), ['', 'logo', 'spotlight banner'], true);
    }

    /**
     * Humanize a URL slug into a display title:
     * "demon-slayer-...-watch-online-download" -> "Demon Slayer ...".
     */
    private function slugToTitle(string $url): ?string
    {
        $slug = $this->slugFromUrl($url);
        if ($slug === null) {
            return null;
        }

        $title = preg_replace('/(?:[-_](?:watch[-_]online|download))+$/', '', $slug) ?? $slug;
        $title = preg_replace('/[-_]+/', ' ', $title) ?? $title;
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);

        return $title === '' ? null : ucwords($title);
    }

    /**
     * Path basename used as a stable client-side key (or slug input).
     */
    private function slugFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $slug = trim($path, '/');
        if ($slug === '') {
            return null;
        }

        $base = basename($slug);

        return $base === '' ? null : $base;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseGrid(string $cardXpath): array
    {
        $items = [];
        foreach ($this->html->query($cardXpath) as $card) {
            $items[] = [
                'title' => $this->html->firstTextWithin($card, ['.//h2[contains(@class,"card-title")]']),
                'url' => $this->html->firstUrlWithin($card, ['.//a[contains(@class,"anime-card")]/@href', './/a[1]/@href']),
                'poster' => $this->html->firstImageUrlWithin($card, [
                    './/img[contains(@class,"wp-post-image")]/@data-src',
                    './/img[contains(@class,"wp-post-image")]/@src',
                ]),
                'episode_badge' => $this->html->firstTextWithin($card, ['.//span[contains(@class,"se-badge")]']),
                'languages_badge' => $this->html->firstTextWithin($card, ['.//span[contains(@class,"meta-badge")]']),
            ];
        }

        return $items;
    }

    /**
     * Languages from the sidebar "Languages" drop-down.
     *
     * @return array<int, array{name: string, url: string}>
     */
    private function parseLanguages(): array
    {
        $items = [];

        $xpath = '//details[contains(@class,"ak-native-drop")][.//summary//span[normalize-space(.)="Languages"]]//a[contains(@class,"ak-sub-link")]';

        foreach ($this->html->query($xpath) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $items[] = [
                'name' => HtmlParser::clean($node->textContent),
                'url' => $this->html->normalizeUrl($node->getAttribute('href')),
            ];
        }

        return $items;
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}