<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

/**
 * Parses the stream-engine watch page and the BoxDrive download page.
 *
 * Pure regex/string helpers — no HtmlParser/DOM dependency required.
 */
final class ProxyParser
{
    /**
     * Extract the source of the <iframe id="main-video"> element.
     *
     * The player URL now lives in `data-src` (loaded lazily by the site's own
     * JS); older snapshots used `src`. Attribute order is unknown, so both
     * id-first and attribute-first variants are tried.
     */
    public static function extractIframeSource(string $html): ?string
    {
        foreach (['data-src', 'src'] as $attr) {
            $quoted = preg_quote($attr, '/');
            if (preg_match('/<iframe[^>]*\bid=["\']main-video["\'][^>]*\b' . $quoted . '=["\']([^"\']+)["\']/i', $html, $m)) {
                return self::usableSource($m[1]);
            }

            if (preg_match('/<iframe[^>]*\b' . $quoted . '=["\']([^"\']+)["\'][^>]*\bid=["\']main-video["\']/i', $html, $m)) {
                return self::usableSource($m[1]);
            }
        }

        return null;
    }

    /**
     * A placeholder `src` (about:blank / empty / data: URI) is not a real
     * player URL — the lazy `data-src` holds the actual embed.
     */
    private static function usableSource(string $src): ?string
    {
        $src = trim($src);
        if ($src === '' || str_starts_with($src, 'data:') || $src === 'about:blank') {
            return null;
        }
        return $src;
    }

    /**
     * Detect a Cloudflare challenge / troll page.
     *
     * 'Kangaroos' and 'cf-chl' are unmistakable challenge markers. The
     * 'challenge-platform' string alone also appears on legitimate stream
     * pages that inject a Cloudflare script, so it only counts as a
     * challenge when the real player iframe is absent.
     */
    public static function isCloudflareChallenge(string $html): bool
    {
        $needle = strtolower($html);

        if (str_contains($needle, 'kangaroos') || str_contains($needle, 'cf-chl')) {
            return true;
        }

        return str_contains($needle, 'challenge-platform') && self::extractIframeSource($html) === null;
    }

    /**
     * Parse a BoxDrive download page.
     *
     * @return array{
     *     title: string,
     *     filename: string,
     *     mirrors: array<int, array{name: string, url: string}>
     * }
     */
    public static function parseDownloadPage(string $html): array
    {
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim($m[1]);
        }

        $filename = preg_replace('/\s*-\s*BoxDrive\s*$/i', '', $title) ?? $title;

        $mirrors = [];
        if (preg_match_all('/<a[^>]*class=["\'][^"\']*public-file-btn[^"\']*["\'][^>]*>/is', $html, $matches)) {
            foreach ($matches[0] as $anchor) {
                $href = '';
                $name = '';

                if (preg_match_all('/(href|title)=["\']([^"\']*)["\']/i', $anchor, $attrs, PREG_SET_ORDER)) {
                    foreach ($attrs as $attr) {
                        if (strtolower($attr[1]) === 'href') {
                            $href = $attr[2];
                        }
                        if (strtolower($attr[1]) === 'title') {
                            $name = $attr[2];
                        }
                    }
                }

                if ($href === '') {
                    continue;
                }

                $mirrors[] = [
                    'name' => $name !== '' ? $name : 'Download',
                    'url' => $href,
                ];
            }
        }

        return [
            'title' => $title,
            'filename' => $filename,
            'mirrors' => $mirrors,
        ];
    }
}
