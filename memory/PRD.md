# DAVISPORN — PRD

## Original problem statement
Build DAVISPORN, a responsive adult video aggregation website inspired by porndig.com, reusing PHP 8 and MySQL/MariaDB, with modular source adapters (Upornia CSV, XVideos CSV, XNXX via RapidAPI, plus future adapters), home page (featured/popular/tags/categories), advanced filters, AJAX autocomplete search, category & tag landing pages, detailed video page with embeds & recommendations, age verification, legal pages (Terms/Privacy/DMCA/2257), XML sitemap, robots.txt, canonical URLs, structured data, SEO-friendly routing, admin area for sources / imports / cache / diagnostics, dark premium interface (red accent).

## Architecture (2026-02)
- **PHP 8.2** + **MariaDB 10.11** — installed inside the container.
- Full PHP app in `/app/php` (public entry, PSR-4 autoload under `App\`, MVC-like structure).
- **Supervisor** manages `mariadb` and `php-app` (built-in server on 127.0.0.1:9000) via `/etc/supervisor/conf.d/davisporn.conf`.
- Environment constraints (ingress → :3000 and /api → :8001) satisfied by making the pre-existing services reverse-proxy every request to PHP:9000:
  - `/app/backend/server.py` — FastAPI catch-all proxy (adds X-Forwarded-Host/Proto).
  - `/app/frontend/proxy.js` — Node HTTP proxy, replaces `craco start` in `package.json`.
- Data source adapters in `App\Adapters` implement a common `SourceAdapter` interface with a `SourceManager` that normalizes, deduplicates and writes to a shared `videos/categories/tags` schema.

## User personas
- **Visitor** — browses home, categories, tags, videos, uses search+filters, watches embedded videos.
- **Admin** — signs in (HTTP Basic), enables/disables sources, triggers imports, clears cache.

## Core requirements
- Age verification (modal + localStorage `davisporn_age_ok`).
- Home: hero, Featured, Popular categories, Most viewed, Newest, Trending tags.
- Browse `/videos` with quality/duration/source/sort filters + pagination.
- Category `/category/{slug}` and tag `/tag/{slug}` landing pages.
- AJAX search `/search` + `/api/suggest` autocomplete.
- Video detail `/video/{slug}` with embed (or placeholder for demo), metadata, chips, related grid, VideoObject schema.org JSON-LD.
- Legal: `/terms`, `/privacy`, `/dmca`, `/2257`.
- SEO: `/robots.txt`, `/sitemap.xml`, canonical URL, WebSite schema.org.
- Admin `/admin` (Basic auth, credentials in `/app/memory/test_credentials.md`).
- DB cache layer with TTL (`App\Core\Cache`).

## Implemented (2026-02)
- PHP MVC skeleton, router, view engine, cache, seeder, DB migrations.
- 4 source adapters: `DemoAdapter` (active), `UporniaCsvAdapter`, `XVideosCsvAdapter`, `XnxxRapidApiAdapter` (structure ready, disabled).
- All pages listed under Core requirements — dark premium theme with red accent, Space Grotesk + Manrope typography.
- Admin dashboard with source toggle, source import, cache clear, source status logs.
- Sitemap & robots derive scheme/host from `X-Forwarded-*` headers.
- Testing pass 2 confirmed both HIGH bugs fixed (age gate persistence, sitemap host).

## Backlog / P1
- P1 — Upornia CSV feed: user has not supplied a feed URL yet (adapter ready, source disabled).
- P1 — Gemini AI integration (use-case still unclarified: auto-tagging / descriptions / SEO copy) — use integration_expert + Emergent LLM key.
- P1 — `EMERGENT_EMAIL_KEY` still blank → weekly digest logs but doesn't send.
- P2 — XVideos "deleted urls" feed → remove dead videos.
- P2 — User accounts, favorites, watch history.
- P2 — Comments and ratings.

## Deployment note
This stack (PHP+MariaDB+reverse proxies) works in preview because both `backend` and `frontend` supervisor programs are still HTTP servers on the expected ports. If Emergent's deploy pipeline strictly requires FastAPI on 8001 (it does), the current setup satisfies it: FastAPI is running and is just acting as a proxy. MariaDB persistence lives on the pod volume — for a real deploy consider externalizing MariaDB or migrating to MongoDB.

## Iteration 3 (2026-02) — Connect Feeds + Scheduled Imports + Ads
- Admin dashboard now edits per-source config: feed_url for Upornia/XVideos CSV, api_key + host for XNXX RapidAPI. Values merge into runtime App::$config at boot.
- Nightly cron at 03:00 UTC via `/app/.emergent/crons.yml` → `POST /api/cron/nightly-import`, Bearer-protected with `WEBHOOK_CRON_SECRET` (in backend/.env and forwarded to php-app via supervisor). Endpoint acks 2xx immediately then imports enabled non-demo sources and clears cache.
- Advertising system: `ads` table + Ad model, 4 slot positions (home_top, home_middle, video_pre, video_sidebar), banner (image+link) + snippet (AdSense/ExoClick HTML/JS) kinds, weight, active flag, optional start/end windows. Admin CRUD (`/admin/ads/save`, `/admin/ads/delete`) with confirm.
- Testing iteration 3: 14/14 backend + UI smoke PASS.

## Iteration 4 (2026-02) — Live Categories + Search Insights
- Cron cadence changed from nightly `0 3 * * *` to `0 */6 * * *` (every 6 hours). Same endpoint /api/cron/nightly-import. cron name updated to `live-import`.
- `search_queries` table (q UNIQUE, count, results_last, last_seen). BrowseController::search logs on page-1 submits.
- Admin adds Search insights section: unique keywords / total searches / zero-result gaps stats + Top 20 table with click-to-open. Zero-result rows are highlighted with a red `GAP` pill to guide landing-page creation.

## Iteration 5 (2026-02) — Landing Page Builder
- `landings` table (slug, title, keyword, intro, categories_json, tags_json, active, views).
- Public route `/l/{slug}` renders curated collection: hero (CURATED COLLECTION + Search intent badges + total count), chips for included categories/tags, grid + pagination, CollectionPage JSON-LD.
- Admin CRUD at /admin/landings/{new,edit,save,delete}. Form has chip-style multi-select for categories & tags plus keyword, intro, slug and active toggle.
- One-click flow: in the Top-20 Search insights table, every keyword row shows a `+ Landing` button that opens the create form pre-filled with keyword, auto-slug, boilerplate intro, and pre-checked matching categories/tags (fuzzy LIKE on category/tag names).
- Union semantics: a video is included if it matches ANY chosen category OR tag OR title/description LIKE keyword.
- Iteration 4 tests: 12/12 backend + UI smoke PASS.

## Iteration 6 (2026-02) — Landing SEO Boost
- `landings` table extended with `meta_title`, `meta_description`, `og_image` (idempotent ALTER for existing rows).
- Public `/l/{slug}` emits `<title>`, `<meta description>`, canonical URL (from X-Forwarded-Host), full Open Graph tags (`og:type=video.other`, `og:site_name`, `og:url`, `og:title`, `og:description`, `og:image`) and Twitter card (`summary_large_image` if image present, otherwise `summary`).
- Sensible defaults when admin leaves fields empty: `meta_title = title — DAVISPORN`, `meta_description = intro trimmed to 160 chars`, `og_image = first video thumbnail`.
- Admin landing form gets a new **SEO & social sharing** fieldset (data-testid l-meta-title, l-meta-desc, l-og-image).

## Iteration 7 (2026-02) — Sitemap Landings + Social Preview Tester
- `/sitemap.xml` now includes every active landing with lastmod, `<changefreq>daily</changefreq>` and `<priority>0.7</priority>` so Google discovers them on the next crawl.
- Landing form (only when editing an existing landing) shows a **Social preview tester** row with 4 buttons: X Card Validator, Preview on X (tweet intent with URL), Facebook Sharing Debugger, LinkedIn Post Inspector — each opens the validator/preview with the live landing URL prefilled. On the create form it shows a "Publish first" hint.

## Iteration 8 (2026-02) — Auto Suggest + Templates + Ping
- Cron `daily-suggest` at 03:30 UTC (`POST /api/cron/daily-suggest`, Bearer-protected). `LandingSuggester::run(3)` picks the top 3 zero-result queries from the last 7 days that don't already have a landing and creates them as DRAFT (active=0, suggested=1). Admin dashboard highlights suggested drafts with an amber `DRAFT SUGGEST` badge.
- 3 templates (`grid`, `editorial`, `top10`) stored on `landings.template`. `pages/landing.php` switches layout:
  - grid — classic responsive tile wall (default)
  - editorial — hero pick + magazine copy + secondary grid
  - top10 — ranked list 1-10 with gold/silver/bronze rank badges
- Radio-card template picker in the landing form.
- `PingService` submits every newly ACTIVE landing to IndexNow (Bing+Yandex+Seznam), Google and Bing sitemap ping (best-effort). IndexNow key served at `/{key}.txt`, key auto-generated at `storage/indexnow.key`. Ping results logged to `pings` table.

## Iteration 9 (2026-02) — Rich Snippets + Bulk Publish
- Top-10 landings now emit a full `ItemList` JSON-LD (10 ListItem entries, each embedding a VideoObject with contentUrl/thumbnail/duration/uploadDate) — Google can surface the numbered list directly in the SERP.
- Landings table gets multi-select checkboxes (`bulk-cb-{id}`, `bulk-all`) with a sticky bulk toolbar (`bulk-toolbar`) exposing **Enable + Ping**, **Re-ping**, **Delete**. Enable also clears the `suggested` flag and pings IndexNow+Google+Bing for every selected row. All actions confirm and show a flash with counts.

## Iteration 10 (2026-02) — Landing A/B Titles
- New `title_variant_b` column and `landing_ab_stats(landing_id, variant, impressions, clicks)` table.
- When variant B is set, `Landing::pickVariant()` assigns each visitor a sticky variant via cookie `l_ab_{id}` (30-day) and increments impressions on page 1. Public `<h1>` uses `display_title` (A or B).
- Click tracking beacon: `POST /api/ab/click?l={id}&v={A|B}` fired via `navigator.sendBeacon` from every video title link on the landing page.
- Admin landings table gets an A/B column showing clicks/impressions + CTR per variant, plus a `WIN` badge on the leader once each variant has >= 20 impressions.

## Iteration 11 (2026-02) — Home Trending Widget + Pod-Restart Resilience
- Home page gets a **Trending collections** section between "Most viewed" and "Newest": up to 6 active landings ranked by combined CTR (falls back to views when < 5 impressions).
- Tile design: 240×180 card with optional OG image background, template badge (GRID/EDITORIAL/TOP10), title, keyword tag and `👁 views · CTR x.x%` footer. `data-testid=trending-landings` and `trending-tile-{id}`.
- Cache-integrated (home:v1 already TTL 5 min; invalidated by admin actions).
- **Resilience fix**: pod restarts wipe `/usr/*` (php + mariadb binaries) and `/var/lib/mysql`. Added `/app/php/bootstrap.sh` (apt-installs php-cli+mariadb-server + initializes datadir if missing) and `/app/php/db_seed.sh` (creates DB + user). Supervisor conf now runs `bootstrap.sh &&` before each start of `mariadb` and `php-app`, so a pod restart auto-recovers within ~60s. Note: DB data itself is not persistent (it lives outside `/app`/`/root`) — landings, ads, search-queries and A/B stats reset on each pod restart until we move the datadir under `/app/mysql`.

## Iteration 12 (2026-02) — Home Personalization
- New `App\Support\Personalization` writes/reads a compact `dv_taste` cookie (`slug:count;slug:count`, capped at 10, 90-day expiry).
- `BrowseController::video` calls `Personalization::record()` with the current video's category slugs so the visitor's taste self-builds while browsing.
- `Landing::trending()` accepts an optional `boostCategory` and computes a per-row `boost` column via `JSON_CONTAINS(l.categories_json, JSON_QUOTE(?))`. Order: boost DESC → CTR DESC → views DESC.
- Home template shows a `TUNED TO YOUR TASTE` badge when personalization is active and marks each matching tile with a gold `★ FOR YOU` badge plus a subtle amber outer glow.
- Cache: kept the shared `home:v1` for the non-personalized sections and computes `trending_landings` outside the cache on every request (cheap SQL, negligible cost).

## Iteration 13 (2026-02) — Personalized Home Row + Persistent DB
- **Persistence**: `bootstrap.sh` and supervisor conf now use `--datadir=/app/mysql`. Existing data copied from `/var/lib/mysql` → `/app/mysql` (preserved on migration). Landings/ads/A-B stats now survive pod restarts.
- **Personalized row**: `HomeController::index` reads `Personalization::topCategory()`, resolves it via `Taxonomy::categoryBySlug`, and — if the category has ≥ 3 videos — renders a new "Because you like {Category}" section above the trending widget with the top 12 videos in that category. `data-testid=taste-row` / `data-testid=taste-grid`.
- Section header keeps the amber `FOR YOU` badge and links to `/category/{slug}` with a "See all N →" CTA.

## Iteration 14 (2026-02) — Explicit Prefs + Weekly Digest
- **Explicit Preferences**: cookies `dv_pins` and `dv_hides` (comma-separated, 180 days) with `Preferences` support class. Public JSON endpoints `POST /api/prefs/{pin|hide}` toggle a slug and return updated arrays. Home cat-chips get inline `☆/★` and `✕` buttons; pinned chips show 📌 + amber background and are reordered to the front; hidden chips are filtered out with a "Hidden: N" clear link. The first pin overrides the implicit `Personalization::topCategory()` for both trending boost and the "Because you like" row.
- **Weekly Digest** (Resend via Emergent, playbook applied):
  - `App\Support\EmailService` — POSTs to `https://integrations.emergentagent.com/api/v1/email/send` with `X-Email-Key` header, `from_name=DAVISPORN`; runs G2/G3 structural gate (no form/input, no non-https/shortener/IP/punycode links, no credential-ask phrases). Returns 0/skipped if `EMERGENT_EMAIL_KEY` is unset.
  - `App\Support\DigestBuilder` — server-side template producing a self-contained HTML email with 4 KPI tiles, new drafts, top searches, best-performing landings and a red "Open admin dashboard" CTA.
  - Cron `weekly-digest` at `0 9 * * 1` UTC → `POST /api/cron/weekly-digest` (Bearer-protected). Ack immediately then send + log to `digests` table.
  - Admin preview at `/admin/digest` — shows recipient, key status, full preview and last 5 send attempts + "Send now" button. Recipient in env `DIGEST_TO_EMAIL=superpanel87@gmail.com`.
  - **Note**: `EMERGENT_EMAIL_KEY` currently blank in this env (not auto-provisioned). Provisioned key can be added to `/app/backend/.env` and `davisporn.conf` supervisor `environment=` and it will start sending on next Monday.

## Iteration 15 (2026-02) — Preference Panel
- New topbar button `★` (`data-testid=open-prefs`) opens a slide-out drawer from the right (`#pref-drawer`, backdrop, close button, apply-and-reload footer).
- Drawer sections: **📌 Pinned** (removable chips), **✕ Hidden** (removable chips), **Add category** (searchable list of up to 60 popular categories with inline `pin` / `hide` buttons that flip color when active).
- Data endpoint: `GET /api/prefs` now returns `{pins, hides, labels, all}` including pretty names + video counts, so the drawer renders human-friendly labels rather than raw slugs.
- JS: toggling in the drawer calls the existing `/api/prefs/pin` / `/api/prefs/hide` and re-renders state in place; "Clear hidden" removes every hide in one tap; "Apply & reload" bounces the page so home reorders instantly.

## Iteration 16 (2026-02) — Tag Preferences
- `Preferences` support class refactored to accept a `type` argument (`category` | `tag`) with dedicated cookies: `dv_pins`/`dv_hides` for categories and `dv_tag_pins`/`dv_tag_hides` for tags. Same 20-slug cap and 180-day expiry.
- `PrefsController` accepts `type` in POST body; `GET /api/prefs` now returns `{category:{pins,hides,labels,all}, tag:{pins,hides,labels,all}, ...backwards-compat category keys}` so older drawer code keeps working.
- Home "Trending tags" section now renders each chip with `☆` pin and `✕` hide buttons (`tag-pin-{slug}` / `tag-hide-{slug}`). Chips are reordered with pins first and hidden ones filtered out; a "Hidden tags: N — clear" line appears when needed.
- Drawer gained two tabs (`pref-tab-category`, `pref-tab-tag`) each with its own pinned/hidden lists, search box and picker. Bulk "Clear hidden" wipes hides across both taxonomies.

## Iteration 17 (2026-06) — REAL FEEDS LIVE (XVideos CSV + XNXX RapidAPI)
- **XVideosCsvAdapter** rewritten for the official webmaster export (`;`-separated, no header, 15 cols: url,title,duration,thumb,embed,tags,pornstars,id,category,quality,uploader,-,date,preview,views). Streams `.csv.gz` over HTTPS with `zlib.inflate` filter and stops after `import_limit` rows, so even the 800 MB full export is never fully downloaded. Default feed = `xvideos.com-export-week.csv.gz`. Cleans mangled entities (`&#039_`), maps 1080P→FullHD/720P→HD/SD, "Unknown" category skipped, pornstars become tags.
- **XnxxRapidApiAdapter** rewritten for `porn-xnxx-api.p.rapidapi.com`: `POST /search {q,page}` → `{count,page,results[{title,thumbnail,duration,views,video_link}]}`. Embed built as `https://www.xnxx.com/embedframe/{id}`; duration ("10min"/"02:23:44") and views ("22.7M") parsed. Iterates configurable `queries` (default 12 categories) and rotates `page_cursor` (1..8) stored in `sources.config` so each 6-hourly cron discovers new videos with ~12 requests.
- **Background imports**: `bin/import.php <slug> [limit]` CLI runner; `SourceManager::importAsync()` spawns it with nohup (log `storage/logs/import.log`), status column shows `RUNNING…` then `OK — inserted N, updated M`. Admin Import button and cron `/api/cron/nightly-import` both use it. `SourceManager::limitFor()` honours per-source `import_limit` (default 300, XVideos 500, XNXX 400, max 5000).
- Admin: config form gains `queries`, `import_limit`, masked api_key; **Purge demo** button (`POST /admin/source/purge-demo`, refuses if no real videos). `saveSourceConfig` now touches only submitted fields (partial POST can no longer wipe the API key). `toggleSource` keeps `enabled` column and JSON in sync.
- `recount()` auto-promotes the 12 most-viewed embedded videos to Featured; home Featured grid shows 12 (`data-testid=featured-grid`).
- Resilience: `bootstrap.sh` uses `flock` + waits for foreign apt locks (previous FATAL was two parallel apt runs), initialises datadir at `/app/mysql`; supervisor `startretries=30`, `PHP_CLI_SERVER_WORKERS=8`, `stopasgroup/killasgroup` for php-app.
- State: demo purged; ~530 XVideos + ~750 XNXX videos live with real embeds; Upornia still disabled (no feed URL). Test iteration 5: 18/18 backend PASS + UI smoke.
- Credentials live in DB `sources.config` (xnxx api_key/host, xvideos feed_url) — see `/app/memory/test_credentials.md`.

## Iteration 18 (2026-06) — Code review fixes
- Tests: secrets/URLs removed from source; `backend/tests/_env.py` loads `backend/.env` (+ `frontend/.env`) and exposes `BASE_URL`, `ADMIN_AUTH` (`ADMIN_USER`/`ADMIN_PASS`), `CRON_SECRET`. `is True` → `== True`; complex tests split into helpers (`_wait_for_import`, `_recreate_landing`, `_landing_video_count`, `UNION_SLUGS`…); stale iteration-4 assertions repaired (`<tr >` rows, exact slug match, `CURATED ·` badge, crons.yml lookup by endpoint).
- `backend/server.py`: `upstream` used only inside `try`; unused import dropped.
- Frontend (unused React shell, still linted): `App.js` effect without stale closure + no console; `use-toast.js` reducer split into `dismissToasts`/`removeToasts` with default branch, effect deps `[setState]`; `proxy.js`/`craco.config.js` logs guarded by `NODE_ENV`.
- Test iteration 6: 26/26 + 19/19 backend PASS, UI smoke PASS.
