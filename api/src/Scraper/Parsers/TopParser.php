<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses the "Top Animes" AJAX fragment (action=get_top_animes).
 * Rows: rank number, title, URL, poster, language tags.
 */
final class TopParser
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
        $items = [];

        foreach ($this->html->query('//a[contains(@class,"top-10-item")]') as $row) {
            $href = $row instanceof \DOMElement ? $row->getAttribute('href') : '';
            $items[] = [
                'rank' => $this->html->firstTextWithin($row, ['.//div[contains(@class,"top-number")]']),
                'title' => $this->html->firstTextWithin($row, ['.//h3']),
                'url' => $href !== '' ? $this->html->normalizeUrl($href) : null,
                'poster' => $this->html->firstImageUrlWithin($row, [
                    './/img[contains(@class,"top-poster")]/@src',
                    './/img[contains(@class,"top-poster")]/@data-src',
                ]),
                'languages' => $this->html->allTextWithin($row, './/div[contains(@class,"top-tags")]/span'),
            ];
        }

        return $items;
    }
}