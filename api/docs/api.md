# AnimeTadka API — Documentation

Zero-dependency PHP API that scrapes public pages of **animetadka.com** (WordPress 7.0.4, theme `anime-tadka`) and serves normalized JSON. No Composer, no PHPUnit, no external services.

## Running

```bash
php -S localhost:8000 -t public
```

Configuration via `.env` (see `.env.example`): request delay, retries, proxy, caching, logging, debug.

## Response envelope

Success:
```json
{
  "status": "success",
  "data": { ... },
  "meta": { "source": "animetadka.com" },
  "timestamp": "2026-08-19T04:14:11+00:00"
}
```

Error:
```json
{
  "status": "error",
  "error": { "code": "NOT_FOUND", "message": "..." },
  "timestamp": "..."
}
```

Error codes: `NOT_FOUND` (404), `INVALID_PARAMETER` (400), `MISSING_QUERY` (400), `QUERY_TOO_SHORT` (400), `METHOD_NOT_ALLOWED` (405), `RATE_LIMITED` (429), `FORBIDDEN` (403), `HTTP_ERROR`/`SOURCE_ERROR` (502), `PARSE_ERROR` (502), `SCRAPE_ERROR` (502), `INTERNAL_ERROR` (500). Stack traces are never exposed.

## Endpoints

### GET /api/health
```json
{ "status": "success", "data": { "status": "ok", "time": "..." } }
```

### GET /api/home
Spotlight carousel + latest grids + top-10 (day/week/month) + language nav.

```json
{
  "data": {
    "spotlight": [
      { "rank": "#1 Spotlight", "badge": "Multi Audio", "genres": "Action, Fantasy",
        "description": "...", "watch_url": ".../ep/{hash}/", "details_url": ".../{slug}/",
        "banner": "https://animetadka.com/wp-content/uploads/.../xxx.webp",
        "poster": "https://animetadka.com/wp-content/uploads/.../yyy.webp" }
    ],
    "latest": {
      "all":    [ { "title": "...", "url": ".../{slug}/", "poster": "...",
                    "episode_badge": "S2-E18", "languages_badge": "Hin-Jpn" } ],
      "series": [ ... ],
      "movies": [ ... ]
    },
    "top": {
      "day":   [ { "rank": "01", "title": "...", "url": ".../{slug}/",
                   "poster": "...", "languages": ["Hindi", "Japanese"] } ],
      "week":  [ ... ],
      "month": [ ... ]
    },
    "languages": [ { "name": "Hindi", "url": ".../lang/hindi/" } ]
  }
}
```

### GET /api/top?range=day|week|month
The "Top Animes" ranking (default `day`), loaded from the site's public `admin-ajax.php?action=get_top_animes` endpoint.

```json
{ "data": { "range": "day", "items": [ ...same top item shape as above... ] } }
```

### GET /api/extra
Site chrome scraped from the home page: navigation menu, header logo, site logo, favicon.

```json
{
  "data": {
    "menu": [
      { "label": "App", "url": "https://animetadka.com/app", "children": [] },
      { "label": "Series", "url": "https://animetadka.com/type/series/", "children": [] },
      { "label": "Languages", "url": null,
        "children": [ { "label": "Hindi", "url": "https://animetadka.com/lang/hindi/" } ] }
    ],
    "header_logo": "https://animetadka.com/wp-content/uploads/2026/06/6077-scaled-1.webp",
    "site_logo": "https://animetadka.com/wp-content/uploads/2026/04/9525.png",
    "favicon": "https://i0.wp.com/animetadka.com/wp-content/uploads/2026/04/9525.png",
    "icons": [ { "url": "...", "size": "32x32" } ]
  }
}
```

## Image URL normalization
All scraped image URLs (spotlight banner/poster, grid posters, search thumbnails, anime poster/banner, season posters, episode thumbnails, logos, favicons) are resolved to their **full-size original**:
- query strings stripped — `https://i0.wp.com/.../captain-tsubasa-6a4f4c484d551-1.webp?fit=214%2C300&ssl=1` → `https://i0.wp.com/.../captain-tsubasa-6a4f4c484d551-1.webp`
- WordPress resize suffixes stripped — `https://animetadka.com/wp-content/uploads/2026/03/mvYznVlIPU3cfcbG7dXh1fBONcc-150x150.jpg` → `https://animetadka.com/wp-content/uploads/2026/03/mvYznVlIPU3cfcbG7dXh1fBONcc.jpg`

Href URLs (watch/download links) are **not** normalized this way — they rely on query strings.

### GET /api/latest?type=all|series|movies&page=N
Paged latest items from the home grid (`type` must be `all`, `series` or `movies`; anything else → 404).

```json
{ "data": { "type": "all", "page": 1, "items": [ ...same card shape... ] } }
```

