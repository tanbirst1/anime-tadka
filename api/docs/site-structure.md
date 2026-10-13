# AnimeTadka — Site Structure Map

> Reconnaissance performed on 2026-08-19 against `https://animetadka.com/`.
> All evidence below was extracted from raw HTML saved under the recon directory
> (see `tests/fixtures/` for the copies used by the test suite).

## 1. Platform

- **CMS:** WordPress 7.0.4 (theme `anime-tadka`, LiteSpeed cache, Rank Math SEO, Site Kit)
- **robots.txt:** `Disallow: /wp-admin/`, **`Allow: /wp-admin/admin-ajax.php`**, sitemap at `/sitemap_index.xml`
- **Public REST API:** `/wp-json/` is fully public (638 routes). Custom post types `post` (anime/series), `ep` (episodes), `movie` (movies) are exposed. Custom taxonomies (`lang`, `genre`, `studio`, `type`, `sub`) are **not** registered in REST — they are only reachable via HTML pages.
- **Anti-bot:** Cloudflare passive `jsd/main.js` script is injected but pages are fully served to plain curl. No CAPTCHA/JS challenge observed on any fetched page.

## 2. Site Map

```
animetadka.com
├── /                          Landing page (no content; links to /home, /lang/*, /app)
├── /home                      Main listing: Spotlight carousel + Latest Animes (All/Series/Movies tabs)
│   └── /page/{n}/             Pagination (rel=next, /page/2/)
├── /app                       App download page (APK)
├── /type/series               Series listing
│   └── /type/series/page/{n}/
├── /type/movies               Movies listing
│   └── /type/movies/page/{n}/
├── /lang/{lang}               Language listing (hindi, tamil, telugu, bengali, malayalam, english,
│   │                          chinese, french, japanese, kanada, kannada, korean, marathi)
│   └── /lang/{lang}/page/{n}/
├── /genre/{genre}             Genre listing (action, adventure, animation, award-winning, comedy, crime,
│   │                          drama, ecchi, family, fantasy, girls-love, gourmet, hentai, horror, kids,
│   │                          mystery, politics, romance, sci-fi, science-fiction, slice-of-life, sports,
│   │                          supernatural, suspense, thriller, war)
│   └── /genre/{genre}/page/{n}/
├── /studio/{studio}           Studio listing (crunchyroll, netflix, disney, sony-yay, muse-india, ...)
├── /sub/{lang}                Subtitle listing (english, hindi)
├── /{slug}/                   Anime / series detail page (post type "post")
├── /ep/{hash}/                Episode watch page (post type "ep")
├── /movie/{hash}/             Movie watch page (post type "movie")
├── /watch.php?{server}={hash} Player wrapper ("Stream Engine") → iframe to 3rd-party CDN
│   │                          (toonx → as-cdn21.top, thugs → go.gadgetsmania.xyz, hyx → play.abyssplayer.com)
├── /redirect/download/?fx={hash}  Download redirect
├── /history, /skip-ads, /fix-buffer  Utility pages (not content)
├── /wp-admin/admin-ajax.php   Public AJAX: action=anime_search&term=..., action=record_search_click
├── /wp-json/wp/v2/posts       Public REST: anime/series posts
├── /wp-json/wp/v2/ep          Public REST: episodes
├── /wp-json/wp/v2/movie       Public REST: movies
└── /sitemap_index.xml         post-sitemap1/2, category, type, studio, subtitles sitemaps
```

## 3. URL Patterns

