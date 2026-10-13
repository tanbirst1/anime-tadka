<?php
// PHP 7.4 polyfills for InfinityFree
if (!function_exists('str_starts_with')) { function str_starts_with(string $h, string $n): bool { return $n === '' || strncmp($h,$n,strlen($n))===0; }}
if (!function_exists('str_contains')) { function str_contains(string $h, string $n): bool { return $n === '' || strpos($h,$n)!==false; }}
if (!function_exists('str_ends_with')) { function str_ends_with(string $h, string $n): bool { return $n === '' || substr($h,-strlen($n))===$n; }}
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
define('ROOT_PATH', dirname(__DIR__));
try {
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($reqPath === '/api' || str_starts_with($reqPath, '/api/')) {
    require ROOT_PATH . '/api/public/index.php';
    exit;
}
require_once ROOT_PATH . '/app/Helpers/Config.php';
if (!file_exists(ROOT_PATH.'/.env') && file_exists(ROOT_PATH.'/.env.example')) @copy(ROOT_PATH.'/.env.example', ROOT_PATH.'/.env');
Config::load();
require_once ROOT_PATH . '/app/Helpers/Logger.php';
require_once ROOT_PATH . '/app/Helpers/Router.php';
require_once ROOT_PATH . '/app/Helpers/View.php';
require_once ROOT_PATH . '/app/Helpers/Url.php';
require_once ROOT_PATH . '/app/Helpers/Database.php';
require_once ROOT_PATH . '/app/Helpers/Fixtures.php';
require_once ROOT_PATH . '/app/Helpers/ApiClient.php';
require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
Logger::init();
View::share('appName', Config::get('APP_NAME', 'AnimeTadka'));
View::share('appUrl', Config::get('APP_URL', ''));
View::share('appDebug', Config::get('APP_DEBUG', false));
$router = new Router();
require_once ROOT_PATH . '/routes/web.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$queryString = parse_url($uri, PHP_URL_QUERY);
if ($queryString) { parse_str($queryString, $parsedQuery); $_GET = array_merge($_GET, $parsedQuery); }
$path = parse_url($uri, PHP_URL_PATH);
if (($path === '/' || $path === '') && isset($_GET['s']) && trim((string)$_GET['s']) !== '') {
    $controller = new SearchController();
    $controller->index();
    exit;
}
$router->dispatch($method, $uri);
} catch (Throwable $e) {
    http_response_code(500);
    error_log((string)$e);
    if (ini_get('display_errors')) echo '<pre>'.htmlspecialchars($e->getMessage().' at '.$e->getFile().':'.$e->getLine()).'</pre>';
    else echo '<h1>Debug</h1><pre>'.htmlspecialchars($e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n".substr($e->getTraceAsString(),0,2000)).'</pre>';
}
