# ToonStream API

PHP scraping REST API for [toon-stream.site](https://toon-stream.site/) — a port of the original Node.js/Hono spec to PHP with no dependencies beyond the standard library (`curl`, `dom`, `mbstring`).

All responses share one envelope:

```json
{ "success": true, "provider": "toonstream", "stats": { }, "results": { } }
```

Errors:

```json
{ "success": false, "provider": "toonstream", "message": "...", "statusCode": 500 }
```

CORS headers are set on every response.

## Endpoints

| Route | Description |
| --- | --- |
| `GET /` | API info + endpoint list |
| `GET /health` | Liveness check |
| `GET /index` | Site index page — the static landing/FAQ article, returned as structured sections |
| `GET /home` | Homepage sections (latest episodes/series, movies, featured, languages) |
| `GET /home/search/:query` | Homepage search alias of `/search/:query` |
| `GET /home/cast_tv/:name` | Cast listing alias of `/cast/:name` |
| `GET /home/letter/:letter` | Letter listing alias of `/letter/:letter` |
| `GET /series/:slug` | Series detail (default: inline season) |
| `GET /episode/:slug` | Episode detail: player servers + prev/next navigation |
| `GET /movies` | Movies listing (maps to the site's `/category/movies/`) |
| `GET /movies/page/:page` | Paginated movies listing |
| `GET /movies/:slug` | Movie detail (single playable unit with the page's own servers) |
| `GET /search/:query?page=N` | Search results |
| `GET /category/:path?page=N` | Category listing, nested paths allowed (e.g. `language/hindi-language`) |
| `GET /cast/:name?page=N` | Actor/cast listing |
| `GET /letter/:letter?page=N` | Alphabet listing |

Query-string aliases mirror the original spec: `/series?slug=`, `/episode?slug=`, `/search?q=` (or `?s=`), `/home?s=`.

### Query parameters

| Param | Applies to | Values |
| --- | --- | --- |
| `seasons` | `/series/:slug` | `all`, `1,2,3`, `2-4` (clamped to available) |
| `src` | `/series/:slug` | `true` — fetch every requested episode page in parallel and attach `servers` arrays |
| `server` | `/series/:slug`, `/episode/:slug` | `all`, `0,1,2`, `0-4`, or comma-separated names (`ruby,cloudy`) — case-insensitive |
| `page` | listings | page number, falls back to 1 |

### Response shapes

- **Cards** (home, search, category, cast, letter, movies): `{ id, title, image, rating, contentType (series|movies|episode), url, slug, categories: [], tags: [] }` — `id` is a stable `post-<slug>` identifier (the source site exposes no numeric post IDs on cards).
- **Series detail**: `{ slug, title, image, rating, year, duration, description, totalSeasons, totalEpisodes, categories, cast, tags, availableSeasons, seasons: [{ seasonNumber, name, episodes }] }` — `totalEpisodes` is always present (counted across all seasons, matching the spec's response).
*55|- **Episode detail**: `{ slug, title, image, rating, year, duration, description, categories, tags, servers: [{ serverNumber, displayNumber, name, language, src, originalSrc, isActive }], navigation: { previousEpisode, nextEpisode, seriesPage } }` — `previousEpisode`/`nextEpisode` are `null` at the ends of a season. `src` is the site's player iframe (`https://toon-stream.site/embed/…`); the scraper goes one step deeper into that embed page and exposes the real player URL as `originalSrc` (e.g. `https://rubystm.com/e/…`, `https://gdmirrorbot.nl/embed/…`) — `null` only when the wrapper can't be resolved, and equal to `src` for servers that already point at an external player.
- **Movie detail**: like series detail with one `seasons[0].episodes[0]` entry (`episodeNumber: "Movie"`) whose `servers` come from the movie page's own player.
- **Index page**: `{ title, url, intro: [...], sections: [{ heading, type: "section"|"subsection", paragraphs: [...] }] }` — the site root is a static landing/FAQ article with no content cards.

## Folder structure

```
toonstream/
├── app/
│   ├── autoload.php              PSR-4 autoloader (ToonStreamApi\ → app/)
│   ├── Core/                     App kernel, router, response envelope, config, exceptions
│   ├── Providers/                ToonStream site scraper (single provider)
│   └── Utils/                    Shared helpers: Arr, Cache, Dom, HttpClient, Url
├── public/                       Document root (front controller + .htaccess)
├── scratch/                      Local debug/test artifacts (not deployed)
└── storage/cache/                Runtime file cache (git-ignored)
```

`app/Utils` are the shared utilities: `HttpClient` (curl, retries, browser UA, parallel fetch — stateless, never persists HTML), `Cache` (TTL JSON cache: finished API payloads are stored as `*.json` files via atomic temp-file writes — compatible with FUSE filesystems where `flock` is unavailable), `Dom` (XPath helpers with PHP 8.4+ DOM-wrapper-safe node checks), `Url` (absolute-URL resolution), `Arr` (pagination math).

## Run

PHP 8.0+ with `curl`, `dom`, `mbstring`. Built-in server (document root is `public/`):

```sh
php -S 127.0.0.1:8787 -t public public/index.php
```

Apache: point the vhost `DocumentRoot` at `public/` (the shipped `.htaccess` rewrites everything to `index.php`). For shared hosting (e.g. InfinityFree), upload the project root — `index.php` + `.htaccess` at the htdocs root, with `app/`, `storage/`, `config.json` beside them; the front controller auto-detects both layouts. The app also runs under a sub-folder: drop the whole project into e.g. `htdocs/toonstream/` and every route is served below `/toonstream/` (`/toonstream/home`, `/toonstream/episode/…`) — the router strips the front controller's directory automatically.

> Note for InfinityFree: the free host serves a JavaScript anti-bot challenge (`aes.js` + `__test` cookie) before proxying to your app. Browsers pass it automatically; scripted clients (curl, apps) must solve it once per ~6h and send the `__test` cookie. The live deployment responds with PHP 8.3. InfinityFree's PHP also disables `curl_multi_exec`, so the parallel HTTP fetcher degrades to sequential fetches there automatically (`/series?src=true` and the embed resolution simply take longer).

## Configuration

`config.json` (project root) controls the response cache:

```json
{
    "cache_enabled": true,
    "cache_ttl": { "home": 300, "search": 300, "series": 1800, "episode": 3600, "category": 600, "cast": 600, "letter": 600 }
}
```

| Key | Default | Purpose |
| --- | --- | --- |
| `cache_enabled` | `true` | Master switch — `false` bypasses the cache entirely (every request scrapes live) |
| `cache_ttl` | per-type seconds | Per-endpoint TTL overrides (any key may be omitted) |

Environment variables:

| Var | Default | Purpose |
| --- | --- | --- |
| `TOONSTREAM_BASE_URL` | `https://toon-stream.site` | Target site (mirrors supported) |

Cache TTLs (seconds), per the original spec: home/search `300`, series `1800`, episode `3600`, category/cast/letter `600`. Cached payloads live in `storage/cache/*.json`; clear with `rm storage/cache/*.json`.

## Notes

- The site's `/movies` route returns HTTP 500, so the movies listing is served from `/category/movies/`.
- Unknown series slugs surface the site's upstream error (`HTTP 500` → API `500`).
- Season navigation: the site emits `<!-- PREVIOUS -->` / `<!-- NEXT -->` markers with anchors; when an episode has no predecessor, both markers render back-to-back — the parser stops at the next marker comment.
- Field parity with the original spec: the source site does not publish episode dates (spec's `time` on episode rows) or numeric WordPress post IDs on cards — `id` is therefore a stable slug-derived `post-<slug>` value, and `time` is omitted. Episode rows carry `{ title, episodeNumber, image, url, slug }`.
