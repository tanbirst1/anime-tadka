<?php

declare(strict_types=1);

/**
 * Absolute path of the project root (the directory containing app/).
 * Works from public/ (local dev) and from the htdocs root (shared hosting).
 */
if (!defined('WATCHHENTAI_ROOT')) {
    define('WATCHHENTAI_ROOT', dirname(__DIR__));
}

/**
 * PSR-4 style autoloader for the WatchHentaiApi namespace.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'WatchHentaiApi\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
