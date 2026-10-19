<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper;

use AnimeTadka\Cache\FileCache;
use AnimeTadka\Config;
use AnimeTadka\Exceptions\HttpException;
use AnimeTadka\Exceptions\NotFoundException;
use AnimeTadka\Exceptions\ParseException;
use AnimeTadka\Exceptions\ScraperException;
use AnimeTadka\Scraper\Parsers\AnimeParser;
use AnimeTadka\Scraper\Parsers\CategoryParser;
use AnimeTadka\Scraper\Parsers\EpisodeParser;
use AnimeTadka\Scraper\Parsers\ExtraParser;
use AnimeTadka\Scraper\Parsers\HomeParser;
use AnimeTadka\Scraper\Parsers\ProxyParser;
use AnimeTadka\Scraper\Parsers\ScheduleParser;
use AnimeTadka\Scraper\Parsers\SearchParser;
use AnimeTadka\Scraper\Parsers\TopParser;

/**
 * High-level scraper facade for animetadka.com.
 *
 * HTTP → Parser → Normalized Data. No scraping logic lives in controllers.
 */
final class AnimeTadkaScraper
{
    private HttpClient $http;
    private Config $config;
    private ?FileCache $cache;
    private array $keyTtl = [];
    /** @var array<string, array{expires:int, data:array}> per-request JSON memory */
    private array $memory = [];

    public function __construct(HttpClient $http, Config $config, ?FileCache $cache = null)
    {
        $this->http = $http;
        $this->config = $config;
        $this->cache = $cache;
        $this->keyTtl = [
            'home'     => 7200,
            'top'      => 7200,
            'anime'    => 86400,
            'episode'  => 43200,
            'search'   => 1800,
            'category' => 3600,
            'letter'   => 3600,
            'latest'   => 3600,
            'all'      => 3600,
            'schedule' => 3600,
        ];
    }

    /**
     * Get a cached JSON result, or null on miss.
     */
    private function cacheGet(string $key): ?array
    {
        $ttl = $this->resolveTtl($key);
        // memory fast path
        if (isset($this->memory[$key])) {
            $entry = $this->memory[$key];
            if ($entry['expires'] === 0 || $entry['expires'] > time()) {
                return $entry['data'];
            }
            unset($this->memory[$key]);
        }
        if ($this->cache === null) {
            return null;
        }
        $raw = $this->cache->get($key, $ttl);
        if ($raw === null) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        $this->memory[$key] = ['data' => $data, 'expires' => $ttl > 0 ? time() + $ttl : 0];
        if (count($this->memory) > 200) {
            array_shift($this->memory);
        }
        return $data;
    }

    /**
     * Store a parsed result as JSON in cache.
     */
    private function cacheSet(string $key, array $data): void
    {
        $ttl = $this->resolveTtl($key);
        $this->memory[$key] = ['data' => $data, 'expires' => $ttl > 0 ? time() + $ttl : 0];
        if (count($this->memory) > 200) {
            array_shift($this->memory);
        }
        if ($this->cache !== null) {
            $this->cache->set($key, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $ttl);
        }
    }

    private function resolveTtl(string $key): int
    {
        foreach ($this->keyTtl as $prefix => $ttl) {
            if (str_starts_with($key, $prefix)) {
                return $ttl;
            }
        }
        return 3600;
    }

    public function baseUrl(): string
    {
        return rtrim($this->config->string('BASE_URL', 'https://animetadka.com'), '/');
    }

