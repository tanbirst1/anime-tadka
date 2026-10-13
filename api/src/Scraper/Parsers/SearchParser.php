<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses the live-search AJAX result fragment (action=anime_search).
 */
final class SearchParser
{
    private HtmlParser $html;

    public function __construct(HtmlParser $html)
    {
        $this->html = $html;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(): array
    {
        $results = [];

        foreach ($this->html->query('//a[contains(@class,"search-res-item")]') as $item) {
            $href = $item instanceof \DOMElement ? $item->getAttribute('href') : '';
            $results[] = [
                'title' => $this->html->firstTextWithin($item, ['.//span[contains(@class,"res-title")]']),
                'url' => $href !== '' ? $this->html->normalizeUrl($href) : $this->html->firstUrlWithin($item, ['.//@href']),
                'poster' => $this->html->firstImageUrlWithin($item, [
                    './/img[contains(@class,"wp-post-image")]/@src',
                    './/img[contains(@class,"wp-post-image")]/@data-src',
                    './/img[1]/@src',
                ]),
                'languages' => $this->html->firstTextWithin($item, ['.//div[contains(@class,"sub-item")][1]/span']),
                'type' => $this->html->firstTextWithin($item, ['.//span[contains(@class,"type-text")]']),
            ];
        }

        return $results;
    }
}