### GET /api/search?q={query}
Live search via the site's public `admin-ajax.php?action=anime_search` endpoint (allowed by robots.txt). `q` is required and ≥ 2 chars.

```json
{ "data": { "query": "naruto",
  "results": [ { "title": "Naruto Shippūden", "url": ".../{slug}/",
                 "poster": "...", "languages": "Hindi, Tamil, ...", "type": "Serie" } ] } }
```

### GET /api/anime/{slug}
Full anime detail page.

```json
{
  "data": {
    "slug": "naruto-shippuden-...", "url": "https://animetadka.com/{slug}/",
    "title": "Naruto Shippūden",
    "poster": "https://i0.wp.com/...", "banner": "https://i0.wp.com/...",
    "genres": [ { "name": "Action", "url": ".../genre/action/" } ],
    "meta": {
      "audio_languages": [ { "name": "Hindi", "url": ".../lang/hindi/" } ],
      "subtitles": [ ... ], "studio": [ ... ],
      "quality": "480p, 720p, 1080p", "encode": "BluRay"
    },
    "description": "...", "notes": "..." ,
    "latest_episode": { "label": "Watch Latest Episode: S17-E368", "url": ".../ep/{hash}/" },
    "external_ids": { "mal": "1735", "tmdb": "31910" },
    "seasons": [
      { "season": 1, "title": "S1: Kazekage Rescue", "episode_count": "32/32 Episodes",
        "poster": "https://image.tmdb.org/...",
        "episodes": [ { "id": 1319, "label": "S1-E1: Homecoming",
                        "url": ".../ep/{hash}/", "thumbnail": "https://image.tmdb.org/..." } ] }
    ]
  }
}
```

### GET /api/anime/{slug}/episodes
Episodes only: `{ title, url, seasons: [...] }`.

### GET /api/episode/{hash}
Episode watch page.

```json
{
  "data": {
    "url": ".../ep/{hash}/", "hash": "{hash}",
    "back_link": { "label": "Back to Anime Details", "url": ".../{slug}/" },
    "title": { "accent": "S1-E1:", "title": "Homecoming" },
    "rating": "8.3", "runtime": "23m", "description": "...",
    "player_url": ".../watch.php?toonx={token}",
    "player_source": "https://as-cdn21.top/video/{token}",
    "player_source_extracted": false,
    "servers": [
      { "label": "Multi Audio ~ VidStream", "url": ".../watch.php?toonx={token}",
        "source": "https://as-cdn21.top/video/{token}", "source_extracted": false }
    ],
    "next_episode_url": ".../ep/{hash}/",
    "download_links": [ { "label": "Multi (720p)", "url": ".../redirect/download/?dd={id}" } ],
    "release_date": "2026-08-19T04:00:00+00:00"
  }
}
```

Each `servers[].source` (and the top-level `player_source`) is the **resolved playable stream URL** — same resolution as `/api/proxy/watch`: the `<iframe id="main-video">` src is extracted from `watch.php` when available (`*_extracted: true`); when the page is Cloudflare-challenged it falls back to the known CDN player pattern (`*_extracted: false`). Sources are cached (default 1h); pass `refresh=1` via `/api/proxy/watch` for a fresh check.

### GET /api/movie/{hash}
Movie watch page — same shape as episode (including `player_source`, `player_source_extracted`, and per-server `source`/`source_extracted`).

### GET /api/category/{type}/{slug}?page=N
Listing pages. `type` ∈ `type` (series|movies), `lang`, `genre`, `studio`, `sub`.

```json
{ "data": { "type": "lang", "slug": "hindi", "page": 1,
            "title": "...", "items": [ ...card shape... ],
            "pagination": { "current": 1, "total": "19", "next": ".../page/2/", "prev": null } } }
```

### GET /api/languages | /api/genres | /api/studios
Sidebar navigation lists: `{ "languages": [ { "name", "url" } ] }` etc.

### GET /api/extra
Menu, header logo, site logo and favicon — see above.

### GET /api/proxy/watch?server=thugs|toonx|hyx&hash={hash}
Resolves a watch-page stream source without exposing the wrapper page. Also accepts a full URL: `?url=https://animetadka.com/watch.php?thugs=0ca2b` (host must be `animetadka.com`).

The proxy fetches `watch.php?{server}={hash}` and extracts the original `<iframe id="main-video">` src. When the watch page is behind a Cloudflare challenge (currently the case for all hashes), it falls back to the known CDN player pattern (`go.gadgetsmania.xyz/embed/`, `as-cdn21.top/video/`, `play.abyssplayer.com/`) — same URLs the site's own JS builds — and reports `"extracted": false`. The `source` URL is the directly-playable player page (verified 200). Never streams media bytes, never bypasses anti-bot.