| Page type | Pattern | Example |
|---|---|---|
| Landing | `/` | `https://animetadka.com/` |
| Home listing | `/home` | `https://animetadka.com/home/` |
| Home pagination | `/page/{n}/` | `https://animetadka.com/page/2/` |
| Series listing | `/type/series/` | `https://animetadka.com/type/series/` |
| Movies listing | `/type/movies/` | `https://animetadka.com/type/movies/` |
| Language | `/lang/{lang}/` | `https://animetadka.com/lang/hindi/` |
| Genre | `/genre/{genre}/` | `https://animetadka.com/genre/action/` |
| Studio | `/studio/{studio}/` | `https://animetadka.com/studio/crunchyroll/` |
| Subtitle | `/sub/{lang}/` | `https://animetadka.com/sub/english/` |
| Anime detail | `/{slug}/` | `https://animetadka.com/naruto-shippuden-hindi-dubbed-multi-audio-bluray-episodes-watch-online-download/` |
| Episode watch | `/ep/{hash}/` | `https://animetadka.com/ep/35278977b149/` |
| Movie watch | `/movie/{hash}/` | `https://animetadka.com/movie/34d9ed3a8bd4/` |
| Player wrapper | `/watch.php?{server}={hash}` | `https://animetadka.com/watch.php?toonx=49182f81e6a13cf5eaa496d51fea6406` |
| Download redirect | `/redirect/download/?fx={hash}` | `https://animetadka.com/redirect/download/?fx=VZZqgP` |
| Search (AJAX) | `POST /wp-admin/admin-ajax.php` `action=anime_search&term={q}` | — |
| REST posts | `/wp-json/wp/v2/posts?search={q}&per_page={n}` | — |
| REST episodes | `/wp-json/wp/v2/ep?search={q}&per_page={n}` | — |

## 4. Pagination Behavior

- Home: `<link rel="next" href="https://animetadka.com/page/2/">` (WordPress default pagination).
- Category/listing pages: `.at-pagination ul.page-numbers` with numbered links, `…` dots, and a `.next` link. Pattern: `{base}/page/{n}/`.
- Search AJAX: no pagination (single result list).

## 5. Search Behavior

- **Primary (used by the site UI):** `POST /wp-admin/admin-ajax.php` with `action=anime_search` and `term={query}` (found in `fpage.js` and `theme-main.js`). Returns HTML fragment. Explicitly allowed by robots.txt.
- **Fallback (WordPress native):** `GET /?s={query}` (Rank Math SearchAction JSON-LD: `https://animetadka.com/?s={search_term_string}`).
- **REST fallback:** `GET /wp-json/wp/v2/posts?search={query}`.

## 6. Key CSS Selectors / HTML Attributes

### Home (`/home`)
| Data | Selector |
|---|---|
| Spotlight slides | `.sa-spotlight-swiper .swiper-slide` |
| Spotlight rank | `.sa-rank-tag` |
| Spotlight badge (audio) | `.sa-badge` |
| Spotlight genres | `.sa-genres` |
| Spotlight description | `.sa-description` |
| Spotlight watch link | `.sa-btn-main[href]` (→ `/ep/{hash}/` or `/movie/{hash}/`) |
| Spotlight details link | `.sa-btn-alt[href]` (→ `/{slug}/`) |
| Latest cards | `.home-grid .anime-card` (grids `#lat-grid-all`, `#lat-grid-series`, `#lat-grid-movies`) |
| Card link | `.anime-card > a[href]` |
| Episode badge | `.badge.se-badge` (e.g. `S2-E18`, or `Movie` for movies) |
| Language badge | `.badge.meta-badge` (e.g. `Hin-Jpn`, `Multi Audio`) |
| Poster | `.poster-box img[data-src]` (LiteSpeed lazy-load; `src` is a 1px GIF) |
| Title | `.card-title` |

