<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses an anime / series detail page (/{slug}/).
 */
final class AnimeParser
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
            'title' => $this->html->firstText([
                '//h1[contains(@class,"entry-title-pro")]',
                '//h1',
            ]),
            'poster' => $this->html->firstImageUrl([
                '//img[contains(@class,"main-poster")]/@data-src',
                '//img[contains(@class,"main-poster")]/@src',
            ]),
            'banner' => $this->parseBanner(),
            'genres' => $this->parseGenres(),
            'meta' => $this->parseMetaRows(),
            'description' => $this->html->firstText([
                '//div[contains(@class,"synopsis-pro")]//p',
                '//div[contains(@class,"synopsis-pro")]',
            ]),
            'notes' => $this->html->firstText(['//span[contains(@class,"shield-text")]']),
            'latest_episode' => $this->parseLatestEpisode(),
            'external_ids' => $this->parseExternalIds(),
            'seasons' => $this->parseSeasons(),
            // Movies ship a "WATCH MOVIE NOW" button that links to the real
            // watch page (/movie/{hash}/). Series use the season/episode lists
            // instead, so this stays null there.
            'watch_url' => $this->parseWatchUrl(),
        ];
    }

    /**
     * Resolve the /movie/{hash}/ watch page from the detail page's watch
     * button (movies have no season blocks to read episode URLs from).
     */
    private function parseWatchUrl(): ?string
    {
        $href = $this->html->firstAttr([
            '//a[contains(@class,"ultra-watch-btn")]/@href',
            '//div[contains(@class,"movie-watch-area")]//a/@href',
        ], 'href');

        if ($href === null || $href === '' || $href === '#') {
            return null;
        }

        $url = $this->html->normalizeUrl($href);

        // Only a watch page is useful here; ignore share/other anchors.
        return str_contains($url, '/movie/') ? $url : null;
    }

    private function parseBanner(): ?string
    {
        $style = $this->html->firstAttr(
            ['//div[contains(@class,"hero-bg")]/@style'],
            'style'
        );

        if ($style === null) {
            return null;
        }

        $url = HtmlParser::extractUrlFromStyle($style);
        return $url === null ? null : $this->html->normalizeImageUrl($url);
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private function parseGenres(): array
    {
        $genres = [];
        foreach ($this->html->query('//div[contains(@class,"genre-pills")]//a') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $genres[] = [
                'name' => HtmlParser::clean($node->textContent),
                'url' => $this->html->normalizeUrl($node->getAttribute('href')),
            ];
        }
        return $genres;
    }

    /**
     * Parse the AUDIO / SUBTITLE / STUDIO / QUALITY / ENCODE meta rows.
     *
     * @return array<string, mixed>
     */
    private function parseMetaRows(): array
    {
        $meta = [];

        foreach ($this->html->query('//div[contains(@class,"meta-row-pro")]') as $row) {
            $labelNode = $this->firstChildByClass($row, 'label-pro');
            if ($labelNode === null) {
                continue;
            }

            $label = strtoupper(HtmlParser::clean($labelNode->textContent));
            $label = rtrim($label, ':');

            $valueNode = $this->firstChildByClass($row, 'value-pro');
            if ($valueNode === null) {
                continue;
            }

            $links = [];
            foreach ($this->childElements($valueNode) as $child) {
                if ($child->nodeName === 'a') {
                    $links[] = [
                        'name' => HtmlParser::clean($child->textContent),
                        'url' => $this->html->normalizeUrl($child->getAttribute('href')),
                    ];
                }
            }

            $text = HtmlParser::clean($valueNode->textContent);

            switch ($label) {
                case 'AUDIO':
                    $meta['audio_languages'] = $links !== [] ? $links : $text;
                    break;
                case 'SUBTITLE':
                    $meta['subtitles'] = $links !== [] ? $links : $text;
                    break;
                case 'STUDIO':
                    $meta['studio'] = $links !== [] ? $links : $text;
                    break;
                case 'QUALITY':
                    $meta['quality'] = $text;
                    break;
                case 'ENCODE':
                    $meta['encode'] = $text;
                    break;
                default:
                    $meta[strtolower($label)] = $text;
            }
        }

        return $meta;
    }

    /**
     * @return array{label: ?string, url: ?string}|null
     */
    private function parseLatestEpisode(): ?array
    {
        $node = $this->html->first('//a[contains(@class,"at-latest-ep-btn")]');
        if ($node === null || !$node instanceof \DOMElement) {
            return null;
        }

        return [
            'label' => HtmlParser::clean($node->textContent),
            'url' => $this->html->normalizeUrl($node->getAttribute('href')),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function parseExternalIds(): array
    {
        $ids = ['mal' => null, 'tmdb' => null];

        foreach ($this->html->query('//a[contains(@class,"sa-ext-btn")]') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $href = $node->getAttribute('href');

            if (preg_match('#myanimelist\.net/anime/(\d+)#', $href, $m)) {
                $ids['mal'] = $m[1];
            } elseif (preg_match('#themoviedb\.org/(?:tv|movie)/(\d+)#', $href, $m)) {
                $ids['tmdb'] = $m[1];
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSeasons(): array
    {
        $seasons = [];

        foreach ($this->html->query('//div[contains(@class,"season-block")]') as $block) {
            $id = null;
            if ($block instanceof \DOMElement && $block->hasAttribute('id')) {
                if (preg_match('/season-target-(\d+)/', $block->getAttribute('id'), $m)) {
                    $id = (int) $m[1];
                }
            }

            $episodes = $this->parseEpisodes($block);
            // Movies/single-episode entries: a missing episode_count must not read
            // as "0 Episodes" — fall back to the actual count (minimum 1).
            $countText = $this->html->firstTextWithin($block, ['.//div[contains(@class,"s-info-text")]/p']);
            if (!is_string($countText) || $countText === '') {
                $epCount = count($episodes);
                $countText = ($epCount > 0 ? (string) $epCount : '1') . ' Episodes';
            }

            $seasons[] = [
                'season' => $id,
                'title' => $this->html->firstTextWithin($block, ['.//div[contains(@class,"s-info-text")]/h3']),
                'episode_count' => $countText,
                'poster' => $this->html->firstImageUrlWithin($block, [
                    './/div[contains(@class,"s-poster-mini")]/img/@data-src',
                    './/div[contains(@class,"s-poster-mini")]/img/@src',
                ]),
                'episodes' => $episodes,
            ];
        }

        // Premium UI fallback (site redesign ~2026): seasons render as
        // `.at-season-card` shells and episodes load via admin-ajax
        // (action=at_get_season_episodes). Without this, multi-episode
        // series parse as 0 seasons and callers misclassify them as movies.
        if ($seasons === []) {
            foreach ($this->html->query('//div[contains(@class,"at-season-card")]') as $card) {
                if (!$card instanceof \DOMElement) {
                    continue;
                }
                $num = $card->getAttribute('data-season');
                $uploaded = $card->getAttribute('data-uploaded');
                $total = $card->getAttribute('data-total');
                $title = $this->html->firstTextWithin($card, [
                    './/div[contains(@class,"info-wrap")]/h3',
                    './/h3',
                ]);
                if ($title === null || $title === '') {
                    $title = $num !== '' ? ('Season ' . $num) : 'Season 1';
                }
                $countText = $this->html->firstTextWithin($card, [
                    './/div[contains(@class,"info-wrap")]/p',
                    './/p',
                ]);
                if (!is_string($countText) || $countText === '') {
                    $known = $uploaded !== '' ? $uploaded : $total;
                    $countText = ($known !== '' ? $known : '1') . ' Episodes';
                }
                $seasons[] = [
                    'season' => $num !== '' ? (int) $num : null,
                    'title' => $title,
                    'episode_count' => $countText,
                    'poster' => $this->html->firstImageUrlWithin($card, [
                        './/img/@data-src',
                        './/img/@src',
                    ]),
                    'episodes' => [],
                    'uploaded' => $uploaded !== '' ? (int) $uploaded : null,
                    'total' => $total !== '' ? (int) $total : null,
                ];
            }
        }

        return $seasons;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseEpisodes(\DOMNode $seasonBlock): array
    {
        $episodes = [];

        foreach ($this->html->query('.//a[contains(@class,"ep-row")]', $seasonBlock) as $row) {
            $epId = $row instanceof \DOMElement ? $row->getAttribute('data-ep-id') : '';
            $href = $row instanceof \DOMElement ? $row->getAttribute('href') : '';
            $episodes[] = [
                'id' => $epId !== '' ? (int) $epId : null,
                'label' => $this->html->firstTextWithin($row, ['.//div[contains(@class,"ep-info")]/strong']),
                'url' => $href !== '' ? $this->html->normalizeUrl($href) : $this->html->firstUrlWithin($row, ['.//@href']),
                'thumbnail' => $this->html->firstImageUrlWithin($row, [
                    './/img/@data-src',
                    './/img/@src',
                ]),
            ];
        }

        return $episodes;
    }

    private function firstChildByClass(\DOMNode $parent, string $class): ?\DOMElement
    {
        foreach ($this->childElements($parent) as $child) {
            $classes = explode(' ', $child->getAttribute('class'));
            if (in_array($class, $classes, true)) {
                return $child;
            }
        }
        return null;
    }

    /**
     * @return \DOMElement[]
     */
    private function childElements(\DOMNode $parent): array
    {
        $elements = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $elements[] = $child;
            }
        }
        return $elements;
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}