# Public feature parity

The Rust service shares the Laravel users, loadouts, attachments and vote pivots. Admin routes are intentionally excluded. Current production media is copied from the master branch; this PR targets `rust` only.

## Features

- Email/username login with existing Laravel `$2y$` bcrypt hashes, five-attempt/15-minute lockout, remembered login and logout.
- Account registration, confirmation and resend; existing confirmation links are accepted except the confirmed sentinel `1`. Guest history moves to the registered account in one transaction.
- Password updates requiring the current password; email-bound, expiring, single-use password reset links. Reset does not bypass email confirmation.
- Member voting and removal; guest additions require verified reCAPTCHA success and the configured hostname. Removal affects only the current identity's vote.
- Attachment selection, count/slot/membership validation and Advanced Warfare Other1/Other2 behavior. Existing combinations are voted for rather than recreated.
- Weapon locks serialize submissions and votes even when legacy vote pivots have no unique constraint. Requests use bound parameters throughout.
- User submissions ordered by the user's latest pivot activity, with existing vote counts and images.
- Existing Disqus threads, asynchronous counts and signed member SSO; copied Google search configuration; optional AdSense and GA4 integration.
- Existing browsing, statistics, encoded URLs, sitemap and responsive navigation, plus robots.txt, canonical links and noindex account pages.

## Activation

Writes are disabled by default. Browse connections still execute `SET SESSION TRANSACTION READ ONLY`. No migration runs as part of web-server startup.

1. Build and validate the Rust image against disposable fixtures.
2. Use a schema owner to run the image with `--migrate-public-accounts`. It checks the required legacy columns and InnoDB engines, creates only `rust_sessions` and `rust_account_tokens`, and verifies them. Alternatively execute `migrations/001_public_accounts.sql` directly on the selected database. Never apply `fixtures/schema.sql` or `fixtures/data.sql` to production.
3. Configure the writer and public settings on the Rust service, then set `INTERACTIVE_ENABLED=1`.

| Setting | Purpose |
|---|---|
| `DB_WRITE_USERNAME`, `DB_WRITE_PASSWORD` | Writer identity; host/database/TLS options come from the existing DB configuration. Credentials stay in service settings or secrets. |
| `ACCOUNT_BASE_URL` | Required HTTPS origin for email links and the expected CAPTCHA hostname, e.g. `https://rust.gameloadouts.com`. Canonical content/Disqus URLs remain controlled separately by `PUBLIC_BASE_URL`. |
| `RECAPTCHA_PRIVATE_KEY`, `RECAPTCHA_PUBLIC_KEY` | Existing secret and optional site key override. Server calls Google's verify endpoint; production has no bypass. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Existing SMTP credentials. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_FROM` | Defaults match Laravel: smtp.zoho.com, 465, ssl, Game Loadouts <mail@gameloadouts.com>. `tls` selects STARTTLS. |
| `DISQUS_ENABLED`, `DISQUS_SECRET_KEY` | Async comments/counts and the existing signed SSO identity. |
| `ADS_ENABLED` | Set 1 to load the existing AdSense unit. |
| `GA4_MEASUREMENT_ID` | Optional current Analytics measurement ID. The old UA ID needs replacement to collect analytics. |

The writer needs SELECT on existing content/account tables; INSERT/UPDATE/DELETE on users and loadout_user; INSERT/UPDATE on loadouts; INSERT on attachment_loadout; and SELECT/INSERT/UPDATE/DELETE on the two Rust tables. `weapons` rows are locked for serialization; the database must support SELECT FOR UPDATE. The web writer does not need DDL privileges. Prefer a scoped writer over using the schema-owner credentials.

## Sessions and email safety

Cookies are host-only, HttpOnly, Secure and SameSite=Lax. Random 256-bit session identifiers are hashed in MySQL; CSRF tokens are checked with constant-time equality. Login/confirmation/reset rotates the session. Sessions last two hours, or 30 days when remembered. A password fingerprint also invalidates sessions after a password change through Laravel. Rust cookies are separate from Laravel cookies.

The shared session store works across Cloud Run instances without writable filesystem storage. Expired sessions/tokens are removed in bounded batches during a sample of new-session requests. Password hashing runs on bounded blocking workers.

Confirmation/reset tokens are hashed, expire and are consumed once. Mail requests require CAPTCHA and have a per-account cooldown. Registration rolls back when SMTP delivery fails; existing pending accounts can resend confirmation. No credentials, raw SQL errors, request paths or submitted form data are logged.

## Validation

`tests/accounts.rs` creates a separate `loadouts_accounts_fixture` database on loopback, uses an SMTP receiver and CAPTCHA protocol stub, and deletes only that disposable fixture. It checks session rotation/hashed storage, CSRF, bcrypt compatibility, lockouts, duplicate/concurrent votes, route relationships, invalid attachment slots/counts/IDs, concurrent combination deduplication, guest conversion, confirmation, password expiry/email binding/single use and session revocation. All production integrations remain real and configurable; test stubs are constructed only by the test.

With `RUN_BROWSER_TESTS=1`, Chromium exercises the rendered login, account, selection, submission, voting, submissions and logout forms and captures desktop/mobile screenshots. The existing read-only browser and query-budget tests still run against their original separate fixture. CI builds the production Docker image and checks non-root/read-only runtime, static media, graceful shutdown and browsing.

[CI run 38170863320](https://github.com/Green-Squad/Game-Loadouts/actions/runs/38170863320) passed for commit `1df59288cf8bcfb3cf8ad0adbd4f2e6ac722715e`: formatting, warnings-as-errors lint, all unit/database tests, interactive Chromium flows, Docker build and read-only container browser/runtime checks. The workflow also checks the explicit migration command and account-enabled container startup with synthetic settings. Live activation still requires the operator migration and writer configuration described above. The historical measurements in `VALIDATION.md` describe the earlier browsing experiment and do not establish performance or memory for the new account flows.
