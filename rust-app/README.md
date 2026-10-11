# Rust Game Loadouts

This branch keeps the Laravel source and implements public browsing, accounts, voting and submissions with Axum, SQLx/MySQL and Askama. The root Dockerfile builds Rust; `Dockerfile.php` retains PHP. Public writes are opt-in and use a separate pool; browsing retains its read-only guard. See [PUBLIC_PARITY.md](PUBLIC_PARITY.md) for activation, encrypted cookies, Laravel token compatibility and validation details. Admin routes remain excluded.

## Quick local run (isolated representative data)

From the repository root, with Docker Compose installed:

```sh
docker compose -f rust-app/compose.yaml up --build --wait
curl -i http://127.0.0.1:8080/healthz
curl -i http://127.0.0.1:8080/Call+of+Duty+Modern+Warfare/M4A1
python3 rust-app/tools/benchmark.py --base http://127.0.0.1:8080 --requests 200 --concurrency 1 8 --output rust-local.json
```

The fixture DB is bound to loopback on port 3307, uses temporary storage, and has a SELECT-only `loadouts_reader` with no password. These are deliberately disposable local fixtures, never a production credential pattern. `down` removes them:

```sh
docker compose -f rust-app/compose.yaml down
```

To build/run without Docker, first start just the fixture database:

```sh
docker compose -f rust-app/compose.yaml up -d --wait fixture-db
cargo build --locked --release --manifest-path rust-app/Cargo.toml
DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=loadouts_fixture DB_USERNAME=loadouts_reader METRICS_HEADERS=1 rust-app/target/release/game-loadouts
```

Run from the repository root so `ASSET_DIR=public` resolves correctly. Rust 1.99.0 was tested; the container pins that compiler release and Cargo.lock pins dependencies. No database is needed to compile or run unit tests.

## Configuration and connection safety

Use environment variables or mounted secrets. `rust-app/.env.example` is a reference, **not automatically loaded**. Never put real credentials in source, build arguments, image layers, logs, shell history or documentation. In Cloud Run, mount the password through Secret Manager. Provision a reader on the existing database with only SELECT on the eight public tables; do not run the fixture SQL on it.

| Variable | Default / purpose |
|---|---|
| `DATABASE_URL` | Optional SQLx MySQL URL; overrides the five individual DB settings below. URL-encode credentials if using this alternative. |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` |
| `DB_DATABASE`, `DB_USERNAME` | Required if DATABASE_URL is absent |
| `DB_PASSWORD` | Optional; unset/empty supports disposable local reader |
| `DB_SOCKET` | Optional `/cloudsql/PROJECT:REGION:INSTANCE` Unix socket path |
| `DB_SSL_MODE`, `DB_SSL_CA` | SQLx TLS mode and optional CA file; use `verify_identity` for TCP TLS with a verified hostname |
| `DB_POOL_MAX` | 10; bounded to 1–100 connections, reused across requests |
| `DB_CONNECT_TIMEOUT_MS` | 5000 for initial connectivity preflight |
| `DB_ACQUIRE_TIMEOUT_MS` | 5000, bounds waiting for a pool connection including new connection establishment |
| `DB_QUERY_TIMEOUT_MS` | 5000 per application SQL query; a timed-out connection is discarded |
| `PORT` | 8080; listens on `0.0.0.0` |
| `ASSET_DIR` | `public` locally, `/app/public` in image |
| `PUBLIC_BASE_URL` | `https://gameloadouts.com`; used for sitemap and Disqus canonical URLs |
| `MEDIA_ORIGIN` | Empty; optional image host prefix for DB paths under `/uploads/` |
| `DISQUS_ENABLED` | 1; set 0 to disable browser-side threads and comment counts for controlled comparisons |
| `SEARCH_MODE` | `google`; `local` explicitly opts into complete database weapon/game-name search |
| `SEARCH_API_URL` | Optional original Google Custom Search URL **prefix**, falling back to the copied `SEARCH_URL` environment variable, including query/key parameters. Treated as a secret; bounded to a 5s request and a 2 MiB response. Without it, use Laravel's external Google fallback. |
| `METRICS_HEADERS` | 0; set 1 for X-DB-Queries and Server-Timing |
| `RUST_LOG` | `game_loadouts=info`; JSON stdout, no SQL text, input paths, credentials or raw database errors |

