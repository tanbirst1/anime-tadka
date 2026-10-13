<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses an episode (/ep/{hash}/) or movie (/movie/{hash}/) watch page.
 */
final class EpisodeParser
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
            'back_link' => $this->parseBackLink(),
            'title' => $this->parseTitle(),
            'rating' => $this->parseMetaValue('Rating'),
            'runtime' => $this->parseMetaValue('Runtime'),
            'description' => $this->html->firstText([
                '//div[contains(@class,"ep-content-text")]',
            ]),
            'player_url' => $this->html->firstUrl([
                '//iframe[@id="at-player-v2"]/@src',
                '//div[contains(@class,"player-container")]//iframe/@src',
            ]),
            'servers' => $this->parseServers(),
            'next_episode_url' => $this->html->firstUrl([
                '//a[@id="next-ep-btn"]/@href',
            ]),
            'download_links' => $this->parseDownloadLinks(),
            'release_date' => $this->html->firstAttr(
                ['//meta[@property="article:modified_time"]/@content', '//meta[@property="og:updated_time"]/@content'],
                'content'
            ),
            'recommendations' => $this->parseRecommendations(),
        ];
    }

    /**
     * @return array{label: ?string, url: ?string}|null
     */
    private function parseBackLink(): ?array
    {
        $node = $this->html->first('//a[contains(@class,"back-info-btn")]');
        if ($node === null || !$node instanceof \DOMElement) {
            return null;
        }

        return [
            'label' => HtmlParser::clean($node->textContent),
            'url' => $this->html->normalizeUrl($node->getAttribute('href')),
        ];
    }

    /**
     * @return array{accent: ?string, title: ?string}
     */
    private function parseTitle(): array
    {
        return [
            'accent' => $this->html->firstText(['//span[contains(@class,"title-accent")]']),
            'title' => $this->html->firstText(['//span[contains(@class,"title-main")]']),
        ];
    }

    private function parseMetaValue(string $label): ?string
    {
        foreach ($this->html->query('//div[contains(@class,"meta-item-box")]') as $box) {
            $text = HtmlParser::clean($box->textContent);
            if (str_starts_with($text, $label . ':')) {
                $value = trim(substr($text, strlen($label) + 1));
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    /**
     * @return array<int, array{label: ?string, url: ?string, auto: string, cdn21: string}>
     */
    private function parseServers(): array
    {
        $servers = [];

        foreach ($this->html->query('//button[contains(@class,"srv-btn")]') as $button) {
            if (!$button instanceof \DOMElement) {
                continue;
            }
            $servers[] = [
                'label' => HtmlParser::clean($button->textContent),
                'url' => $this->html->normalizeUrl($button->getAttribute('data-url')),
                'auto' => $button->getAttribute('data-auto'),
                'cdn21' => $button->getAttribute('data-cdn21'),
            ];
        }

        return $servers;
    }

    /**
     * @return array<int, array{title: ?string, url: ?string, poster: ?string}>
     */
    private function parseRecommendations(): array
    {
        $items = [];

        foreach ($this->html->query('//div[contains(@class,"reco-slider")]//div[contains(@class,"reco-item")]') as $item) {
            if (!$item instanceof \DOMElement) {
                continue;
            }

            $items[] = [
                'title' => $this->html->firstTextWithin($item, ['.//span[contains(@class,"reco-title")]']),
                'url' => $this->html->firstUrlWithin($item, ['.//a/@href']),
                'poster' => $this->html->firstImageUrlWithin($item, [
                    './/div[contains(@class,"reco-poster")]/img/@data-src',
                    './/div[contains(@class,"reco-poster")]/img/@src',
                ]),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{label: ?string, url: ?string}>
     */
    private function parseDownloadLinks(): array
    {
        $links = [];

        foreach ($this->html->query('//a[contains(@class,"dl-btn-premium")]') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $links[] = [
                'label' => HtmlParser::clean($node->textContent),
                'url' => $this->html->normalizeUrl($node->getAttribute('href')),
            ];
        }

        return $links;
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}