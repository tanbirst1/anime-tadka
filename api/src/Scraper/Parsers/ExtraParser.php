<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses site chrome from the home page: navigation menu, header logo,
 * site logo (JSON-LD) and favicon links.
 */
final class ExtraParser
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
            'menu' => $this->parseMenu(),
            'header_logo' => $this->parseHeaderLogo(),
            'site_logo' => $this->parseSiteLogo(),
            'favicon' => $this->parseFavicon(),
            'icons' => $this->parseIcons(),
        ];
    }

    /**
     * Sidebar navigation: single links plus accordion blocks with sub-links.
     *
     * @return array<int, array{label: ?string, url: ?string, children: array<int, array{label: ?string, url: ?string}>}>
     */
    private function parseMenu(): array
    {
        $menu = [];

        foreach ($this->html->query('//nav[contains(@class,"ak-sidebar-nav")]/*') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $classes = explode(' ', $node->getAttribute('class'));

            if ($node->nodeName === 'a' && in_array('ak-single-link', $classes, true)) {
                $menu[] = [
                    'label' => $this->html->firstTextWithin($node, ['.//span']),
                    'url' => $this->html->firstUrlWithin($node, ['.//@href']) ?? $this->html->normalizeUrl($node->getAttribute('href')),
                    'children' => [],
                ];
                continue;
            }

            if ($node->nodeName === 'details' && in_array('ak-native-drop', $classes, true)) {
                $children = [];
                foreach ($this->html->query('.//a[contains(@class,"ak-sub-link")]', $node) as $child) {
                    if (!$child instanceof \DOMElement) {
                        continue;
                    }
                    $children[] = [
                        'label' => HtmlParser::clean($child->textContent),
                        'url' => $this->html->normalizeUrl($child->getAttribute('href')),
                    ];
                }

                $menu[] = [
                    'label' => $this->html->firstTextWithin($node, ['.//summary//span']),
                    'url' => null,
                    'children' => $children,
                ];
            }
        }

        return $menu;
    }

    private function parseHeaderLogo(): ?string
    {
        return $this->html->firstImageUrl([
            '//div[contains(@class,"site-logo")]//img/@data-src',
            '//div[contains(@class,"site-logo")]//img/@src',
        ]);
    }

    /**
     * Site logo from the JSON-LD @graph Person/Organization image.
     */
    private function parseSiteLogo(): ?string
    {
        foreach ($this->html->query('//script[@type="application/ld+json"]') as $script) {
            $json = trim($script->textContent);
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }

            $graph = $data['@graph'] ?? null;
            if (!is_array($graph)) {
                $graph = [$data];
            }

            foreach ($graph as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                if (($entry['@type'] ?? '') === 'WebSite' && isset($entry['publisher']['logo']['url'])) {
                    return $this->html->normalizeImageUrl((string) $entry['publisher']['logo']['url']);
                }
                if (($entry['@type'] ?? '') === 'Organization' && isset($entry['logo']['url'])) {
                    return $this->html->normalizeImageUrl((string) $entry['logo']['url']);
                }
                if (isset($entry['image']['url']) && is_string($entry['image']['url'])) {
                    return $this->html->normalizeImageUrl($entry['image']['url']);
                }
            }
        }

        return null;
    }

    /**
     * Primary favicon (first rel=icon link), normalized to full size.
     */
    private function parseFavicon(): ?string
    {
        $icons = $this->parseIcons();
        return $icons[0]['url'] ?? null;
    }

    /**
     * @return array<int, array{url: ?string, size: ?string}>
     */
    private function parseIcons(): array
    {
        $icons = [];

        $xpath = '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]';

        foreach ($this->html->query($xpath) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $icons[] = [
                'url' => $this->html->normalizeImageUrl($node->getAttribute('href')),
                'size' => $node->getAttribute('sizes') !== '' ? $node->getAttribute('sizes') : null,
            ];
        }

        return $icons;
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}