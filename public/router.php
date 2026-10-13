<?php
/**
 * Router for PHP built-in dev server.
 * Serves static files directly, routes everything else to index.php.
 * Image URLs are already normalized to wsrv.nl CDN by HtmlParser.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

require __DIR__ . '/index.php';