    /**
     * Home listing: spotlight + latest grids + top-10 (day/week/month) + languages.
     * Tops are fetched in parallel via curl_multi to avoid 3 sequential POSTs.
     *
     * @return array<string, mixed>
     */
    public function getHome(): array
    {
        $cacheKey = 'home';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $html = $this->http->getHtml($this->baseUrl() . '/home/');
        $data = (new HomeParser(new HtmlParser($html, $this->baseUrl())))->parse();

        // Fetch top animes in parallel when possible
        $ranges = ['day', 'week', 'month'];
        $tops = [];
        $misses = [];
        foreach ($ranges as $r) {
            $cachedTop = $this->cacheGet("top:{$r}");
            if ($cachedTop !== null) {
                $tops[$r] = $cachedTop;
            } else {
                $misses[] = $r;
            }
        }

        if ($misses !== []) {
            $url = $this->baseUrl() . '/wp-admin/admin-ajax.php';
            $payloads = [];
            foreach ($misses as $r) {
                $payloads[] = [
                    'action' => 'get_top_animes',
                    'range' => $r,
                    '_t' => (string) (int) floor(time() / 3600),
                ];
            }
            // Try parallel POST first
            try {
                $bodies = $this->http->postMulti($url, $payloads);
                foreach ($misses as $idx => $r) {
                    $body = $bodies[$idx] ?? null;
                    if ($body !== null && trim($body) !== '' && trim($body) !== '0') {
                        try {
                            $parsed = (new TopParser(new HtmlParser($body, $this->baseUrl())))->parse();
                            $this->cacheSet("top:{$r}", $parsed);
                            $tops[$r] = $parsed;
                            continue;
                        } catch (\Throwable $e) {
                            // fall through to sequential retry
                        }
                    }
                    // Fallback sequential with retry logic
                    try {
                        $tops[$r] = $this->getTopAnimes($r);
                    } catch (\Throwable $e) {
                        $tops[$r] = [];
                    }
                }
            } catch (\Throwable $e) {
                // Multi failed entirely, fallback to sequential
                foreach ($misses as $r) {
                    try {
                        $tops[$r] = $this->getTopAnimes($r);
                    } catch (\Throwable $ex) {
                        $tops[$r] = [];
                    }
                }
            }
        }

        // Ensure all ranges present
        foreach ($ranges as $r) {
            if (!isset($tops[$r])) {
                try {
                    $tops[$r] = $this->getTopAnimes($r);
                } catch (\Throwable $e) {
                    $tops[$r] = [];
                }
            }
        }

        $data['top'] = $tops;

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * "Top Animes" ranking via the public admin-ajax endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopAnimes(string $range = 'day'): array
    {
        $range = strtolower(trim($range));
        if (!in_array($range, ['day', 'week', 'month'], true)) {
            throw new NotFoundException("Unknown top range: {$range}");
        }

        $cacheKey = "top:{$range}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $body = $this->http->post($this->baseUrl() . '/wp-admin/admin-ajax.php', [
            'action' => 'get_top_animes',
            'range' => $range,
            '_t' => (string) (int) floor(time() / 3600),
        ]);

        $body = trim($body);
        if ($body === '' || $body === '0') {
            throw new ParseException("Top animes endpoint returned no data for range: {$range}");
        }

        $data = (new TopParser(new HtmlParser($body, $this->baseUrl())))->parse();
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Live search via the public admin-ajax endpoint.
     *
     * @return array<string, mixed>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        $cacheKey = 'search:' . sha1($query);
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $body = $this->http->post($this->baseUrl() . '/wp-admin/admin-ajax.php', [
            'action' => 'anime_search',
            'term' => $query,
        ]);

        $results = (new SearchParser(new HtmlParser($body, $this->baseUrl())))->parse();

