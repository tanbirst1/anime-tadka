<?php
require_once __DIR__ . '/config.php';

const TTL = [
  'HOME' => 1800,
  'SEARCH' => 600,
  'BROWSE' => 900,
  'ANIME' => 3600,
  'EPISODES' => 1800,
  'EPISODE' => 1800,
  'GENRE' => 900,
  'AZLIST' => 3600,
  'NAV' => 3600,
];

if (!is_dir(CACHE_DIR)) {
  @mkdir(CACHE_DIR, 0777, true);
}

function cacheGet($key) {
  $path = CACHE_DIR . '/' . md5($key) . '.cache';
  if (!file_exists($path)) return null;
  $data = unserialize(file_get_contents($path));
  if (!$data || time() > $data['expires']) {
    @unlink($path);
    return null;
  }
  return $data['value'];
}

function cacheSet($key, $value, $ttl) {
  $path = CACHE_DIR . '/' . md5($key) . '.cache';
  $data = serialize(['value' => $value, 'expires' => time() + $ttl]);
  $tmp = $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, $data) !== false) {
    @rename($tmp, $path);
    @unlink($tmp);
  } else {
    @file_put_contents($path, $data);
  }
}

function cacheDel($key) {
  $path = CACHE_DIR . '/' . md5($key) . '.cache';
  @unlink($path);
}

function cachePurge() {
  $now = time();
  foreach (glob(CACHE_DIR . '/*.cache') as $f) {
    $data = @unserialize(file_get_contents($f));
    if ($data && $now > $data['expires']) @unlink($f);
  }
}

function cacheStats() {
  $alive = $expired = 0;
  $now = time();
  foreach (glob(CACHE_DIR . '/*.cache') as $f) {
    $data = @unserialize(file_get_contents($f));
    if (!$data) { @unlink($f); continue; }
    $now > $data['expires'] ? $expired++ : $alive++;
  }
  return ['totalKeys' => $alive + $expired, 'aliveKeys' => $alive, 'expiredKeys' => $expired];
}

function withCache($key, $ttl, $fn) {
  $bypass = ($_GET['nocache'] ?? '') === '1';
  if (!$bypass) {
    $hit = cacheGet($key);
    if ($hit !== null) {
      header('X-Cache: HIT');
      header('X-Cache-TTL: ' . $ttl);
      $hit['_cached'] = true;
      return ['success' => true, 'data' => $hit];
    }
  }
  header('X-Cache: MISS');
  header('X-Cache-TTL: ' . $ttl);
  $data = $fn();
  if (!$bypass) cacheSet($key, $data, $ttl);
  return ['success' => true, 'data' => $data];
}
