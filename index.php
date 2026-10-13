<?php
/**
 * InfinityFree entry point.
 *
 * The shared-hosting document root is htdocs/. This file forwards
 * all requests to the real front controller at public/index.php.
 */

// Rewrite REQUEST_URI so the router never sees "/public/".
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_URI'] = preg_replace('#^/public(?:/|$)#', '/', $requestUri);

if (!file_exists(__DIR__.'/.env') && file_exists(__DIR__.'/.env.example')) @copy(__DIR__.'/.env.example', __DIR__.'/.env');
require __DIR__ . '/public/index.php';