### Anime detail (`/{slug}/`)
| Data | Selector |
|---|---|
| Title | `h1.entry-title-pro` |
| Poster | `.main-poster[data-src]` |
| Banner | `.hero-bg` / hero `style="background-image:url(...)"` |
| Genres | `.genre-pills a[href*="/genre/"]` |
| Audio languages | `.meta-row-pro` where `.label-pro` = `AUDIO:` → `.value-pro a[href*="/lang/"]` |
| Subtitles | `.meta-row-pro` where `.label-pro` = `SUBTITLE:` → `.value-pro a[href*="/sub/"]` |
| Studio | `.meta-row-pro` where `.label-pro` = `STUDIO:` → `.value-pro a[href*="/studio/"]` |
| Quality | `.meta-row-pro` where `.label-pro` = `QUALITY:` → `.value-pro` text |
| Encode | `.meta-row-pro` where `.label-pro` = `ENCODE:` → `.value-pro` text |
| Description | `.synopsis-pro p` |
| Notes | `.at-age-shield .shield-text` |
| Latest episode | `.at-latest-ep-btn[href]` + text `Watch Latest Episode: S17-E368` |
| External IDs | `.sa-ext-btn.mal[href]` (MyAnimeList), `.sa-ext-btn.ext-movie-db[href]` (TMDb) |
| Season blocks | `.season-block[id^="season-target-"]` |
| Season title | `.season-header .s-info-text h3` (e.g. `S1: Kazekage Rescue`) |
| Season episode count | `.season-header .s-info-text p` (e.g. `32/32 Episodes`) |
| Season poster | `.season-header .s-poster-mini img[data-src]` (TMDb) |
| Episode rows | `.ep-row[href*="/ep/"]` with `data-ep-id` |
| Episode label | `.ep-row .ep-info strong` (e.g. `S1-E1: Homecoming`) |
| Episode thumbnail | `.ep-row img[data-src]` (TMDb) |

### Episode / Movie watch (`/ep/{hash}/`, `/movie/{hash}/`)
| Data | Selector |
|---|---|
| Back link (anime) | `.movie-nav-top .back-info-btn[href]` |
| Player iframe | `#at-player-v2[src]` (→ `/watch.php?...`) |
| Server tabs | `.server-tabs .srv-btn[data-url]` + button text (server label) |
| Next episode | `.ep-nav-flex #next-ep-btn[href]` |
| Title accent | `.ep-main-title .title-accent` (e.g. `S1-E1:`) |
| Title main | `.ep-main-title .title-main` (e.g. `Homecoming`) |
| Rating | `.ep-meta-row-animated .meta-item-box` containing `Rating:` → `strong` |
| Runtime | `.ep-meta-row-animated .meta-item-box` containing `Runtime:` → `strong` |
| Description | `.ep-content-text` |
| Download links | `.download-section .dl-btn-premium[href*="/redirect/download/"]` + label text |

### Search AJAX result
| Data | Selector |
|---|---|
| Result item | `.search-res-item[href]` |
| Poster | `.search-res-img img[src]` |
| Title | `.res-title` |
| Languages | `.sub-item span` (first sub-item) |
| Type | `.sub-item.res-type-highlight .type-text` (`Serie` / `Movie`) |

### Category / listing pages
| Data | Selector |
|---|---|
| Section label | `.pro-section-header-v2 .dynamic-pro-label` |
| Section title | `.pro-section-header-v2 .main-pro-title-v2` |
| Cards | `.home-grid .anime-card` (same structure as home) |
| Pagination | `.at-pagination .page-numbers` |

## 7. Discovered API / AJAX Endpoints

| Endpoint | Method | Purpose | Public? |
|---|---|---|---|
| `/wp-admin/admin-ajax.php` (`action=anime_search`) | POST | Live search | Yes (robots.txt allows) |
| `/wp-admin/admin-ajax.php` (`action=record_search_click`) | POST | Analytics | Yes |
| `/wp-json/wp/v2/posts` | GET | Anime/series posts | Yes |
| `/wp-json/wp/v2/ep` | GET | Episode posts | Yes |
| `/wp-json/wp/v2/movie` | GET | Movie posts | Yes |
| `/wp-json/wp/v2/categories` | GET | Categories | Yes |
| `/watch.php?{server}={hash}` | GET | Player wrapper (iframe → 3rd-party CDN) | Yes |
| `/redirect/download/?fx={hash}` | GET | Download redirect | Yes |

## 8. Data-Field Specification

Legend: **available** = reliably present in source HTML · **derived** = computed from other fields · **not_available** = not present on the source page.