Connections execute `SET SESSION TRANSACTION READ ONLY` once, complementing SELECT-only grants. Startup performs a connectivity check before binding. Application query counts exclude connection setup, SQLx health checks, prepare protocol messages and pool housekeeping; these are not measurements of every MySQL wire packet. `db_ms` measures SQL execution/fetch time, excluding pool acquisition; `elapsed_ms` includes acquisition and rendering. There is no application cache, permanent page cache or hidden result truncation. Dynamic responses have `Cache-Control: no-store`.

## Public behavior and deliberate differences

- Homepage: latest **three live games**, top **five voted loadouts per game** (up to 15), descending votes. A single UNION query retrieves the ranked cards; one attachment batch hydrates them.
- Games: descending creation date, alternating two-column layout. The metadata list follows ID ordering as the legacy un-ordered primary-key retrieval normally does; the all-games result also supplies navigation and footer without another query.
- Game: weapons grouped by type, alphabetically by name; top five **voted** loadouts and five latest updated loadouts. Both sets share one attachment fetch.
- Weapon: **every loadout**, descending votes, including zero-vote records. Available attachment information and the Advanced Warfare Other1/Other2 slot duplication remain readable. No pagination.
- Loadout: full attachments, votes, submitter (Guest for guest/deleted users), original title and description formats. Wrong game/weapon/loadout relationships return 404 instead of showing a mismatched loadout.
- URLs: PHP `urlencode` semantics preserved (space `+`, literal plus `%2B`, slash `%2F`, tilde `%7E`, UTF-8 byte escapes). Percent-encoded spaces are accepted too. Raw URI segments are decoded once, avoiding `%2B` becoming a space.
- Navigation/footer share one recent-games result. The legacy limit-before-live-filter behavior is preserved (five rows for navigation, four for footer).
- Search: `/search_weapons?query=...` keeps the original `{ "suggestions": ["weapon / game", ...] }` contract, first slash-delimited term, LIKE wildcards and full results. Exact `weapon / game` searches redirect to the weapon URL. GET `/search` supports the browsing form; POST `/search` remains a **read-only compatibility alias**, never a write endpoint. Google Custom Search results can be rendered with the original API prefix; API failure/missing configuration falls back to the legacy Google site-search redirect. Local search is an explicit optional mode, not asserted to match Google ranking/snippets.
- Stats: per-game votes/unique loadouts and the last 30 days of vote activity; legacy inclusion rules remain (totals omit games with no votes; recent chart has only dates with recorded votes and zero fills missing game/date combinations). Existing Chart.js/colors are reused. The inaccurate “cached hourly” label is removed.
- Sitemap: static homepage/games and complete legacy dynamic game/weapon/loadout results, with original priority/change frequency/lastmod values. Login/join pages are kept out of the sitemap and marked noindex. The legacy inclusion of non-live games in the sitemap is retained, though their browsing pages remain 404; reconsider separately if desired.
- Identical vote rankings need a stable tie-break: loadout ID ascending, consistent with primary-key retrieval and the fixture oracle. Legacy top queries specified no tie-break, so exact tied ordering on production is not guaranteed until verified there.
- Public accounts, voting, guest CAPTCHA, attachment submission, email confirmation, password recovery, account password updates and user submissions are available when `INTERACTIVE_ENABLED=1`; they remain disabled otherwise. Admin remains unavailable.
- `loadout-<id>` threads and comment counts on **tryharddev** load asynchronously in the browser with `DISQUS_ENABLED=1`. Confirmed members use the existing signed Disqus SSO protocol when `DISQUS_SECRET_KEY` is configured.
- Existing Bootstrap/CSS/fonts/images and autocomplete are reused. Native forms support voting and attachment selection with optional JavaScript enhancements. `ADS_ENABLED=1` restores the existing AdSense unit; `GA4_MEASUREMENT_ID` enables current Google Analytics. The old Universal Analytics ID is not reused because that product no longer collects data. Disable external integrations for controlled browser comparisons.
- Real infrastructure failures return a styled 503, malformed URL text 400, missing records 404, unsupported write methods 405. Legacy PHP converted most failures into 404.

