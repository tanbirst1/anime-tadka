<?php

declare(strict_types=1);

/**
 * Global base-URL registry for every provider the API aggregates.
 *
 * Single source of truth — change a mirror here and every provider
 * that resolves from this file picks up the new URL automatically.
 *
 * Usage:
 *   $urls = require __DIR__ . '/baseurl.php';
 *   $anikotoBase = $urls['anikoto'];
 */

return [

    // ── Animetadka ──────────────────────────────────────────────────
    'animetadka' => 'https://animetadka.com',

    // ── Anikoto ─────────────────────────────────────────────────────
    'anikoto'        => 'https://anikototv.to',
    'anikotoApi'     => 'https://anikotoapi.site',
    'anikai'         => 'https://anikai.to',
    'hianime'        => 'https://hianime.to',

    // ── Toonstream ──────────────────────────────────────────────────
    'toonstream'     => 'https://toonstream.us',

    // ── WatchHentai ─────────────────────────────────────────────────
    'watchhentai'    => 'https://watchhentai.net',
];