### Anime / series detail (`/{slug}/`)
| Field | Status | Source |
|---|---|---|
| id | derived | `postid-{n}` body class / REST id |
| slug | available | URL path |
| title | available | `h1.entry-title-pro` |
| poster | available | `.main-poster[data-src]` |
| banner | available | hero background-image |
| genres | available | `.genre-pills a` |
| audio_languages | available | AUDIO meta-row |
| subtitles | available | SUBTITLE meta-row |
| studio | available | STUDIO meta-row |
| quality | available | QUALITY meta-row |
| encode | available | ENCODE meta-row |
| description | available | `.synopsis-pro p` |
| notes | available | `.at-age-shield .shield-text` |
| latest_episode | available | `.at-latest-ep-btn` (label + URL) |
| external_ids (mal, tmdb) | available | `.sa-ext-btn` links |
| seasons | available | `.season-block` (title, episode count, poster) |
| episodes | available | `.ep-row` (label, URL, thumbnail, data-ep-id) |
| rating | not_available | only on episode page |
| release_year | not_available | not present |
| status (airing/completed) | not_available | not present |
| type | derived | URL/context (series vs movie) |

### Episode (`/ep/{hash}/`)
| Field | Status | Source |
|---|---|---|
| id | derived | `data-ep-id` on anime page / REST id |
| episode_label | available | `.title-accent` (e.g. `S1-E1:`) |
| episode_title | available | `.title-main` |
| anime_title | derived | back-link page title |
| anime_url | available | `.back-info-btn[href]` |
| rating | available | meta-item-box `Rating:` |
| runtime | available | meta-item-box `Runtime:` |
| description | available | `.ep-content-text` |
| servers | available | `.srv-btn[data-url]` + label |
| player_url | available | `#at-player-v2[src]` |
| next_episode_url | available | `#next-ep-btn[href]` |
| download_links | available | `.dl-btn-premium[href]` + label |
| thumbnail | not_available | only on anime page episode rows |
| release_date | derived | `og:updated_time` meta |

### Movie (`/movie/{hash}/`)
Same as episode; `anime_url` is the movie detail page (`Back to Movie Details`).

### Search result
| Field | Status | Source |
|---|---|---|
| title | available | `.res-title` |
| url | available | `.search-res-item[href]` |
| poster | available | `.search-res-img img[src]` |
| languages | available | `.sub-item span` |
| type | available | `.res-type-highlight .type-text` |

### Listing card (home / category / language / genre / studio)
| Field | Status | Source |
|---|---|---|
| title | available | `.card-title` |
| url | available | `.anime-card > a[href]` |
| poster | available | `.poster-box img[data-src]` |
| latest_episode_badge | available | `.badge.se-badge` |
| languages_badge | available | `.badge.meta-badge` |
| type | derived | `Movie` badge vs `S{n}-E{m}` badge |

## 9. Notes About Dynamic Content

- **Lazy-loaded images:** LiteSpeed lazy-load replaces `src` with a 1px GIF and puts the real URL in `data-src` (and `data-srcset`). Parsers must read `data-src`.
- **Home "Latest" tabs:** All three grids (`#lat-grid-all`, `#lat-grid-series`, `#lat-grid-movies`) are server-rendered in the HTML; the skeleton loader is cosmetic.
- **Continue Watching / history:** localStorage-based (client-side), not scrapable.
- **Player:** `/watch.php` wraps a third-party CDN iframe (`as-cdn21.top`, `go.gadgetsmania.xyz`, `play.abyssplayer.com`). The actual media stream is on those CDNs. This project only exposes the public watch-page links and does **not** fetch, download, or redistribute media.
- **Download links:** `/redirect/download/?fx={hash}` — exposed as links only, never followed to media.
- **REST API:** `/wp-json/wp/v2/ep` and `/wp-json/wp/v2/movie` expose id/slug/link/title/description but **not** servers, rating, or runtime — those require the HTML pages.