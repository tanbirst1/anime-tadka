<?php
/**
 * Global site configuration
 *
 * Central place for site-wide settings. Edit values here and the whole
 * site picks them up.
 *
 * Available keys:
 *   site_name   — website name shown in titles, footer, share text, etc.
 *   base_url    — auto-detected from the current request (scheme + host).
 *   api_url     — auto-derived: {base_url}/api
 *   icon        — favicon path (relative to /public)
 *   logo        — header/landing logo path (relative to /public)
 *   main_image  — og:image / preview image path (relative to /public)
 *   socials     — social share/profile links (used by share buttons)
 */

// --- Auto-detect base URL -------------------------------------------------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$baseUrl = ($https ? 'https' : 'http') . '://' . $host;

return [
    // Website
    'site_name'  => 'AniTadka',
    'base_url'   => $baseUrl,
    'api_url'    => $baseUrl . '/api',

    // Content provider: 'animetadka' (default) | 'anikoto' | 'toonstream' | 'animedekho' | 'watchhentai'
    // Change this to switch the data source for all pages.
    'provider' => 'animedekho',

    // Assets (relative to /public)
    'icon'       => '/assets/static/icon.png',
    'logo'       => '/assets/static/logo.webp',
    'main_image' => '/assets/static/main_img.png',

    // Ad Timer — set to minutes, 0 = disabled (no timer, no popup)
    'ad_timer'   => [
        'enabled' => false,     // false = completely hide timer & popup
        'minutes' => 5,         // countdown duration in minutes (e.g. 5, 10, 15)
    ],

    // Visitor-facing "Skip Ads" verification gate (header status widget +
    // /skip-ads page). false = visitors are never asked to verify or given a
    // token: the server resolves the upstream ad-gate token itself and serves
    // the original sources directly. Set true to restore the old behaviour.
    'skip_ads_gate' => false,

    // Social links (used by the share buttons on landing + anime pages)
    'socials'    => [
        'whatsapp'  => 'https://api.whatsapp.com/send?text=',
        'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=',
        'telegram'  => 'https://t.me/+2XKc4jsHo_RlNDdl',
        'messenger' => 'fb-messenger://share/?link=',
        'sms'       => 'sms:?body=',
    ],

    // Scraper cache TTLs (seconds) — longer = fewer external HTTP requests
    'cache_ttl' => [
        'home'     => 7200,     // 2 hours
        'top'      => 7200,     // 2 hours
        'anime'    => 86400,    // 24 hours
        'episode'  => 43200,    // 12 hours
        'search'   => 1800,     // 30 minutes
        'category' => 3600,     // 1 hour
        'letter'   => 3600,     // 1 hour
    ],
];