## Schema evidence and media

The original browsing fixture was built without production DB/cloud credentials. Public parity activation now requires an authorized schema preflight; the fixture remains isolated. The tracked SQLite file is empty; core tables have no complete migrations. The representative MySQL schema is inferred from `app/models`, controllers, templates, seeders and the partial migrations. **It is not a production schema dump.**

Required tables/columns:

- games: id (string), live, short_name, theme_color, thumb_url, header_url, created_at.
- weapons: id, game_id, name, type, image_url, thumb_url, min_attachments, max_attachments.
- loadouts: id, weapon_id, user_id (users.email), updated_at.
- users: email, username, role.
- attachments: id, name, slot, image_url, thumb_url.
- attachment_loadout: attachment_id, loadout_id.
- attachment_weapon: attachment_id, weapon_id.
- loadout_user: loadout_id, created_at (count rows, not distinct users, matching legacy votes).

IDs are assumed numeric except games/users; unsigned counts are cast to SIGNED for consistent SQLx decoding. Production collation, uniqueness, indexes, nullability and timestamp/timezone settings remain unverified. Intended useful existing indexes are weapons(game_id,name), loadouts(weapon_id), loadout_user(loadout_id), attachment_loadout(loadout_id), attachment_weapon(weapon_id), users(email). **Encrypted cookie sessions and the existing Laravel confirmation/reset storage require no schema migration. The schema-check command reads metadata; it never creates or alters tables or indexes.** Inspect `SHOW CREATE TABLE`/`SHOW INDEX` with authorized access and EXPLAIN representative queries before drawing production conclusions. Match DB timezone/collation with PHP.

Current production `public/uploads` assets have been copied from the master content snapshot into the Rust branch, including Battlefield 6, Black Ops 7 and COD Mobile artwork. There are 13,165 tracked paths under `public/uploads`. Controllers upload directly into that directory and thumb/header subdirectories; runtime uploads can exist outside Git. The image copies tracked files and any deliberately supplied local upload snapshot. Audit against an authorized DB export before deployment:

```sql
SELECT image_url FROM games UNION SELECT thumb_url FROM games UNION SELECT header_url FROM games
UNION SELECT image_url FROM weapons UNION SELECT thumb_url FROM weapons
UNION SELECT image_url FROM attachments UNION SELECT thumb_url FROM attachments;
```

Export one non-empty path per line to a private temporary file, then:

```sh
python3 rust-app/tools/audit_media.py /path/to/media-paths.txt --assets public
```

Recover missing media from the existing deployment/storage into the build context, or set `MEDIA_ORIGIN` to a verified host that serves the original `/uploads/...` URLs. Do not assume an image exists just because a DB record refers to it. The server and container require no writable/persistent local disk. Static assets use correct MIME types, conditional Last-Modified requests, and `public, max-age=3600`; **unversioned assets are not immutable**. Upload filenames may contain literal `+`; media paths remain literal rather than applying game/weapon URL decoding.

## Verification

```sh
cargo fmt --manifest-path rust-app/Cargo.toml -- --check
cargo clippy --locked --manifest-path rust-app/Cargo.toml --all-targets -- -D warnings
cargo test --locked --manifest-path rust-app/Cargo.toml
# After starting the disposable fixture DB; this suite performs fixture-only test writes:
TEST_DATABASE_URL=mysql://root@127.0.0.1:3307/loadouts_fixture cargo test --locked --manifest-path rust-app/Cargo.toml -- --include-ignored
# With the Rust server running against those SAME fixture rows:
python3 -m pip install beautifulsoup4
DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=loadouts_fixture DB_USERNAME=loadouts_reader php rust-app/tools/php_oracle.php > php-oracle.json
python3 rust-app/tools/validate_oracle.py php-oracle.json
```

