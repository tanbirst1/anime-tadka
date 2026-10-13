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
        return [
            'spotlight' => $this->parseSpotlight(),
            'latest' => [
                'all' => $this->parseGrid('//div[@id="lat-grid-all"]//div[contains(@class,"anime-card")]'),
                'series' => $this->parseGrid('//div[@id="lat-grid-series"]//div[contains(@class,"anime-card")]'),
                'movies' => $this->parseGrid('//div[@id="lat-grid-movies"]//div[contains(@class,"anime-card")]'),
            ],
            'languages' => $this->parseLanguages(),
        ];
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