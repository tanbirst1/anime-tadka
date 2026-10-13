<?php

declare(strict_types=1);

namespace ToonStreamApi\Utils;

/**
 * Small DOMDocument/DOMXPath helpers — every CSS-like selector lives here,
 * never inline in providers.
 */
final class Dom
{
    public static function load(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Force UTF-8 so non-ASCII titles survive loadHTML().
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $doc;
    }

    public static function xp(\DOMDocument $doc): \DOMXPath
    {
        return new \DOMXPath($doc);
    }

    /** @return \DOMElement[] */
    public static function q(\DOMXPath $xp, string $expr, ?\DOMNode $context = null): array
    {
        $list = $xp->query($expr, $context);
        if ($list === false) {
            return [];
        }
        $out = [];
        foreach ($list as $node) {
            $out[] = $node;
        }
        return $out;
    }

    public static function one(\DOMXPath $xp, string $expr, ?\DOMNode $context = null): ?\DOMElement
    {
        $list = $xp->query($expr, $context);
        if ($list === false || $list->length === 0) {
            return null;
        }
        $node = $list->item(0);
        // nodeType, not instanceof: PHP 8.4+ DOM wrapper objects obtained
        // through some access paths fail instanceof checks.
        return $node !== null && $node->nodeType === XML_ELEMENT_NODE ? $node : null;
    }

    /** Normalized inner text (all whitespace runs collapse to single spaces). */
    public static function text(?\DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }
        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    public static function attr(?\DOMNode $node, string $name): ?string
    {
        if ($node !== null && $node->nodeType === XML_ELEMENT_NODE && $node->hasAttribute($name)) {
            $value = trim($node->getAttribute($name));
            return $value === '' ? null : $value;
        }
        return null;
    }

    public static function href(?\DOMNode $node): ?string
    {
        return self::attr($node, 'href');
    }
}
