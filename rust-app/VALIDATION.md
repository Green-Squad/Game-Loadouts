# Validation and measurements — 2026-10-10

## Completed

- Built release binary with Rust 1.99.0; Cargo.lock checked in, no build-time DB required.
- `cargo fmt --check`, `cargo clippy --locked --all-targets -- -D warnings`, unit tests and two explicit fixture integration tests passed. Integration uses MariaDB 10.11.14 over MySQL protocol, with SELECT-only app connections; the test setup/admin connection only modifies disposable fixture rows.
- PDO/PHP 8.3.6 correctness oracle executing original Laravel SQL/count/sort logic matches Rust homepage rankings and complete weapon list/votes/attachments. This is NOT a running PHP 5.6/Laravel latency baseline.
- Tests cover 15 popular cards, zero votes and ties, escaped author/attachment text, advanced URL encoding, empty and missing records, relationship validation, no write methods, search/autocomplete (Google provider stub and fallback plus optional local mode), stats, sitemap, reader grants, query timeout/connection recovery, and unchanged six-query weapon budget at 7 and 1,007 loadouts.
- Chromium 156 headless browser checks passed: desktop home/games/game/weapon/loadout/stats, asset responses and JS errors, actual typed autocomplete selection display, accordion expansion, weapon filtering, disabled votes, mobile home/navigation and no horizontal overflow. Screenshots inspected for home/weapon/game/mobile; fixture uses tracked real gun media after the first visual pass revealed that a header-image stand-in was too wide for weapon thumbnails.
- Three read-only production page fetches (home, COD Modern Warfare/M19, loadout 1082) checked the actual public markup, title/description, URL/attachment ordering patterns and Disqus identity. All 24 sampled image paths are in Git. No production traffic benchmark or DB access occurred. Full production result/ranking parity awaits the same authorized DB snapshot.
- CSS HTTP response verified as `text/css`, `public, max-age=3600`, with Last-Modified and no immutable flag. Health check returns 200. Ten startup/SIGTERM cycles exited cleanly.

## Local warm measurements

Three final runs, each **200 measured requests + 20 warmups per route and concurrency**, persistent HTTP connections, concurrency 1 and 8, no application caching. Single local virtualized host, same-host MariaDB and Rust processes, **no fixed Cloud Run CPU/memory allocation**. Fixture: 7 games, 8 weapons, 24 loadouts, 39 votes, 4 attachments (72 loadout-attachment relationships). Background host scheduling/DB work is uncontrolled. Ranges below retain variation rather than selecting the fastest run.

`/COD-MW` abbreviates the actual URL `/Call+of+Duty+Modern+Warfare` only in this table.

| Route | Concurrency | p50 ms range | p95 ms range | Queries | Mean DB ms range |
|---|---:|---:|---:|---:|---:|
| `/` | 1 | 2.35–2.89 | 4.07–5.36 | 4 | 1.68–2.18 |
| `/` | 8 | 4.87–6.01 | 9.03–11.75 | 4 | 2.74–3.26 |
| `/games` | 1 | 0.36–0.46 | 0.72–1.73 | 1 | 0.15–0.23 |
| `/games` | 8 | 1.52–2.10 | 5.85–6.46 | 1 | 0.27–0.38 |
| `/COD-MW` | 1 | 2.83–3.20 | 4.54–8.23 | 6 | 1.98–2.38 |
| `/COD-MW` | 8 | 4.89–5.59 | 8.23–9.25 | 6 | 2.73–2.99 |
| `/COD-MW/M4A1` | 1 | 2.48–2.51 | 3.96–4.27 | 6 | 1.61–1.73 |
| `/COD-MW/M4A1` | 8 | 4.73–5.29 | 7.97–10.93 | 6 | 2.48–2.74 |
| `/COD-MW/M4A1/1` | 1 | 1.89–1.98 | 3.07–3.56 | 5 | 1.18–1.28 |
| `/COD-MW/M4A1/1` | 8 | 3.50–3.80 | 6.00–7.33 | 5 | 1.75–1.87 |
| `/stats` | 1 | 1.64–1.76 | 2.96–3.60 | 3 | 1.09–1.20 |
| `/stats` | 8 | 3.40–3.51 | 6.22–7.96 | 3 | 1.43–1.75 |
| `/search_weapons?query=M4` | 1 | 0.40–0.45 | 0.91–1.11 | 1 | 0.18–0.19 |
| `/search_weapons?query=M4` | 8 | 2.04–2.45 | 7.12–8.48 | 1 | 0.26–0.30 |

Process RSS after the final workloads: **9.44 MiB**; observed process high-water mark **9.44 MiB**. This is not total container/cgroup memory. Stripped release binary: **6,268,120 bytes**; this is not Docker image size.

Local startup, 10 warm-host launches with a ready local DB: health-readiness median **6.61 ms**, range **6.14–12.22 ms**, 5 ms polling interval. Internal `ready` log median **1.20 ms**. These exclude image pull, Cloud Run provisioning, remote DB connection delays and real Cloud Run cold starts.

Raw reports and source/environment digest are in [measurements](measurements/). `db_mean_ms` is measured execution/fetch time, not pool wait; request latency includes pool wait, HTTP and HTML rendering. Query count covers application SQL, excludes prepare/connection/session maintenance. An earlier exploratory pass showed more variable weapon-tail latency; final repeated runs are reported here. No percentage PHP speedup is claimed.

## Not measured / still unverified

- Actual production MySQL schema, indexes/collation, timezone and complete data/image inventory. Fixture inference is documented in README.
- Full same-dataset PHP 5.6/Laravel response parity and latency, including tied rankings with the legacy unspecified tie-break.
- Docker image build, runtime image size and MySQL 8.4 validation on this workstation: no Docker daemon is available. The Dockerfile is release/multi-stage/non-root; CI includes an actual build and read-only-container smoke test, with image-size output. CI status is separate from these local results.
- Live Google Custom Search credentials/response; provider logic is tested with a local HTTP fixture. Actual Disqus thread content/network response is unverified; identifiers and embedding configuration are preserved.
- Cloud Run startup/cold starts, memory, billable instance time, costs, cloud-region DB time, or a running batched Laravel performance baseline. No cloud credentials were available; nothing was deployed.

Use the exact local/build/deployment/comparison commands in [README.md](README.md). A PHP-versus-Rust comparison will combine query batching, removed synchronous Disqus, framework/container behavior and Rust until equivalent SQL changes are made in PHP. The local numbers do not estimate Cloud Run savings.
