<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper;

use AnimeTadka\Exceptions\ParseException;

/**
 * DOMDocument/XPath wrapper with resilient extraction helpers.
 *
 * All extraction methods accept arrays of XPath expressions and return the
 * first non-empty match, so parsers degrade gracefully when the site markup
 * changes (anti-fragile selectors).
 */
final class HtmlParser
{
    private \DOMDocument $dom;
    private \DOMXPath $xpath;
    private string $baseUrl;

    /** @var array<string,string> image url normalization cache */
    private static array $imageCache = [];

    /** @var array<string,string> url normalization cache */
    private static array $urlCache = [];

    public function __construct(string $html, string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl;

        if (trim($html) === '') {
            throw new ParseException('Empty HTML document');
        }

        $this->dom = new \DOMDocument('1.0', 'UTF-8');

        // Suppress warnings for malformed markup; DOMDocument is lenient.
        $previous = libxml_use_internal_errors(true);
        $loaded = $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT | LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new ParseException('Unable to parse HTML document');
        }

        $this->xpath = new \DOMXPath($this->dom);
    }

    public function getDom(): \DOMDocument
    {
        return $this->dom;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Run an XPath query.
     *
     * @return \DOMNodeList<\DOMNode>
     */
    public function query(string $xpath, ?\DOMNode $context = null): \DOMNodeList
    {
        $result = @$this->xpath->query($xpath, $context);
        if ($result === false) {
            throw new ParseException("Invalid XPath expression: {$xpath}");
        }
        return $result;
    }

    public function first(string $xpath, ?\DOMNode $context = null): ?\DOMNode
    {
        $nodes = $this->query($xpath, $context);
        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    // ── Context-aware helpers (avoids re-parsing node HTML) ──────────

    public function queryWithin(\DOMNode $context, string $xpath): \DOMNodeList
    {
        return $this->query($xpath, $context);
    }

    public function firstWithin(\DOMNode $context, string $xpath): ?\DOMNode
    {
        $nodes = $this->query($xpath, $context);
        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    /**
     * First non-empty text within a context node.
     * @param string[] $xpaths
     */
    public function firstTextWithin(\DOMNode $context, array $xpaths): ?string
    {
        foreach ($xpaths as $xpath) {
            $nodes = $this->query($xpath, $context);
            foreach ($nodes as $node) {
                $text = self::clean($node->textContent);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        return null;
    }

    /**
     * First non-empty attribute within a context node.
     * @param string[] $xpaths
     */
    public function firstAttrWithin(\DOMNode $context, array $xpaths, string $attribute): ?string
    {
        foreach ($xpaths as $xpath) {
            $nodes = $this->query($xpath, $context);
            foreach ($nodes as $node) {
                $value = null;
                if ($node instanceof \DOMAttr) {
                    $value = trim($node->value);
                } elseif ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
                    $value = trim($node->getAttribute($attribute));
                }
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * First absolute URL within a context node.
     * @param string[] $xpaths
     */
    public function firstUrlWithin(\DOMNode $context, array $xpaths, string $attribute = 'href'): ?string
    {
        $value = $this->firstAttrWithin($context, $xpaths, $attribute);
        return $value === null ? null : $this->normalizeUrl($value);
    }

    /**
     * First full-size image URL within a context node.
     * @param string[] $xpaths
     */
    public function firstImageUrlWithin(\DOMNode $context, array $xpaths, string $attribute = 'src'): ?string
    {
        $value = $this->firstAttrWithin($context, $xpaths, $attribute);
        return $value === null ? null : $this->normalizeImageUrl($value);
    }

    /**
     * @return string[]
     */
    public function allTextWithin(\DOMNode $context, string $xpath): array
    {
        $result = [];
        foreach ($this->query($xpath, $context) as $node) {
            $text = self::clean($node->textContent);
            if ($text !== '') {
                $result[] = $text;
            }
        }
        return $result;
    }

    /**
     * First non-empty text among a list of candidate XPath expressions.
     *
     * @param string[] $xpaths
     */
    public function firstText(array $xpaths): ?string
    {
        foreach ($xpaths as $xpath) {
            $nodes = $this->query($xpath);
            foreach ($nodes as $node) {
                $text = self::clean($node->textContent);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        return null;
    }

    /**
     * First non-empty attribute among a list of candidate XPath expressions.
     * Supports both element XPaths ('.//img/@data-src') and attribute XPaths
     * ('.//a/@href') — the latter yield DOMAttr nodes.
     *
     * @param string[] $xpaths
     */
    public function firstAttr(array $xpaths, string $attribute): ?string
    {
        foreach ($xpaths as $xpath) {
            $nodes = $this->query($xpath);
            foreach ($nodes as $node) {
                $value = null;
                if ($node instanceof \DOMAttr) {
                    $value = trim($node->value);
                } elseif ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
                    $value = trim($node->getAttribute($attribute));
                }
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * First absolute URL among candidate XPath expressions.
     *
     * @param string[] $xpaths
     */
    public function firstUrl(array $xpaths, string $attribute = 'href'): ?string
    {
        $value = $this->firstAttr($xpaths, $attribute);
        return $value === null ? null : $this->normalizeUrl($value);
    }

    /**
     * First full-size image URL among candidate XPath expressions.
     *
     * @param string[] $xpaths
     */
    public function firstImageUrl(array $xpaths, string $attribute = 'src'): ?string
    {
        $value = $this->firstAttr($xpaths, $attribute);
        return $value === null ? null : $this->normalizeImageUrl($value);
    }

    /**
     * @return string[]
     */
    public function allText(string $xpath): array
    {
        $result = [];
        foreach ($this->query($xpath) as $node) {
            $text = self::clean($node->textContent);
            if ($text !== '') {
                $result[] = $text;
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function allAttr(string $xpath, string $attribute): array
    {
        $result = [];
        foreach ($this->query($xpath) as $node) {
            if ($node instanceof \DOMElement && $node->hasAttribute($attribute)) {
                $value = trim($node->getAttribute($attribute));
                if ($value !== '') {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function allUrls(string $xpath, string $attribute = 'href'): array
    {
        $result = [];
        foreach ($this->allAttr($xpath, $attribute) as $value) {
            $result[] = $this->normalizeUrl($value);
        }
        return $result;
    }

    /**
     * Resolve a possibly-relative URL against the page base URL.
     */
    public function normalizeUrl(string $url): string
    {
        $cacheKey = $this->baseUrl . '|' . $url;
        if (isset(self::$urlCache[$cacheKey])) {
            return self::$urlCache[$cacheKey];
        }

        $orig = $url;
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return self::$urlCache[$cacheKey] = '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return self::$urlCache[$cacheKey] = $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = (string) parse_url($this->baseUrl, PHP_URL_SCHEME);
            return self::$urlCache[$cacheKey] = ($scheme !== '' ? $scheme : 'https') . ':' . $url;
        }

        if ($this->baseUrl === '') {
            return self::$urlCache[$cacheKey] = $url;
        }

        $base = $this->baseUrl;

        if (str_starts_with($url, '/')) {
            $host = (string) parse_url($base, PHP_URL_SCHEME) . '://' . (string) parse_url($base, PHP_URL_HOST);
            return self::$urlCache[$cacheKey] = $host . $url;
        }

        // Relative path: resolve against the base directory.
        $path = (string) parse_url($base, PHP_URL_PATH);
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        $host = (string) parse_url($base, PHP_URL_SCHEME) . '://' . (string) parse_url($base, PHP_URL_HOST);
        return self::$urlCache[$cacheKey] = $host . $dir . $url;
    }

    /**
     * Resolve an image URL to its full-size original and wrap with serveproxy.com CDN:
     *  - makes relative URLs absolute (like normalizeUrl)
     *  - strips WordPress "-{w}x{h}" resize suffixes (e.g. "-150x150.jpg")
     *  - strips query strings (e.g. "?fit=214%2C300&ssl=1") for original-size images
     *  - unwraps Jetpack CDN prefix (i0.wp.com/animetadka.com → animetadka.com)
     *  - wraps with serveproxy.com proxy for fast global CDN delivery
     *
     * Href URLs must NOT use this — watch/download links rely on query strings.
     */
    public function normalizeImageUrl(string $url): string
    {
        if (isset(self::$imageCache[$url])) {
            return self::$imageCache[$url];
        }

        $norm = $this->normalizeUrl($url);

        // Strip query params first (e.g. ?fit=214%2C300&ssl=1) to expose the extension
        $norm = preg_replace('/\?.*$/', '', $norm) ?? $norm;

        // Strip WordPress resize suffixes (-150x150, -200x300, etc.)
        $norm = preg_replace('/-\d+x\d+(?=\.[a-zA-Z0-9]{2,5}$)/', '', $norm) ?? $norm;

        // Unwrap Jetpack CDN: https://i0.wp.com/animetadka.com/... → https://animetadka.com/...
        if (preg_match('#^https?://i\d+\.wp\.com/(.+)$#i', $norm, $m)) {
            $norm = 'https://' . ltrim($m[1], '/');
        }
        // Ensure absolute https:// for proxy (was missing protocol after unwrap)
        if (!preg_match('#^https?://#i', $norm)) {
            $norm = 'https://' . ltrim($norm, '/');
        }
        $result = 'https://serveproxy.com/?url=' . rawurlencode($norm);
        self::$imageCache[$url] = $result;
        self::$imageCache[$norm] = $result;
        return $result;
    }

    /**
     * Normalize extracted text: decode entities, collapse whitespace, trim.
     */
    public static function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Extract a URL from an inline style like background-image: url('...').
     */
    public static function extractUrlFromStyle(string $style): ?string
    {
        if (preg_match('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}