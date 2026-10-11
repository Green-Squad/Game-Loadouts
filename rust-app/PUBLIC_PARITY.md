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

No schema migration is needed. The app uses the existing Laravel `users`, `password_reminders`, loadout and pivot tables. `--check-public-schema` only reads their metadata and validates the required columns and InnoDB engines. Never apply the disposable `fixtures/schema.sql` or `fixtures/data.sql` to production.

Set `INTERACTIVE_ENABLED=1` and `ACCOUNT_BASE_URL=https://rust.gameloadouts.com` on the Rust service. The copied `KEY`, database, CAPTCHA and SMTP credentials are reused. Browsing keeps its separate read-only pool. An optional `DB_WRITE_USERNAME` / `DB_WRITE_PASSWORD` pair can override the existing database identity; no override is required.

| Setting | Purpose |
|---|---|
| `KEY` | Existing application secret (at least 32 bytes); an origin- and purpose-specific AES-256-GCM session key is derived from it. `RUST_SESSION_KEY` optionally overrides it. A `base64:` prefix is supported. Keep the same secret across instances/revisions. |
| `ACCOUNT_BASE_URL` | HTTPS origin used for encrypted-cookie binding, email links and the expected CAPTCHA hostname. |
| `RECAPTCHA_PRIVATE_KEY`, `RECAPTCHA_PUBLIC_KEY` | Existing secret and optional site-key override; server verifies with Google. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Existing SMTP credentials. Defaults remain smtp.zoho.com:465 with SSL and Game Loadouts <mail@gameloadouts.com>. |
| `DISQUS_SECRET_KEY` | Existing signed member SSO. |
| `ADS_ENABLED`, `GA4_MEASUREMENT_ID` | Optional existing AdSense unit and a current Analytics measurement ID. |

The existing DB identity needs SELECT/INSERT/UPDATE on users and loadouts, DELETE on guest users, SELECT/INSERT/DELETE on loadout_user and password_reminders, and SELECT/INSERT on attachment_loadout. No DDL privileges are needed. Existing content rows are locked to serialize concurrent changes.

## Sessions and token compatibility

Session state lives in authenticated, encrypted, versioned cookies with random 96-bit nonces. Cookies are host-only, HttpOnly, Secure and SameSite=Lax. Login/confirmation/reset rotates the session and CSRF tokens. Sessions expire after two hours or 30 days when remembered. They work across instances without local files, server-side session rows or migrations. Authenticated requests check the shared user's password and remember_token fingerprint, so Laravel password changes invalidate Rust sessions.

Logout changes the existing users.remember_token to revoke all of that account's Rust sessions, including a copied pre-logout cookie. This also invalidates Laravel remember-me cookies; active Laravel file sessions remain managed by Laravel. Clearing just one stolen/replayed Rust session independently would require shared server-side revocation storage.

Confirmation tokens use users.confirm_token and the existing confirmed sentinel `1`. New opaque tokens include an issue time and expire after 24 hours on Rust; existing Laravel confirmation links remain accepted. Laravel can consume new tokens in its unchanged confirmation handler, which retains its original expiry behavior. Resending replaces the token, with a 60-second cooldown.

Password reset tokens use the exact Laravel 4.1 password_reminders(email,token,created_at) format: raw random tokens with a 60-minute lifetime. Rust accepts existing Laravel-issued tokens and consumes reminder rows on successful reset. Issuance locks the user, replaces prior reminders, applies a 60-second cooldown, and rolls back if SMTP fails. Password changes update the shared bcrypt password and invalidate old Rust cookies; resetting an unconfirmed account does not confirm it.

Guest identity is derived from the random ID inside the encrypted anonymous cookie, so concurrent first-time guest votes share one identity. Registration transfers guest data in a transaction. Real production mail and CAPTCHA integrations remain enabled, with isolated substitutes constructed only in tests.

## Validation

`tests/accounts.rs` creates a separate `loadouts_accounts_fixture` database on loopback, uses an SMTP receiver and CAPTCHA protocol stub, and deletes only that disposable fixture. It checks session rotation/hashed storage, CSRF, bcrypt compatibility, lockouts, duplicate/concurrent votes, route relationships, invalid attachment slots/counts/IDs, concurrent combination deduplication, guest conversion, confirmation, password expiry/email binding/single use and session revocation. All production integrations remain real and configurable; test stubs are constructed only by the test.

With `RUN_BROWSER_TESTS=1`, Chromium exercises the rendered login, account, selection, submission, voting, submissions and logout forms and captures desktop/mobile screenshots. The existing read-only browser and query-budget tests still run against their original separate fixture. CI builds the production Docker image and checks non-root/read-only runtime, static media, graceful shutdown and browsing.

CI checks formatting, warnings-as-errors lint, unit/database tests, Chromium account flows, and the production Docker image against disposable fixtures. Cookie tests cover encryption, randomized nonces, tampering, expiry, key/origin separation and cross-instance compatibility. Integration tests use only the Laravel schema and exercise legacy token compatibility, guest conversion/concurrency, replay after logout and password-change invalidation. The historical measurements in `VALIDATION.md` describe the earlier browsing experiment, not the current account flows.
