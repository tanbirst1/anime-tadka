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
            'player_url' => $this->parsePlayerUrl(),
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
     * The lazy player iframe. The redesign ships the watch.php URL as a
     * base64 `data-init` attribute while `src` stays `about:blank` until the
     * site's own JS swaps it in, so decode `data-init` (falling back to a real
     * `src` on older snapshots).
     */
    private function parsePlayerUrl(): ?string
    {
        $init = $this->html->firstAttr(
            ['//iframe[@id="at-player-v2"]/@data-init', '//div[contains(@class,"player-container")]//iframe/@data-init'],
            'data-init'
        );
        $decoded = self::decodeWatchUrl($init);
        if ($decoded !== null) {
            return $this->html->normalizeUrl($decoded);
        }

        $src = $this->html->firstUrl([
            '//iframe[@id="at-player-v2"]/@src',
            '//div[contains(@class,"player-container")]//iframe/@src',
        ]);

        // about:blank / data: placeholders are not real player URLs.
        if ($src === null || str_starts_with($src, 'data:')) {
            return null;
        }

        return $src;
    }

    /**
     * @return array<int, array{label: ?string, url: ?string, auto: string, cdn21: string}>
     */
    private function parseServers(): array
    {
        $servers = [];

        $buttons = $this->html->query('//button[contains(@class,"srv-btn")]');
        foreach ($buttons as $button) {
            if (!$button instanceof \DOMElement) {
                continue;
            }

            // `data-u` is the base64 watch.php URL on the redesign; `data-url`
            // is the plain href on older snapshots.
            $raw = $button->getAttribute('data-u');
            $url = $raw !== '' ? self::decodeWatchUrl($raw) : $button->getAttribute('data-url');

            $servers[] = [
                'label' => HtmlParser::clean($button->textContent),
                'url' => $url !== null ? $this->html->normalizeUrl($url) : null,
                'auto' => $button->getAttribute('data-autonext') !== '' ? $button->getAttribute('data-autonext') : $button->getAttribute('data-auto'),
                'cdn21' => $button->getAttribute('data-cdn21'),
            ];
        }

        return $servers;
    }

    /**
     * Decode a base64-encoded `/watch.php?...` path (the redesign's `data-u`
     * and `data-init` values). Returns null when the value is not base64.
     */
    private static function decodeWatchUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false || !str_contains($decoded, '/watch.php')) {
            return null;
        }

        return $decoded;
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
     * Download buttons. The redesign exposes the real `/redirect/download/`
     * URL as a base64 `data-u` attribute (`href` is just `#`) and groups the
     * buttons under a quality tab (`720P` / `1080P`), so carry the quality
     * through and skip any button whose endpoint cannot be decoded.
     *
     * @return array<int, array{label: ?string, url: ?string, quality: ?string}>
     */
    private function parseDownloadLinks(): array
    {
        $links = [];

        foreach ($this->html->query('//a[contains(@class,"dl-btn-premium")]') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $url = $this->downloadUrl($node);
            if ($url === null) {
                continue;
            }

            $links[] = [
                'label' => HtmlParser::clean($node->textContent),
                'url' => $url,
                'quality' => $this->downloadQuality($node),
            ];
        }

        return $links;
    }

    /**
     * Resolve a download button's endpoint from base64 `data-u` (redesign) or
     * a plain `href` (older snapshots).
     */
    private function downloadUrl(\DOMElement $node): ?string
    {
        $decoded = self::decodeRedirectUrl($node->getAttribute('data-u'));
        if ($decoded !== null) {
            return $this->html->normalizeUrl($decoded);
        }

        $href = trim($node->getAttribute('href'));
        if ($href === '' || $href === '#') {
            return null;
        }

        return $this->html->normalizeUrl($href);
    }

    /**
     * Quality label of the tab wrapping a download button, when present.
     */
    private function downloadQuality(\DOMElement $node): ?string
    {
        for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
            $classes = explode(' ', $p->getAttribute('class'));
            if (in_array('dl-tab-content', $classes, true)) {
                $id = $p->getAttribute('id');
                return $id !== '' ? preg_replace('/^dl-content-/', '', $id) : null;
            }
        }

        return null;
    }

    private static function decodeRedirectUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false || !str_contains($decoded, '/redirect/download/')) {
            return null;
        }

        return $decoded;
    }

    private function nodeHtml(\DOMNode $node): string
    {
        return $node->ownerDocument?->saveHTML($node) ?: '';
    }
}