<?php

declare(strict_types=1);

/**
 * Endpoint tester — hits the running API and verifies status + JSON + data.
 *
 * Usage:
 *   php bin/test-endpoints.php [base-url]
 *
 * Default base URL: http://localhost:8000
 */

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8000', '/');

$tests = [
    ['GET', '/api/health', 200, 'status'],
    ['GET', '/api/home', 200, 'spotlight'],
    ['GET', '/api/latest?type=all', 200, 'items'],
    ['GET', '/api/latest?type=series', 200, 'items'],
    ['GET', '/api/latest?type=movies', 200, 'items'],
    ['GET', '/api/latest?type=bogus', 404, null],
    ['GET', '/api/top?range=day', 200, 'items'],
    ['GET', '/api/top?range=week', 200, 'items'],
    ['GET', '/api/top?range=month', 200, 'items'],
    ['GET', '/api/top?range=year', 404, null],
    ['GET', '/api/search?q=naruto', 200, 'results'],
    ['GET', '/api/search?q=', 400, null],
    ['GET', '/api/search?q=n', 400, null],
    ['GET', '/api/anime/naruto-shippuden-hindi-dubbed-multi-audio-bluray-episodes-watch-online-download', 200, 'seasons'],
    ['GET', '/api/anime/naruto-shippuden-hindi-dubbed-multi-audio-bluray-episodes-watch-online-download/episodes', 200, 'seasons'],
    ['GET', '/api/anime/this-slug-does-not-exist-xyz', 404, null],
    ['GET', '/api/episode/35278977b149', 200, 'servers'],
    ['GET', '/api/movie/34d9ed3a8bd4', 200, 'servers'],
    ['GET', '/api/category/type/series', 200, 'items'],
    ['GET', '/api/category/lang/hindi', 200, 'items'],
    ['GET', '/api/category/genre/action', 200, 'items'],
    ['GET', '/api/category/bogus/whatever', 404, null],
    ['GET', '/api/languages', 200, 'languages'],
    ['GET', '/api/genres', 200, 'genres'],
    ['GET', '/api/studios', 200, 'studios'],
    ['GET', '/api/extra', 200, 'menu'],
    ['GET', '/api/proxy/watch?server=thugs&hash=0ca2b', 200, 'source'],
    ['GET', '/api/proxy/watch?server=bogus&hash=abc', 400, null],
    ['GET', '/api/proxy/download?url=https%3A%2F%2Fanimetadka.com%2Fredirect%2Fdownload%2F%3Fdd%3D5933e', 200, 'mirrors'],
    ['GET', '/api/does-not-exist', 404, null],
];

$pass = 0;
$fail = 0;

printf("%-90s %-6s %-6s %-6s %s\n", 'ENDPOINT', 'HTTP', 'JSON', 'DATA', 'STATUS');
printf("%s\n", str_repeat('-', 120));

foreach ($tests as [$method, $path, $expectedStatus, $expectedKey]) {
    $url = $baseUrl . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'animetadka-api-tester/1.0',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    // Note: curl handle is closed automatically since PHP 8.0.

    $jsonOk = false;
    $dataOk = false;
    $decoded = null;

    if ($body !== false) {
        $decoded = json_decode($body, true);
        $jsonOk = is_array($decoded);
    }

    if ($jsonOk && $expectedKey !== null) {
        $dataOk = isset($decoded['data'][$expectedKey]);
    }

    $statusOk = $status === $expectedStatus;
    $result = ($statusOk && $jsonOk && ($expectedKey === null || $dataOk)) ? 'PASS' : 'FAIL';

    if ($result === 'PASS') {
        $pass++;
    } else {
        $fail++;
    }

    printf(
        "%-90s %-6s %-6s %-6s %s%s\n",
        $path,
        $status . ($statusOk ? '' : '!'),
        $jsonOk ? '✓' : '✗',
        $expectedKey === null ? 'N/A' : ($dataOk ? '✓' : '✗'),
        $result,
        $curlError !== '' ? " (curl: {$curlError})" : ''
    );
}

printf("%s\n", str_repeat('-', 120));
printf("TOTAL: %d  PASS: %d  FAIL: %d\n", $pass + $fail, $pass, $fail);

exit($fail === 0 ? 0 : 1);