<?php

declare(strict_types=1);

/**
 * Page inspector — fetch a URL and summarize its HTML structure.
 * Helps understand the site without touching the production scraper.
 *
 * Usage:
 *   php bin/inspect-page.php "https://animetadka.com/..."
 */

use AnimeTadka\Bootstrap;
use AnimeTadka\Scraper\HtmlParser;

require dirname(__DIR__) . '/src/autoload.php';

$url = $argv[1] ?? '';
if ($url === '') {
    fwrite(STDERR, "Usage: php bin/inspect-page.php \"https://animetadka.com/...\"\n");
    exit(1);
}

$config = Bootstrap::config();
$http = Bootstrap::httpClient($config);

try {
    $html = $http->getHtml($url, ['save_html' => true]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'FETCH ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

$parser = new HtmlParser($html, $url);

echo "=== PAGE INSPECTION ===\n";
echo "URL:   {$url}\n";
echo "Bytes: " . strlen($html) . "\n";

$title = $parser->firstText(['//title', '//h1']);
echo "Title: " . ($title ?? '(none)') . "\n";

$description = $parser->firstAttr(['//meta[@name="description"]/@content'], 'content');
echo "Description: " . ($description ?? '(none)') . "\n";

$canonical = $parser->firstUrl(['//link[@rel="canonical"]/@href']);
echo "Canonical: " . ($canonical ?? '(none)') . "\n";

$links = $parser->allUrls('//a/@href');
$internal = array_values(array_filter($links, static fn ($l) => str_contains($l, 'animetadka.com')));
echo "Links: " . count($links) . " total, " . count($internal) . " internal\n";

$images = $parser->allAttr('//img', 'data-src');
$images = array_merge($images, $parser->allAttr('//img', 'src'));
echo "Images: " . count($images) . "\n";

$scripts = $parser->allAttr('//script', 'src');
echo "Scripts: " . count($scripts) . "\n";

$forms = $parser->query('//form');
echo "Forms: " . $forms->length . "\n";

$jsonLd = $parser->query('//script[@type="application/ld+json"]');
echo "JSON-LD blocks: " . $jsonLd->length . "\n";

$dataAttrs = [];
foreach ($parser->query('//*[@*[starts-with(name(),"data-")]]') as $node) {
    if ($node instanceof \DOMElement) {
        foreach ($node->attributes as $attr) {
            if (str_starts_with($attr->nodeName, 'data-')) {
                $dataAttrs[$attr->nodeName] = ($dataAttrs[$attr->nodeName] ?? 0) + 1;
            }
        }
    }
}
echo "data-* attributes:\n";
foreach ($dataAttrs as $name => $count) {
    echo "  {$name}: {$count}\n";
}

echo "\n=== INTERNAL LINKS (first 40) ===\n";
foreach (array_slice($internal, 0, 40) as $link) {
    echo "  {$link}\n";
}

echo "\n=== IMAGES (first 20) ===\n";
foreach (array_slice($images, 0, 20) as $img) {
    echo "  {$img}\n";
}

echo "\nSaved snapshot to storage/html/ (see logs for filename).\n";