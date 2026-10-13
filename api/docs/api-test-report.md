# API Test Report

**Date:** 2026-08-19
**Environment:** PHP 8.5.1 (curl, dom, mbstring, json), Android/Termux, no Composer, no PHPUnit.

## Test layers

| Layer | Location | Method | Count | Result |
|---|---|---|---|---|
| Parser unit tests | `tests/Unit/ParserTest.php` | Fixture HTML (`tests/fixtures/`) | 35 | **35/35 pass** |
| HTTP client tests | `tests/Unit/HttpClientTest.php` | Local stub server (`tests/stub-server.php`) | 8 | **8/8 pass** |
| API integration tests | `tests/Integration/ApiTest.php` | In-process Router + `FixtureHttpClient` | 32 | **32/32 pass** |
| **Offline suite** | `php tests/run.php` | — | **75** | **75/75 pass** |
| **Live endpoint tests** | `bin/test-endpoints.php` | Running API at `localhost:8000` | **30** | **30/30 pass** |

Run everything with:

```bash
php tests/run.php                          # offline suite (75 tests)
php -S localhost:8000 -t public &          # start API
php bin/test-endpoints.php localhost:8000  # live endpoint checks (30)
```

## Offline suite (`php tests/run.php`)

```
Tests: 75  Passed: 75  Failed: 0
```

### Parser tests (`ParserTest`, fixtures only — no network)
- Home: spotlight items (rank/badge/genres/description/watch+details URLs, **banner + poster images**), latest grids (all/series/movies: title/url/poster/episode/languages badges), language nav (13 languages incl. Hindi, Tamil).
- **Top-10 (day/week/month)**: `TopParser` against `top-day/week/month.html` fixtures — 10 ranked items each, sequential ranks 01–10, title/url/poster/languages, posters normalized to full size.
- **Image normalization**: spotlight/grid/search/anime posters have no query strings (`?fit=…&ssl=1`) and no `-{w}x{h}` resize suffixes; `normalizeImageUrl()` unit-tested against both real URL shapes.
- **Extra**: sidebar menu (App/Series/Movies single links + Languages/Genres/Studios/Subtitles accordions with sub-links), header logo, site logo (JSON-LD 9525.png), favicon + icons.
- Search: results with title/url/poster/languages/type; multiple Naruto hits.
- Anime detail: title, poster/banner (data-src + hero-bg style), genres, meta rows (AUDIO→languages, SUBTITLE, STUDIO, QUALITY, ENCODE), latest episode, MAL=1735 / TMDb=31910 external IDs, 20 seasons with per-episode id/label/url/thumbnail.
- Episode: title accent/main, rating 8.3, runtime 23m, description, player URL, back link, ≥3 watch.php servers, next episode, 6 download links.
- Movie: Demon Slayer title, rating/runtime, servers, back link.
- Category: series listing + pagination (current/total/next), hindi listing.
- URL normalization, malformed HTML resilience, missing-selector nulls, empty-HTML rejection.
- **Proxy**: `ProxyParser::extractIframeSource()` extracts `id="main-video"` src (watch fixture → `as-cdn21.top`); Cloudflare-troll fixture → `null`; `isCloudflareChallenge()` true on troll page, false on legitimate watch page; `parseDownloadPage()` extracts BoxDrive `<title>` → filename (trailing ` - BoxDrive` stripped) + 3 mirror links (Voe DL / FileBee DL / SendCM) with names + URLs.

### HTTP client tests (`HttpClientTest`, stub server `tests/stub-server.php`)
- 200 success, 404 → `HttpException` (status 404), 500 → retry then `HttpException` (HTTP_MAX_RETRIES=1), 429 → `RateLimitedException`, timeout → `ScraperException`, non-HTML content-type → `ScraperException`, invalid URL → `ScraperException`, cache hit (2nd request served from cache, server counter stays 1).

### API integration tests (`ApiTest`, real Router + FixtureHttpClient)
- Envelope shape: success `{status,data,meta,timestamp}`, error `{status,error:{code,message},timestamp}`.
- Validation: missing `q` → 400 MISSING_QUERY, short `q` → 400 QUERY_TOO_SHORT, bad latest type → 404, unknown category type → 404, unknown route → 404.
- Happy paths for all 14 public endpoints (health/home/latest×2/search/anime/episodes/episode/movie/category×3/languages/genres/studios).
- **Proxy endpoints**: `/api/proxy/watch?server=toonx&hash=…` → 200 with extracted `source` = fixture iframe src + `watch_url`; bogus server → 400; non-animetadka URL → 400 `INVALID_URL`; `/api/proxy/download?url=…` → 200 `kind=page` with filename containing "Jujutsu Kaisen" + ≥3 mirrors (first = Voe DL); non-animetadka download URL → 400 `INVALID_URL`.
- **Episode/movie source enrichment**: `testEpisode`/`testMovie` assert `player_source` + per-server `source` (fixture `watch.html` → `https://as-cdn21.top/video/49182f…`, `source_extracted: true`), proving the episode page embeds the same resolved source as the watch proxy.