```json
{
  "data": {
    "server": "thugs",
    "hash": "0ca2b",
    "source": "https://go.gadgetsmania.xyz/embed/0ca2b",
    "extracted": false,
    "watch_url": "https://animetadka.com/watch.php?thugs=0ca2b"
  }
}
```

### GET /api/proxy/download?url={/redirect/download/ url} or ?dd={id}
Resolves a download gateway URL server-side: follows the redirect chain (`/redirect/download/?dd=…` → `go.gadgetsmania.xyz/file/…` → final page, max 8 hops, http/https only) and returns the original filename plus mirror links from the final BoxDrive page. Also accepts `?dd=5933e` directly. Host must be `animetadka.com` — anything else → 400 `INVALID_URL` (SSRF guard).

If the final response is a direct file (non-HTML content-type) it returns `kind: "file"` with the resolved URL and filename; if it is a page (current live behavior) it returns `kind: "page"` with `title`, `filename` and `mirrors`. Media bytes are never streamed through this API (final hosts are Cloudflare-protected; no anti-bot bypass).

```json
{
  "data": {
    "url": "https://animetadka.com/redirect/download/?dd=5933e",
    "final_url": "https://go.gadgetsmania.xyz/file/{token}",
    "kind": "page",
    "title": "[AnimeTadka]-Jujutsu Kaisen S01E01 [BDRip 480p x264] [Multi Audio] ESub.mkv - BoxDrive",
    "filename": "[AnimeTadka]-Jujutsu Kaisen S01E01 [BDRip 480p x264] [Multi Audio] ESub.mkv",
    "mirrors": [
      { "name": "Voe DL", "url": "https://voe.sx/r8zr0spjhpq6" },
      { "name": "FileBee DL", "url": "https://fpgo.xyz/file/69c8f9d0426bf3dc7a642b1b" },
      { "name": "SendCM", "url": "https://send.now/qjbvxo6oh199" }
    ]
  }
}
```

## Data integrity rules
- **Only publicly available data** from the site's own pages/AJAX/REST — nothing that requires auth, CAPTCHA, or bypassing DRM.
- Player/CDN iframes are **never fetched** — the API exposes the public watch-page URLs only. The watch proxy resolves the `watch.php` wrapper's iframe src or the known CDN pattern; it does not touch the CDN player's internals.
- Media bytes are **never streamed or stored**; the download proxy only follows public redirects and returns the filename + mirror links.
- Download links point to the site's own `/redirect/download/` gateway (public).
- Proxy input URLs are restricted to `animetadka.com` only (SSRF guard), redirects are capped at 8 and schemes limited to http/https.
- Missing fields are returned as `null`/`[]`, never invented.
- `REQUEST_DELAY_MS` (default 1000ms) throttles requests; 429s back off and eventually surface as `RATE_LIMITED`.
- HTTP responses are cached (`CACHE_ENABLED`/`CACHE_TTL`, default 1h) in `storage/cache/`.

## Project layout

```
animetadka-api/
├── public/index.php          # front controller (CORS, routing, error safety net)
├── src/
│   ├── autoload.php          # PSR-4 style, no Composer
│   ├── Bootstrap.php         # DI wiring (config → logger → cache → http → scraper → router)
│   ├── Config.php, Logger.php
│   ├── Exceptions/           # ScraperException, HttpException, NotFoundException, ...
│   ├── Cache/FileCache.php
│   ├── Scraper/
│   │   ├── HttpClient.php    # curl, retries, rate limit, caching, snapshots, proxy
│   │   ├── HtmlParser.php    # DOM/XPath helpers, URL normalization
│   │   ├── Parsers/          # Home, Search, Anime, Episode, Category, Extra, Top
│   │   └── AnimeTadkaScraper.php   # facade: HTTP → Parser → data
│   └── Api/
│       ├── Response.php      # {status,data,meta,timestamp} / {status,error,timestamp}
│       ├── Router.php        # {param} regex routing
│       └── Controllers/      # Home, Search, Anime, Episode, Category, Meta, Proxy, base
├── bin/
│   ├── scrape.php            # CLI scraper
│   ├── inspect-page.php      # HTML structure inspector
│   └── test-endpoints.php    # live endpoint tester
├── tests/
│   ├── run.php               # zero-dependency runner
│   ├── TestCase.php, FixtureHttpClient.php, stub-server.php
│   ├── fixtures/             # saved HTML from the live site
│   └── Unit/, Integration/
├── storage/{cache,logs,html}
└── docs/ (site-structure.md, api.md, api-test-report.md)
```

## CLI tools

```bash
php bin/scrape.php home
php bin/scrape.php latest movies 2
php bin/scrape.php search "naruto"
php bin/scrape.php anime "naruto-shippuden-..."
php bin/scrape.php episode "35278977b149"
php bin/scrape.php category lang hindi
php bin/scrape.php languages

php bin/inspect-page.php "https://animetadka.com/type/movies/"
```
