<?php
define('DEFAULT_PROVIDER', 'anikoto');
define('TIMEOUT', 15);
define('CACHE_DIR', __DIR__ . '/cache');
define('BASE_URLS', json_encode([
  'anikai' => 'https://anikai.to',
  'anikoto' => 'https://anikototv.to',
  'anikotoApi' => 'https://anikotoapi.site',
  'hianime' => 'https://hianime.to',
]));
define('HEADERS', json_encode([
  'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
  'Accept-Language' => 'en-US,en;q=0.5',
  'Accept-Encoding' => 'gzip, deflate, br',
  'Connection' => 'keep-alive',
  'Upgrade-Insecure-Requests' => '1',
]));
define('API_HEADERS', json_encode([
  'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  'Accept' => 'application/json',
  'Accept-Language' => 'en-US,en;q=0.5',
  'Accept-Encoding' => 'gzip, deflate, br',
]));

function baseUrl($key) {
  $urls = json_decode(BASE_URLS, true);
  return $urls[$key] ?? null;
}