Optional browser checks (with the same local fixture server running):

```sh
npm install --no-save playwright@1.64.0
npx playwright install chromium
node rust-app/tools/browser_smoke.mjs
```

The oracle executes the original Laravel query/count/sort logic through PDO. It checks rankings/votes/attachments, **not Laravel framework latency**. Tests verify up to 15 homepage cards, all weapon rows, zero votes/ties, no vote/attachment multiplication, author rendering, full URL encoding, unavailable methods, no broken submission handlers, search/autocomplete, XML, missing/non-live/wrong-relationship records, DB read-only grants, timeout recovery, and constant queries after expanding a weapon from 7 to 1,007 loadouts. `.github/workflows/rust.yml` additionally runs MySQL 8.4 and builds/smoke-tests the non-root read-only container when CI is available.

## Separate Cloud Run service

Build from root. Runtime contains the release binary, static assets and Debian TLS certificates; SQLx and HTTP use Rustls, so no OpenSSL runtime package is needed.

```sh
docker build --pull -t game-loadouts-rust:experiment .
docker image inspect game-loadouts-rust:experiment --format '{{.Size}}'
docker run --rm --read-only --cap-drop ALL --env-file /path/to/private/runtime.env -p 127.0.0.1:8080:8080 game-loadouts-rust:experiment
```

Use your existing Artifact Registry repository and Cloud SQL instance. Substitute placeholders, with the same region/network/CPU/memory as the PHP comparison service. This deploy command is provided for later execution; it was **not executed** here.

```sh
gcloud builds submit --project PROJECT --tag REGION-docker.pkg.dev/PROJECT/REPOSITORY/game-loadouts-rust:experiment .
gcloud run deploy game-loadouts-rust-experiment \
  --project PROJECT --region REGION \
  --image REGION-docker.pkg.dev/PROJECT/REPOSITORY/game-loadouts-rust:experiment \
  --service-account READER_SERVICE_ACCOUNT \
  --cpu 1 --memory 512Mi --concurrency 8 --min-instances 0 --max-instances 2 \
  --timeout 30 --port 8080 --no-allow-unauthenticated \
  --add-cloudsql-instances PROJECT:REGION:INSTANCE \
  --set-env-vars DB_SOCKET=/cloudsql/PROJECT:REGION:INSTANCE,DB_DATABASE=DATABASE,DB_USERNAME=READ_ONLY_USER,METRICS_HEADERS=1,DISQUS_ENABLED=0 \
  --set-secrets DB_PASSWORD=READ_ONLY_PASSWORD_SECRET:latest
```

The service account needs only the relevant Cloud SQL client and secret-access permissions. For private-IP TCP use DB_HOST and the same VPC connectivity as PHP instead of DB_SOCKET. `/healthz` is a lightweight liveness endpoint, not a continuous DB health probe. SIGTERM/SIGINT stop new requests and drain existing requests before closing the pool. Cloud Run's finite shutdown grace still applies to long requests; configure query/acquisition timeouts accordingly.

## Reproducible comparisons

See [VALIDATION.md](VALIDATION.md) for the measurements actually taken and unmeasured items.

