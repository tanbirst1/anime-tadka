<?php
require dirname(__DIR__) . '/src/autoload.php';
use AnimeTadka\Bootstrap;
$config = Bootstrap::config();
$http = Bootstrap::httpClient($config);
try {
    $body = $http->post('https://animetadka.com/wp-admin/admin-ajax.php', ['action' => 'anime_search', 'term' => 'Ju']);
    file_put_contents(__DIR__ . '/raw-search.html', $body);
    echo 'OK bytes=' . strlen($body) . "\n";
    echo substr($body, 0, 3500) . "\n";
} catch (\Throwable $e) {
    echo 'ERR ' . $e->getMessage() . "\n";
}