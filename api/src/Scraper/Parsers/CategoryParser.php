<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses listing pages: /type/*, /lang/*, /genre/*, /studio/*, /sub/*.
 */
final class CategoryParser
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
            'label' => $this->html->firstText(['//span[contains(@class,"dynamic-pro-label")]']),
            'title' => $this->html->firstText(['//h2[contains(@class,"main-pro-title-v2")]']),
            'items' => $this->parseItems(),
            'pagination' => $this->parsePagination(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseItems(): array
    {
        $items = [];

        foreach ($this->html->query('//div[contains(@class,"home-grid")]//div[contains(@class,"anime-card")]') as $card) {
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
     * @return array<string, mixed>
     */
    private function parsePagination(): array
    {
        $current = null;
        $total = null;
        $next = null;

        foreach ($this->html->query('//div[contains(@class,"at-pagination")]//a[contains(@class,"page-numbers")]') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $text = HtmlParser::clean($node->textContent);
            $href = $node->getAttribute('href');

            if (str_contains(implode(' ', explode(' ', $node->getAttribute('class'))), 'next')) {
                $next = $this->html->normalizeUrl($href);
                continue;
            }

            if (is_numeric($text)) {
                $num = (int) $text;
                $total = max($total ?? 0, $num);
            }
        }

        $currentNode = $this->html->first('//div[contains(@class,"at-pagination")]//span[contains(@class,"page-numbers") and contains(@class,"current")]');
        if ($currentNode !== null && is_numeric(HtmlParser::clean($currentNode->textContent))) {
            $current = (int) HtmlParser::clean($currentNode->textContent);
        }

        return [
            'current' => $current,
            'total' => $total,
            'next' => $next,
        ];
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}