1. Use an isolated DB snapshot with identical schema/data for both services. Do not load-test the live site. Match CPU/memory, region, DB proximity, DB indexes/timezone, pool/connection limits, Cloud Run billing mode/concurrency/autoscaling, and request bodies/route mix. Record commit/image digest, database row counts and all resource settings.
2. Hit origin/direct service URLs, bypassing CDN/browser caches. Disable third-party scripts for end-to-end browser work on both services, and keep HTML-only and full asset navigation measurements separate. Metrics headers are enabled for comparison; no application cache exists in Rust. Legacy PHP caches must be cleared/disabled for an uncached baseline, then optionally measured as a separately labeled cached scenario.
3. Run `benchmark.py --requests 200 --concurrency 1 8` three or more times in alternating service order. It warms each route/workload with 20 requests, uses persistent connections, cache-busting query parameters and no-cache headers, validates HTTP status, and reports p50/p95, query counts, DB execution/fetch time and response bytes. It refuses gameloadouts.com. Modest remote traffic requires `--allow-isolated-target` on a dedicated service. For IAM-only Cloud Run, use a local `gcloud run services proxy game-loadouts-rust-experiment --project PROJECT --region REGION --port 8088`; proxy/network overhead is part of client latency, so use the same path for PHP.
For the isolated legacy PHP service, set the Laravel cache driver to `array` before resolving the cache service (so each PHP request starts with an empty request-local store). For query/time headers, register this fixture-only instrumentation early in `app/start/global.php`; do not log SQL/bindings. Do not install this on the live service:

```php
if (getenv('BENCHMARK_METRICS') === '1') {
    Config::set('cache.driver', 'array');
    $queryCount = 0;
    $dbMs = 0;
    DB::listen(function ($sql, $bindings, $time) use (&$queryCount, &$dbMs) {
        $queryCount++;
        $dbMs += $time;
    });
    App::after(function ($request, $response) use (&$queryCount, &$dbMs) {
        $response->headers->set('X-DB-Queries', (string) $queryCount);
        $response->headers->set('Server-Timing', sprintf('db;dur=%.3f', $dbMs));
        $response->headers->set('Cache-Control', 'no-store');
    });
}
```

Confirm that the listener includes queries during view rendering by checking a weapon page's N+1 count before relying on its headers. If the installed Laravel version renders the view after its after-filter, move the header emission after response rendering. Run the **same** benchmark client against both isolated services; without PHP instrumentation the DB columns remain unmeasured, not zero.

4. Collect process RSS/high-water mark with `--pid PID` (Linux /proc), and cgroup/container peak memory separately. Image size comes from `docker image inspect --format '{{.Size}}'` (uncompressed local size); registry compressed transfer size is a separate measure. Binary size is not image size.
5. Measure local warm-host process startup with `measure_startup.py`; it performs ten launches/readiness probes and verifies clean SIGTERM exits. Real Cloud Run cold starts require separate scale-to-zero trials: keep min=0, verify no active instance, invoke one request with no intervening probes, and correlate client latency, the `ready` log, revision startup metrics and request logs. A deploy/new revision startup is not the same trial as steady-state cold activation. Do not report the local startup result as a Cloud Run cold start.
6. For billable time, run identical scheduled bursts into otherwise idle dedicated services and record UTC start/end. After Cloud Monitoring's collection delay, use:

```sh
python3 rust-app/tools/cloud_billable_time.py --project PROJECT --service game-loadouts-rust-experiment --start 2026-10-10T18:00:00Z --end 2026-10-10T18:15:00Z > rust-billable.json
```

It retrieves `run.googleapis.com/container/billable_instance_time`. Read all revision series and avoid background probes/traffic. Cloud metric granularity and billing rounding mean summing request latencies is not a replacement. Compare the same idle/burst schedule and billing configuration for PHP. Sources: [Cloud Run runtime contract](https://docs.cloud.google.com/run/docs/container-contract), [metric definitions](https://docs.cloud.google.com/monitoring/api/metrics_gcp_p_z), [Cloud Run pricing/billable time](https://cloud.google.com/run/pricing).

A fully batched Laravel performance baseline has **not** been implemented. Consequently, any legacy PHP-versus-this-Rust comparison combines SQL batching, removal of synchronous Disqus, cache configuration, container/framework changes and Rust; it cannot isolate the language's contribution. The PDO correctness oracle is not a substitute performance baseline. For language-only claims, first implement the same batched query plan in PHP, remove server-side Disqus from both, use equivalent templates/data, and compare uncached variants. Optional cache experiments must be independently disableable, bounded and expiring; none are present in V1.
