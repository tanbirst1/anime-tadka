<?php
/**
 * SEO Scraper — fetches landing page content from each provider and
 * saves structured SEO text to data/seo/{provider}.json.
 *
 * Usage:
 *   php bin/scrape-seo.php              # scrape all providers
 *   php bin/scrape-seo.php anikoto      # scrape specific provider
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/baseurl.php';
require_once __DIR__ . '/../api/anikoto/config.php';
require_once __DIR__ . '/../api/anikoto/http.php';
require_once __DIR__ . '/../api/anikoto/dom.php';

$config = require __DIR__ . '/../api/config.php';
$baseUrls = require __DIR__ . '/../api/baseurl.php';
$seoDir = __DIR__ . '/../data/seo';

if (!is_dir($seoDir)) {
    mkdir($seoDir, 0755, true);
}

$target = $argv[1] ?? null;
$providers = $target ? [$target] : ['animetadka', 'anikoto', 'toonstream', 'animedekho'];

foreach ($providers as $provider) {
    echo "Scraping: $provider\n";
    $data = match ($provider) {
        'animetadka' => scrapeAnimetadka($baseUrls['animetadka']),
        'anikoto'    => scrapeAnikoto($baseUrls['anikoto']),
        'toonstream' => scrapeToonstream($baseUrls['toonstream']),
        'animedekho' => scrapeAnimedekho($baseUrls['animetadka']), // animedekho uses animedekho.app
        default      => null,
    };

    if ($data === null) {
        echo "  Skipped (no scraper for $provider)\n";
        continue;
    }

    $file = $seoDir . "/$provider.json";
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "  Saved: $file\n";
}

echo "Done.\n";

// ── Scrapers ────────────────────────────────────────────────────────

function scrapeAnimetadka(string $base): array {
    // Animetadka uses WordPress — the landing page is the root.
    $html = @file_get_contents($base . '/');
    if ($html === false) return [];

    $doc = domLoad($html);
    $xp = new DOMXPath($doc);

    return extractSeoFromDom($xp, "//div[contains(@class,'entry-content')]");
}

function scrapeAnikoto(string $base): array {
    $html = @file_get_contents($base . '/');
    if ($html === false) return [];

    $doc = domLoad($html);
    $xp = new DOMXPath($doc);

    // Anikoto's landing content is in the main body area
    $data = extractSeoFromDom($xp, "//div[contains(@class,'container')] | //main | //article");

    // Also grab top searches from the homepage
    $topSearches = [];
    foreach ($xp->query("//div[contains(@class,'search')]//a | //div[contains(@class,'tag')]//button") as $el) {
        $term = trim($el->textContent);
        if ($term !== '' && count($topSearches) < 8) {
            $topSearches[] = $term;
        }
    }
    $data['topSearches'] = $topSearches;

    return $data;
}

function scrapeToonstream(string $base): array {
    $html = @file_get_contents($base . '/');
    if ($html === false) return [];

    $doc = domLoad($html);
    $xp = new DOMXPath($doc);

    $container = null;
    foreach ($xp->query("//div[@id='home-article']") as $node) {
        $container = $node;
        break;
    }

    if ($container === null) {
        return extractSeoFromDom($xp, "//div[contains(@class,'entry-content')]");
    }

    $intro = [];
    $sections = [];
    $current = null;

    foreach ($xp->query('.//*[self::h2 or self::h3 or self::p]', $container) as $node) {
        $text = trim($node->textContent);
        if ($text === '') continue;

        if ($node->nodeName === 'h2' || $node->nodeName === 'h3') {
            $current = [
                'heading' => $text,
                'type' => $node->nodeName === 'h2' ? 'section' : 'subsection',
                'paragraphs' => [],
            ];
            $sections[] = $current;
        } elseif ($current === null) {
            $intro[] = $text;
        } else {
            $sections[count($sections) - 1]['paragraphs'][] = $text;
        }
    }

    return ['intro' => $intro, 'sections' => $sections, 'topSearches' => []];
}

function scrapeAnimedekho(string $base): array {
    $html = @file_get_contents('https://animedekho.app/');
    if ($html === false) return [];

    $doc = domLoad($html);
    $xp = new DOMXPath($doc);

    return extractSeoFromDom($xp, "//div[contains(@class,'entry-content')]");
}

function extractSeoFromDom(DOMXPath $xp, string $selector): array {
    $intro = [];
    $sections = [];
    $current = null;

    foreach ($xp->query($selector) as $container) {
        foreach ($xp->query('.//*[self::h2 or self::h3 or self::p]', $container) as $node) {
            $text = trim($node->textContent);
            if ($text === '') continue;

            if ($node->nodeName === 'h2' || $node->nodeName === 'h3') {
                $current = [
                    'heading' => $text,
                    'type' => $node->nodeName === 'h2' ? 'section' : 'subsection',
                    'paragraphs' => [],
                ];
                $sections[] = $current;
            } elseif ($current === null) {
                $intro[] = $text;
            } else {
                $sections[count($sections) - 1]['paragraphs'][] = $text;
            }
        }
    }

    return ['intro' => $intro, 'sections' => $sections, 'topSearches' => []];
}