        $data = [
            'query' => $query,
            'results' => $results,
        ];
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Anime / series detail page.
     *
     * @return array<string, mixed>
     */
    public function getAnime(string $slugOrUrl): array
    {
        $url = $this->resolveContentUrl($slugOrUrl, 'anime');

        $cacheKey = 'anime:' . sha1($url);
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $html = $this->http->getHtml($url);

        $data = (new AnimeParser(new HtmlParser($html, $url)))->parse();
        $data['slug'] = $this->slugFromUrl($url);
        $data['url'] = $url;

        // Premium UI: episodes load via admin-ajax; backfill them so
        // multi-episode series are not mistaken for single-unit movies.
        $data['seasons'] = $this->backfillPremiumEpisodes($html, $data['seasons'] ?? []);

        // Movies have no season/episode blocks: their player + download rows
        // live on the /movie/{hash}/ watch page the detail page links to.
        // Follow that link so movie detail returns real servers/links too.
        if (empty($data['seasons']) && !empty($data['watch_url'])) {
            $movie = $this->getMovie((string) $data['watch_url']);
            if (!empty($movie['servers'])) {
                $data['servers'] = $movie['servers'];
                $data['download_links'] = $movie['download_links'] ?? [];
                $data['player_source'] = $movie['player_source'] ?? ($movie['servers'][0]['source'] ?? null);
            }
        }

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Episodes only (from the anime detail page).
     *
     * @return array<string, mixed>
     */
    public function getAnimeEpisodes(string $slugOrUrl): array
    {
        $url = $this->resolveContentUrl($slugOrUrl, 'anime');

        $cacheKey = 'anime-episodes:' . sha1($url);
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $html = $this->http->getHtml($url);

        $parser = new AnimeParser(new HtmlParser($html, $url));
        $data = $parser->parse();
        $data['seasons'] = $this->backfillPremiumEpisodes($html, $data['seasons'] ?? []);

        $result = [
            'title' => $data['title'],
            'url' => $url,
            'seasons' => $data['seasons'],
        ];
        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Episode watch page (/ep/{hash}/).
     *
     * @return array<string, mixed>
     */
    public function getEpisode(string $hashOrUrl): array
    {
        $url = $this->resolveContentUrl($hashOrUrl, 'ep');

        $cacheKey = 'episode:' . sha1($url);
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $html = $this->http->getHtml($url);

        $data = (new EpisodeParser(new HtmlParser($html, $url)))->parse();
        $data['url'] = $url;
        $data['hash'] = $this->hashFromUrl($url);

        $this->enrichWatchSources($data);

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Movie watch page (/movie/{hash}/).
     *
     * @return array<string, mixed>
     */
    public function getMovie(string $hashOrUrl): array
    {
        $url = $this->resolveContentUrl($hashOrUrl, 'movie');

        $cacheKey = 'movie:' . sha1($url);
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $html = $this->http->getHtml($url);

        $data = (new EpisodeParser(new HtmlParser($html, $url)))->parse();
        $data['url'] = $url;
        $data['hash'] = $this->hashFromUrl($url);

        $this->enrichWatchSources($data);

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Resolve the CDN player URL for a watch.php stream server.
     *
     * Primary: extracts <iframe> src from the watch.php HTML.
     * Fallback: known server→CDN patterns when scraping fails.
     *
     * @return array{server: string, hash: string, source: string|null, extracted: bool, watch_url: string}
     */
    public function getWatchSource(string $server, string $hash, bool $refresh = false): array
    {
        $server = strtolower(trim($server));

        $watchUrl = $this->baseUrl() . '/watch.php?' . rawurlencode($server) . '=' . rawurlencode($hash);

        $source = null;
        $extracted = false;

        // --- Primary: scrape the watch.php page for the real iframe src ---
        try {
            // watch.php serves an anti-hotlink troll page without a same-site
            // Referer, so always send one.
            $html = $this->http->getHtml($watchUrl, [
                'force_refresh' => $refresh,
                'referer' => $this->baseUrl() . '/',
            ]);

            if (!ProxyParser::isCloudflareChallenge($html)) {
                $source = ProxyParser::extractIframeSource($html);

                // Fallback within the HTML: find any external iframe
                if ($source === null) {
                    $source = $this->extractAnyIframeSrc($html);
                }
            }

            if ($source !== null) {
                $extracted = true;
            }
        } catch (\Throwable $e) {
            // Transport failure — try pattern fallback below.
        }

        // --- Fallback: known server→CDN embed patterns ---
        if ($source === null) {
            $source = $this->cdnPatternFallback($server, $hash);
        }

        return [
            'server' => $server,
            'hash' => $hash,
            'source' => $source,
            'extracted' => $extracted,
            'watch_url' => $watchUrl,
        ];
    }

    /**
     * Fallback CDN patterns when scraping fails.
     * Returns a constructed embed URL, or null if the server is unknown.
     */
    private function cdnPatternFallback(string $server, string $hash): ?string
    {
        $patterns = [
            'toonplay' => 'https://ravok.buzz/video/',
            'vix'      => 'https://gbadosadl.com/e/',
            'flco'     => 'https://blakiteapi.xyz/embed/',
            'tox'      => 'https://short.azonahub.biz/embed/',
        ];

        if (isset($patterns[$server])) {
            return $patterns[$server] . $hash;
        }

        return null;
    }

    /**
     * Fallback iframe extractor: finds any <iframe> with an http(s) src
     * that is NOT an animetadka.com URL (i.e. an external embed).
     */
    private function extractAnyIframeSrc(string $html): ?string
    {
        if (preg_match_all('/<iframe[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $src = trim($src);
                if ($src === '') continue;
                $host = strtolower((string) (parse_url($src, PHP_URL_HOST) ?? ''));
                // Skip empty, data: URIs, and animetadka.com self-references
                if ($host !== '' && !str_contains($host, 'animetadka')) {
                    return $src;
                }
            }
        }
        return null;
    }

    /**
     * Resolve the playable source for a watch.php URL (as returned in
     * episode/movie player_url and server entries), or null when the URL
     * is not a valid animetadka.com watch.php URL.
     *
     * @return array{server: string, hash: string, source: string|null, extracted: bool, watch_url: string}|null
     */
    public function resolveWatchSource(string $url): ?array
    {
        $serverHash = $this->watchServerFromUrl($url);
        if ($serverHash === null) {
            return null;
        }

        return $this->getWatchSource($serverHash['server'], $serverHash['hash']);
    }

    /**
     * Extract the server/hash pair from an animetadka.com watch.php URL.
     * Works with ANY server type — no hardcoded whitelist.
     *
     * @return array{server: string, hash: string}|null
     */
    private function watchServerFromUrl(string $url): ?array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (!in_array($host, ['animetadka.com', 'www.animetadka.com'], true) || $path !== '/watch.php') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $qs);

        // Take the first query parameter that looks like server=hash
        foreach ($qs as $key => $value) {
            $hash = trim((string) $value);
            if ($hash !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $hash)) {
                return ['server' => strtolower(trim($key)), 'hash' => $hash];
            }
        }

        return null;
    }

    /**
     * Resolve and embed the playable source for player_url and each server
     * entry of an episode/movie watch page — batched via curl_multi.
     *
     * @param array<string, mixed> $data
     */
    private function enrichWatchSources(array &$data): void
    {
        // Collect all watch.php URLs that need resolving
        $targets = []; // list of ['kind'=>'player'|'server','idx'=>int,'ph'=>[server,hash],'watchUrl'=>string]
        $watchUrls = [];

        $playerUrl = (string) ($data['player_url'] ?? '');
        $ph = $this->watchServerFromUrl($playerUrl);
        if ($ph !== null) {
            $watchUrl = $this->baseUrl() . '/watch.php?' . rawurlencode($ph['server']) . '=' . rawurlencode($ph['hash']);
            $targets[] = ['kind' => 'player', 'ph' => $ph, 'watchUrl' => $watchUrl];
            $watchUrls[] = $watchUrl;
        }

        foreach ($data['servers'] as $idx => $server) {
            $u = (string) ($server['url'] ?? '');
            $sh = $this->watchServerFromUrl($u);
            if ($sh !== null) {
                $watchUrl = $this->baseUrl() . '/watch.php?' . rawurlencode($sh['server']) . '=' . rawurlencode($sh['hash']);
                $targets[] = ['kind' => 'server', 'idx' => $idx, 'ph' => $sh, 'watchUrl' => $watchUrl];
                $watchUrls[] = $watchUrl;
            }
        }

        if ($targets === []) {
            $data['player_source'] = null;
            $data['player_source_extracted'] = false;
            foreach ($data['servers'] as &$s) {
                $s['source'] = $s['source'] ?? $this->cdnPatternFallback(strtolower((string)($s['label'] ?? '')), '') ?? null;
                $s['source_extracted'] = false;
            }
            unset($s);
            return;
        }

        // Deduplicate URLs for fetching
        $uniqueUrls = array_values(array_unique($watchUrls));
        $bodies = [];
        try {
            // watch.php requires a same-site Referer (see getWatchSource).
            $bodies = $this->http->getMulti($uniqueUrls, ['referer' => $this->baseUrl() . '/']);
        } catch (\Throwable $e) {
            $bodies = [];
        }

        // Build lookup: watchUrl => [source, extracted]
        $lookup = [];
        foreach ($uniqueUrls as $wUrl) {
            $body = $bodies[$wUrl] ?? null;
            $source = null;
            $extracted = false;
            if (is_string($body) && $body !== '' && !ProxyParser::isCloudflareChallenge($body)) {
                $source = ProxyParser::extractIframeSource($body);
                if ($source === null) {
                    $source = $this->extractAnyIframeSrc($body);
                }
                if ($source !== null) {
                    $extracted = true;
                }
            }
            // Fallback CDN pattern
            if ($source === null) {
                // extract server/hash from watchUrl for fallback
                $parts = parse_url($wUrl);
                parse_str((string)($parts['query'] ?? ''), $qs);
                $srv = strtolower((string) (array_key_first($qs) ?? ''));
                $hsh = (string) ($qs[$srv] ?? '');
                $source = $this->cdnPatternFallback($srv, $hsh);
            }
            $lookup[$wUrl] = ['source' => $source, 'extracted' => $extracted];
        }

        // Assign back
        $playerAssigned = false;
        foreach ($targets as $t) {
            $info = $lookup[$t['watchUrl']] ?? ['source' => null, 'extracted' => false];
            if ($t['kind'] === 'player' && !$playerAssigned) {
                $data['player_source'] = $info['source'];
                $data['player_source_extracted'] = $info['extracted'];
                $playerAssigned = true;
            } elseif ($t['kind'] === 'server') {
                $idx = $t['idx'];
                $data['servers'][$idx]['source'] = $info['source'];
                $data['servers'][$idx]['source_extracted'] = $info['extracted'];
            }
        }

        if (!$playerAssigned) {
            $data['player_source'] = null;
            $data['player_source_extracted'] = false;
        }

        // Ensure servers without watch.php get fallback
        foreach ($data['servers'] as &$s) {
            if (!array_key_exists('source', $s) || $s['source'] === null) {
                // try fallback based on label as server name (best effort)
                $labelSrv = strtolower(trim((string)($s['label'] ?? '')));
                $fallback = $this->cdnPatternFallback($labelSrv, '');
                $s['source'] = $fallback;
                $s['source_extracted'] = false;
            }
            if (!array_key_exists('source_extracted', $s)) {
                $s['source_extracted'] = false;
            }
        }
        unset($s);
    }

    /**
     * Follow the redirect chain of a /redirect/download/ URL and resolve the
     * final BoxDrive page (with mirror links) or direct file URL.
     *
     * @return array<string, mixed>
     */
    public function resolveDownload(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (!in_array($host, ['animetadka.com', 'www.animetadka.com'], true) || !str_starts_with($path, '/redirect/download/')) {
            throw new NotFoundException('Invalid download URL.');
        }

        $current = $url;

        for ($hop = 0; $hop < 8; $hop++) {
            $result = $this->http->getWithMeta($current, ['force_refresh' => true]);
            $status = (int) $result['status'];
            $headers = $result['headers'];
            $body = (string) $result['body'];

            if ($status >= 300 && $status < 400 && !empty($headers['location'])) {
                $current = $this->resolveLocation($current, $headers['location']);
                continue;
            }

            if ($status >= 400) {
                throw new HttpException($status, "Source returned HTTP {$status}: {$url}");
            }

            // 2xx — inspect the response to decide page vs direct file.
            $contentType = strtolower((string) ($headers['content-type'] ?? ''));
            $sniff = ltrim(substr($body, 0, 512));
            $looksHtml = str_contains($contentType, 'html')
                || str_contains($contentType, 'text/')
                || str_starts_with($sniff, '<');

            if ($looksHtml) {
                $page = ProxyParser::parseDownloadPage($body);

                return [
                    'url' => $url,
                    'final_url' => $current,
                    'kind' => 'page',
                    'title' => $page['title'],
                    'filename' => $page['filename'],
                    'mirrors' => $page['mirrors'],
                ];
            }

            return [
                'url' => $url,
                'final_url' => $current,
                'kind' => 'file',
                'filename' => $this->downloadFilename($headers, $current),
            ];
        }

        throw new ScraperException('Too many redirects.');
    }

    /**
     * Resolve a (possibly relative) Location header against the current URL.
     */
    private function resolveLocation(string $base, string $location): string
    {
        $loc = parse_url($location);
        if (isset($loc['scheme'])) {
            $scheme = strtolower((string) $loc['scheme']);
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new ScraperException("Unsupported redirect scheme: {$scheme}");
            }
            return $location;
        }

        $baseParts = parse_url($base);
        $scheme = strtolower((string) ($baseParts['scheme'] ?? ''));
        $host = (string) ($baseParts['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ScraperException("Cannot resolve relative redirect for: {$base}");
        }

        return $scheme . '://' . $host . $location;
    }

    /**
     * Best-effort filename from Content-Disposition or the URL path.
     *
     * @param array<string, string> $headers
     */
    private function downloadFilename(array $headers, string $url): string
    {
        $disposition = (string) ($headers['content-disposition'] ?? '');
        if ($disposition !== '') {
            if (preg_match('/filename="?([^"]*)"?/i', $disposition, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = basename($path);

        return $name !== '' && $name !== '.' ? $name : 'download';
    }

    /**
     * Listing pages: type, lang, genre, studio, sub.
     *
     * @return array<string, mixed>
     */
    public function getCategory(string $type, string $slug, int $page = 1, string $filter = ''): array
    {
        $type = strtolower(trim($type));
        $slug = strtolower(trim($slug));

        if (!in_array($type, ['type', 'lang', 'genre', 'studio', 'sub'], true)) {
            throw new NotFoundException("Unknown category type: {$type}");
        }

        $cacheKey = "category:{$type}:{$slug}:{$page}:{$filter}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/' . $type . '/' . rawurlencode($slug) . '/';
        if ($page > 1) {
            $url .= 'page/' . $page . '/';
        }
        $url = $this->applyTypeFilter($url, $filter);

        $html = $this->http->getHtml($url);
        $data = (new CategoryParser(new HtmlParser($html, $url)))->parse();
        $data['type'] = $type;
        $data['slug'] = $slug;
        $data['page'] = $page;
        $data['filter'] = $filter;

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Latest animes from the home page (all / series / movies).
     *
     * @return array<string, mixed>
     */
    public function getLatest(string $type = 'all', int $page = 1): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['all', 'series', 'movies'], true)) {
            throw new NotFoundException("Unknown latest type: {$type}");
        }

        $cacheKey = "latest:{$type}:{$page}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/home/';
        if ($page > 1) {
            $url = $this->baseUrl() . '/home/page/' . $page . '/';
        }

        $html = $this->http->getHtml($url);
        $data = (new HomeParser(new HtmlParser($html, $url)))->parse();

        $result = [
            'type' => $type,
            'page' => $page,
            'items' => $data['latest'][$type] ?? [],
        ];
        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Full "All Animes" listing from /all with pagination (24 per page).
     *
     * @return array<string, mixed>
     */
    public function getAllAnimes(int $page = 1): array
    {
        $page = max(1, $page);

        $cacheKey = "all:{$page}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/all/';
        if ($page > 1) {
            $url .= 'page/' . $page . '/';
        }

        $html = $this->http->getHtml($url);
        $data = (new CategoryParser(new HtmlParser($html, $url)))->parse();

        $result = [
            'page' => $page,
            'items' => $data['items'] ?? [],
            'pagination' => $data['pagination'] ?? [],
        ];
        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Weekly anime schedule from /schedule/.
     *
     * @return array<string, mixed>
     */
    public function getSchedule(): array
    {
        $cacheKey = 'schedule';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/schedule/';
        $html = $this->http->getHtml($url);
        $data = (new ScheduleParser(new HtmlParser($html, $url)))->parse();

        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Letter listing (A-Z) from /letter/{letter} with pagination.
     *
     * @return array<string, mixed>
     */
    public function getLetter(string $letter, int $page = 1, string $filter = ''): array
    {
        $letter = strtolower(trim($letter));
        if ($letter === '') {
            throw new NotFoundException('Letter is required');
        }
        $page = max(1, $page);

        $cacheKey = "letter:{$letter}:{$page}:{$filter}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/letter/' . rawurlencode($letter) . '/';
        if ($page > 1) {
            $url .= 'page/' . $page . '/';
        }
        $url = $this->applyTypeFilter($url, $filter);

        $html = $this->http->getHtml($url);
        $data = (new CategoryParser(new HtmlParser($html, $url)))->parse();

        $result = [
            'letter' => $letter,
            'page' => $page,
            'filter' => $filter,
            'items' => $data['items'] ?? [],
            'pagination' => $data['pagination'] ?? [],
        ];
        $this->cacheSet($cacheKey, $result);
        return $result;
    }

    /**
     * Appends the ?type=series|movies filter to a listing URL when requested.
     */
    private function applyTypeFilter(string $url, string $filter): string
    {
        $filter = strtolower(trim($filter));
        if (!in_array($filter, ['series', 'movies'], true)) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'type=' . $filter;
    }

    /**
     * Language list from the home page navigation.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function getLanguages(): array
    {
        $cacheKey = 'nav:languages';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }
        $data = $this->getTaxonomies('lang');
        if ($data === []) {
            $data = $this->parseNavLinks('Languages');
        }
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Genre list from the /genre/ index (falls back to the home nav).
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function getGenres(): array
    {
        $cacheKey = 'nav:genres';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }
        $data = $this->getTaxonomies('genre');
        if ($data === []) {
            $data = $this->parseNavLinks('Genres');
        }
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Studio list from the /studio/ index (falls back to the home nav).
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function getStudios(): array
    {
        $cacheKey = 'nav:studios';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }
        $data = $this->getTaxonomies('studio');
        if ($data === []) {
            $data = $this->parseNavLinks('Studios');
        }
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * Scrape a taxonomy index page (/lang/, /genre/, /studio/) whose cards
     * are `a.at-c` anchors: `data-n` holds the slug, `.at-nm` the display name.
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function getTaxonomies(string $type): array
    {
        if (!in_array($type, ['lang', 'genre', 'studio'], true)) {
            throw new NotFoundException("Unknown taxonomy type: {$type}");
        }

        $cacheKey = "taxonomy:{$type}";
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }

        $url = $this->baseUrl() . '/' . $type . '/';
        $html = $this->http->getHtml($url);
        $parser = new HtmlParser($html, $url);

        $items = [];
        foreach ($parser->query('//a[contains(@class,"at-c")]') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = $parser->firstTextWithin($node, ['.//div[contains(@class,"at-nm")]']);
            if ($name === null || $name === '') {
                $slug = $node->getAttribute('data-n');
                $name = $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : '';
            }
            if ($name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'url' => $parser->normalizeUrl($node->getAttribute('href')),
            ];
        }

        $this->cacheSet($cacheKey, $items);
        return $items;
    }

    /**
     * Site chrome: navigation menu, header logo, site logo, favicon icons.
     *
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        $cacheKey = 'extra';
        if (($cached = $this->cacheGet($cacheKey)) !== null) {
            return $cached;
        }
        $html = $this->http->getHtml($this->baseUrl() . '/home/');
        $data = (new ExtraParser(new HtmlParser($html, $this->baseUrl())))->parse();
        $this->cacheSet($cacheKey, $data);
        return $data;
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    private function parseNavLinks(string $blockLabel): array
    {
        $html = $this->http->getHtml($this->baseUrl() . '/home/');
        $parser = new HtmlParser($html, $this->baseUrl());

        $links = [];
        $xpath = '//details[contains(@class,"ak-native-drop")][.//summary//span[normalize-space(.)="' . $blockLabel . '"]]//a[contains(@class,"ak-sub-link")]';

        foreach ($parser->query($xpath) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $links[] = [
                'name' => HtmlParser::clean($node->textContent),
                'url' => $parser->normalizeUrl($node->getAttribute('href')),
            ];
        }

        return $links;
    }

    /**
     * Backfill episodes for the premium UI (site redesign ~2026).
     *
     * Seasons render as `.at-season-card` shells while episodes load via
     * POST {baseUrl}/wp-admin/admin-ajax.php action=at_get_season_episodes
     * (aid + nonce embedded in the page, incl. LiteSpeed base64 data: URIs).
     * Without this, series with AJAX-loaded episodes parse as 0 seasons and
     * callers synthesize a single-unit "Movie" entry for them.
     *
     * Movies have no `.at-season-card` / atAnimeId markers, so they pass
     * through untouched. All failures degrade to the input shells.
     *
     * @param array<int, array<string, mixed>> $seasons
     * @return array<int, array<string, mixed>>
     */
    private function backfillPremiumEpisodes(string $html, array $seasons): array
    {
        try {
            $needsFetch = false;
            foreach ($seasons as $s) {
                if (empty($s['episodes'])) {
                    $needsFetch = true;
                    break;
                }
            }
            if (!$needsFetch) {
                return $seasons;
            }

            // LiteSpeed inlines some scripts as base64 data: URIs — decode
            // them so the atAnimeId/atAjaxNonce/atSeasons vars are visible.
            $combined = $html;
            if (preg_match_all('#data:text/javascript;base64,([A-Za-z0-9+/=]+)#', $html, $mm)) {
                foreach ($mm[1] as $b) {
                    $decoded = base64_decode($b, true);
                    if (is_string($decoded) && $decoded !== '') {
                        $combined .= "\n" . $decoded;
                    }
                }
            }

            if (!preg_match('/atAnimeId\s*=\s*(\d+)/', $combined, $mAid)) {
                return $seasons;
            }
            if (!preg_match("/atAjaxNonce\s*=\s*'([^']+)'/", $combined, $mNonce)) {
                return $seasons;
            }
            $aid = $mAid[1];
            $nonce = $mNonce[1];

            foreach ($seasons as $i => $s) {
                if (!empty($s['episodes'])) {
                    continue;
                }
                $seasonNum = (string) ($s['season'] ?? '1');
                if ($seasonNum === '') {
                    $seasonNum = '1';
                }
                $uploaded = (int) ($s['uploaded'] ?? $s['total'] ?? 0);
                $maxPages = $uploaded > 0 ? (int) ceil($uploaded / 24) + 1 : 3;
                $maxPages = min(max($maxPages, 1), 60);

                $episodes = [];
                for ($page = 1; $page <= $maxPages; $page++) {
                    try {
                        $body = $this->http->post($this->baseUrl() . '/wp-admin/admin-ajax.php', [
                            'action' => 'at_get_season_episodes',
                            'security' => $nonce,
                            'aid' => $aid,
                            'season' => $seasonNum,
                            'page' => (string) $page,
                        ]);
                    } catch (\Throwable $e) {
                        break;
                    }
                    $resp = json_decode($body, true);
                    if (!is_array($resp) || empty($resp['success']) || !is_array($resp['data'] ?? null)) {
                        break;
                    }
                    $epHtml = (string) ($resp['data']['html'] ?? '');
                    if (trim($epHtml) !== '') {
                        try {
                            $epParser = new HtmlParser($epHtml, $this->baseUrl());
                        } catch (\Throwable $e) {
                            break;
                        }
                        foreach ($epParser->query('//a[contains(@class,"ep-row")]') as $row) {
                            if (!$row instanceof \DOMElement) {
                                continue;
                            }
                            $label = $epParser->firstTextWithin($row, [
                                './/div[contains(@class,"ep-info")]/strong',
                                './/strong',
                            ]);
                            $href = $row->getAttribute('href');
                            $episodes[] = [
                                'id' => $row->getAttribute('data-ep-id') !== '' ? (int) $row->getAttribute('data-ep-id') : null,
                                'label' => $label ?? '',
                                'url' => $href !== '' ? $epParser->normalizeUrl($href) : $epParser->firstUrlWithin($row, ['.//@href']),
                                'thumbnail' => $epParser->firstImageUrlWithin($row, [
                                    './/img/@data-src',
                                    './/img/@src',
                                ]),
                            ];
                        }
                    }
                    $totalEps = $resp['data']['total_eps'] ?? null;
                    if ($totalEps !== null && $totalEps !== '') {
                        $seasons[$i]['episode_count'] = ((string) (int) $totalEps) . '/' . ((string) ($s['total'] ?? $totalEps)) . ' Episodes';
                    }
                    if (empty($resp['data']['has_more'])) {
                        break;
                    }
                }
                if ($episodes !== []) {
                    $seasons[$i]['episodes'] = $episodes;
                }
            }

            return $seasons;
        } catch (\Throwable $e) {
            return $seasons;
        }
    }

    /**
     * Resolve a slug or full URL into a canonical page URL.
     */
    private function resolveContentUrl(string $value, string $kind): string
    {
        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $base = $this->baseUrl();

        switch ($kind) {
            case 'anime':
                return $base . '/' . ltrim($value, '/');
            case 'ep':
                return $base . '/ep/' . ltrim($value, '/');
            case 'movie':
                return $base . '/movie/' . ltrim($value, '/');
        }

        throw new NotFoundException("Unsupported content kind: {$kind}");
    }

    private function slugFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        return (string) end($parts);
    }

    private function hashFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        return (string) end($parts);
    }
}