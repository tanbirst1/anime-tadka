# AnimeTadka API

A production-quality, zero-dependency PHP scraper/API for **animetadka.com** — reconstructs the site's public data (anime catalog, episodes, movies, search, categories) into clean JSON. No Composer, no PHPUnit, no external services.

## Quick start

```bash
# 1. Configure (optional — sensible defaults are baked in)
cp .env.example .env

# 2. Run the API
php -S localhost:8000 -t public

# 3. Use it
curl http://localhost:8000/api/health
curl "http://localhost:8000/api/search?q=naruto"
curl http://localhost:8000/api/anime/naruto-shippuden-hindi-dubbed-multi-audio-bluray-episodes-watch-online-download
curl http://localhost:8000/api/episode/35278977b149
curl "http://localhost:8000/api/category/lang/hindi"
curl "http://localhost:8000/api/proxy/watch?server=thugs&hash=0ca2b"      # playable stream source
curl "http://localhost:8000/api/proxy/download?dd=5933e"                  # original filename + mirrors
```

## Requirements
- PHP ≥ 8.1 (developed/tested on 8.5.1) with `curl`, `dom`, `mbstring`, `json`, `libxml`, `simplexml`
- No Composer required — autoloading is hand-rolled (`src/autoload.php`)
- Network access to `animetadka.com` for live scraping

## Tests

```bash
php tests/run.php                          # 75 offline tests (fixtures + stub server) — no network
php bin/test-endpoints.php localhost:8000  # 30 live endpoint checks (start the API first)
```

**Status:** 75/75 offline, 30/30 live — see `docs/api-test-report.md`.

## Documentation
- `docs/api.md` — endpoint reference, response envelopes, examples, project layout
- `docs/site-structure.md` — source-site reconnaissance (routes, selectors, AJAX/REST, data spec)
- `docs/api-test-report.md` — test results and environment fixes

## Endpoints
| Method | Path | Description |
|---|---|---|
| GET | `/api/health` | liveness |
| GET | `/api/home` | spotlight + latest grids + top-10 (day/week/month) + languages |
| GET | `/api/latest?type=all\|series\|movies&page=N` | paged latest |
| GET | `/api/top?range=day\|week\|month` | top-10 ranking |
| GET | `/api/search?q={query}` | live search |
| GET | `/api/anime/{slug}` | anime detail + seasons/episodes |
| GET | `/api/anime/{slug}/episodes` | episodes only |
| GET | `/api/episode/{hash}` | episode watch page (incl. resolved player/source) |
| GET | `/api/movie/{hash}` | movie watch page (incl. resolved player/source) |
| GET | `/api/category/{type}/{slug}?page=N` | type/lang/genre/studio/sub listings |
| GET | `/api/languages` `/api/genres` `/api/studios` | sidebar nav lists |
| GET | `/api/extra` | menu, header logo, site logo, favicon |
| GET | `/api/proxy/watch?server=thugs\|toonx\|hyx&hash={hash}` (or `?url=`) | resolve playable stream source from `watch.php` |
| GET | `/api/proxy/download?url={...}` (or `?dd={id}`) | resolve download redirect chain → filename + mirrors |

## Integrity & ethics
- Serves **only publicly available data** from the site's own pages and its public AJAX/REST endpoints (robots.txt allows `admin-ajax.php`).
- Never bypasses auth, CAPTCHA, DRM or anti-bot measures; player/CDN iframes are never fetched — only public watch-page URLs are exposed. The proxy endpoints only resolve public watch-page iframe srcs / CDN patterns and public download redirects; media bytes are never streamed or stored.
- Proxy input URLs are restricted to `animetadka.com` only (SSRF guard); redirects capped at 8 hops, schemes http/https.
- Missing data is `null`/`[]` — never invented.
- Politeness: 1s default request delay, retries with backoff on 429/5xx, 1h response cache, optional proxy via `.env`.
