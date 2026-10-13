<?php
$slug = isset($_GET['slug']) ? $_GET['slug'] : 'muchuu-no-tou-episode-1-id-01';

echo "<h2>Scrape test for: $slug</h2>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://watchhentai.net/videos/' . rawurlencode($slug) . '/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Accept: text/html'],
    CURLOPT_HEADER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$info = curl_getinfo($ch);
curl_close($ch);

echo "<p>HTTP Code: $httpCode</p>";
echo "<p>Final URL: " . $info['url'] . "</p>";
echo "<p>Response length: " . strlen($response) . " bytes</p>";

// Split headers from body
$parts = explode("\r\n\r\n", $response, 2);
$headers = $parts[0];
$html = $parts[1] ?? '';

echo "<h3>Response Headers:</h3><pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h3>Extracted Data:</h3>";

$playerUrl = '';
if (preg_match('/<iframe[^>]*(?:data-litespeed-src|src)\s*=\s*["\']([^"\']*jwplayer[^"\']*)["\'][^>]*>/i', $html, $m)) {
    $playerUrl = str_replace('&amp;', '&', $m[1]);
    echo "<p><b>Player URL found:</b> " . htmlspecialchars($playerUrl) . "</p>";
} else {
    echo "<p style='color:red'><b>Player URL NOT found</b></p>";
    // Try to find ANY iframe
    if (preg_match('/<iframe[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $m2)) {
        echo "<p>Found other iframe: " . htmlspecialchars($m2[1]) . "</p>";
    }
}

$videoSrc = '';
if ($playerUrl) {
    if (preg_match('/[?&;]source=([^&;]+)/i', $playerUrl, $m)) {
        $videoSrc = urldecode($m[1]);
        echo "<p><b>Video source extracted:</b> " . htmlspecialchars($videoSrc) . "</p>";
        echo "<p><b>Video source (raw):</b> " . htmlspecialchars($m[1]) . "</p>";
    } else {
        echo "<p style='color:red'><b>No source parameter found in player URL</b></p>";
        echo "<p>Player URL components:</p><pre>" . htmlspecialchars($playerUrl) . "</pre>";
    }
}

// Also try contentUrl
if (preg_match('/itemprop="contentUrl"\s+content="([^"]+)"/i', $html, $m)) {
    echo "<p><b>contentUrl found:</b> " . htmlspecialchars($m[1]) . "</p>";
}

// Check for download links
if (preg_match('/<div class=[\'"]_4continuar[\'"]>/i', $html, $m)) {
    echo "<p>Download section found</p>";
} else {
    echo "<p>No download section found</p>";
}

echo "<h3>HTML snippet (first 3000 chars):</h3><pre>" . htmlspecialchars(substr($html, 0, 3000)) . "</pre>";
