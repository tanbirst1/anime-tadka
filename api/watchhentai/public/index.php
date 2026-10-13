<?php

declare(strict_types=1);

/**
 * Front controller — the only public entry point.
 *
 * This single file works from two layouts:
 *   - shared hosting: document root = project root (index.php sits next to
 *     app/, storage/, config.json),
 *   - local dev:      `php -S 127.0.0.1:8787 -t public public/index.php`
 *                     (this file sits inside public/).
 * The autoloader defines WATCHHENTAI_ROOT from its own location, so both
 * setups resolve the same app/ tree.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$autoload = is_file(__DIR__ . '/app/autoload.php')
    ? __DIR__ . '/app/autoload.php'
    : __DIR__ . '/../app/autoload.php';
require $autoload;

(new WatchHentaiApi\Core\App())->run();
