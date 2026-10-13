<?php

declare(strict_types=1);

/**
 * Stub HTTP server for HttpClient tests.
 *
 * Run: php -S 127.0.0.1:8899 tests/stub-server.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$counterFile = __DIR__ . '/stub-counter.txt';

switch ($path) {
    case '/ok':
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><head><title>Stub OK</title></head><body><h1>Hello</h1></body></html>';
        break;

    case '/count':
        $count = (int) @file_get_contents($counterFile);
        $count++;
        @file_put_contents($counterFile, (string) $count);
        header('Content-Type: text/html');
        echo "count={$count}";
        break;

    case '/notfound':
        http_response_code(404);
        header('Content-Type: text/html');
        echo 'Not Found';
        break;

    case '/error500':
        http_response_code(500);
        header('Content-Type: text/html');
        echo 'Server Error';
        break;

    case '/ratelimit':
        http_response_code(429);
        header('Content-Type: text/html');
        echo 'Too Many Requests';
        break;

    case '/slow':
        sleep(3);
        header('Content-Type: text/html');
        echo 'Slow response';
        break;

    case '/malformed':
        header('Content-Type: application/json');
        echo '{"not":"html"}';
        break;

    case '/reset':
        @unlink($counterFile);
        header('Content-Type: text/html');
        echo 'reset';
        break;

    default:
        http_response_code(404);
        header('Content-Type: text/html');
        echo 'Unknown stub path';
}