## Live endpoint tests (`bin/test-endpoints.php`)

```
TOTAL: 30  PASS: 30  FAIL: 0
```

| Endpoint | HTTP | JSON | Data |
|---|---|---|---|
| GET /api/health | 200 | ✓ | ✓ |
| GET /api/home | 200 | ✓ | ✓ |
| GET /api/latest?type=all | 200 | ✓ | ✓ |
| GET /api/latest?type=series | 200 | ✓ | ✓ |
| GET /api/latest?type=movies | 200 | ✓ | ✓ |
| GET /api/latest?type=bogus | 404 | ✓ | N/A |
| GET /api/top?range=day | 200 | ✓ | ✓ |
| GET /api/top?range=week | 200 | ✓ | ✓ |
| GET /api/top?range=month | 200 | ✓ | ✓ |
| GET /api/top?range=year | 404 | ✓ | N/A |
| GET /api/search?q=naruto | 200 | ✓ | ✓ |
| GET /api/search?q= | 400 | ✓ | N/A |
| GET /api/search?q=n | 400 | ✓ | N/A |
| GET /api/anime/{slug} | 200 | ✓ | ✓ |
| GET /api/anime/{slug}/episodes | 200 | ✓ | ✓ |
| GET /api/anime/nonexistent-slug | 404 | ✓ | N/A |
| GET /api/episode/{hash} | 200 | ✓ | ✓ |
| GET /api/movie/{hash} | 200 | ✓ | ✓ |
| GET /api/category/type/series | 200 | ✓ | ✓ |
| GET /api/category/lang/hindi | 200 | ✓ | ✓ |
| GET /api/category/genre/action | 200 | ✓ | ✓ |
| GET /api/category/bogus/whatever | 404 | ✓ | N/A |
| GET /api/languages | 200 | ✓ | ✓ |
| GET /api/genres | 200 | ✓ | ✓ |
| GET /api/studios | 200 | ✓ | ✓ |
| GET /api/extra | 200 | ✓ | ✓ |
| GET /api/proxy/watch?server=thugs&hash=0ca2b | 200 | ✓ | ✓ |
| GET /api/proxy/watch?server=bogus&hash=abc | 400 | ✓ | N/A |
| GET /api/proxy/download?url=…redirect%2Fdownload%2F%3Fdd%3D5933e | 200 | ✓ | ✓ |
| GET /api/does-not-exist | 404 | ✓ | N/A |

## Sample live data spot-checks

**`/api/anime/naruto-shippuden-...`**
- title: `Naruto Shippūden`; genres: Action, Adventure, Sci-Fi
- audio languages: Bengali, English, Hindi, Japanese, Malayalam, Tamil, Telugu
- subtitles: English; studio: Sony Yay; quality: 480p/720p/1080p; encode: BluRay
- latest: `Watch Latest Episode: S17-E368`; 20 seasons; S1-E1: Homecoming

**`/api/episode/35278977b149`**
- title: `S1-E1: Homecoming`; rating `8.3`; runtime `23m`
- 3 servers → `watch.php?toonx=…`, `watch.php?thugs=…`, `watch.php?hyx=…`
- 6 download links → `/redirect/download/?…`; next episode URL present

**`/api/proxy/watch?server=thugs&hash=0ca2b`**
- `source` → `https://go.gadgetsmania.xyz/embed/0ca2b` (200, playable), `extracted: false` because live `watch.php` is Cloudflare-challenged → known-CDN-pattern fallback works.

**`/api/proxy/download?dd=5933e`**
- Resolves full chain to BoxDrive page: filename `[AnimeTadka]-Jujutsu Kaisen S01E01 [BDRip 480p x264] [Multi Audio] ESub.mkv`, 3 mirrors (voe.sx, fpgo.xyz, send.now).

## Notable environment-specific fixes
- `LOCK_EX` (`flock`) silently fails on this Android FUSE filesystem → removed from `FileCache` and `Logger` (0-byte files otherwise).
- `/tmp` is read-only on this device → fixtures live in `tests/fixtures/`, snapshots in `storage/html/`, cache in `storage/cache/` (all project-local).
- `curl_close()` deprecated since PHP 8.0 → removed.
- `HtmlParser::firstAttr()` must handle both `DOMElement` and `DOMAttr` nodes (XPath `@href`/`@data-src` return attributes).
- Per-row scoping bug in `AnimeParser::parseEpisodes()` fixed (was returning first episode for every row).
- `HttpClient::fetch()` refactored to return `[status, headers, body]` (with `getWithMeta()` for redirect-aware requests) so proxy endpoints can follow chains and inspect content-types; existing `get()`/`post()`/`getHtml()` behavior unchanged (75/75 regression pass).
