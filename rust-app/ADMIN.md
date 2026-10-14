# Rust administration

`ADMIN_ENABLED=1` enables the existing `/admin` routes for current users whose role is exactly `Admin`. Imported accounts keep their passwords. Guests redirect to login and other roles redirect home. Role and password validity are checked again inside each write transaction. The normal website writer retains its existing permissions; `TURSO_ADMIN_TOKEN` is a separate table-scoped Secret Manager credential.

The dashboard, user/guest/converted lists, user creation/edit/deletion/submissions, game CRUD, weapon CRUD and compatibility, attachment CRUD and image removal, loadout deletion, vote/loadout statistics, CSV matrix imports, advertisement toggle and stylesheet publication are supported. Private games can be previewed only by authenticated admins. The disabled original beta route remains disabled. Existing Bootstrap, dashboard templates, JavaScript and public assets are retained.

Mutations require CSRF-protected POST, PUT, PATCH or DELETE. The three legacy GET actions that changed data (ads, CSS, image removal) now show a confirmation form. Validation rejects malformed inputs before publication, and database failures return a generic error without exposing SQL or records.

Uploads are immutable, content-addressed PNG/JPEG/GIF files stored in private Turso BLOB chunks of at most 512 KiB, with verified digests and generated thumbnails. Only image paths are publicly served. Full originals remain durable across Cloud Run instances. Each image is limited to 8 MiB and 4096 pixels per dimension; aggregate media is limited to 2 GiB. Deleted records do not immediately delete immutable media, so recovery links stay valid; media cleanup is manual. Cloud Run filesystem storage is never authoritative.

The CSV format matches the original matrix: names, types, minimum and maximum attachments in the first four rows, a separator row, then attachment name/slot/compatibility flags. Imports validate the complete CSV, use bounded batches in one transaction, retain existing metadata and reuse canonical MySQL comparison keys on retries. Limits: 2 MiB, 200 weapon columns, 10,000 rows and 100,000 compatibility flags. An expired or interrupted transaction rolls back; rerun the same CSV safely.

Compile CSS publishes the packaged stylesheet built from the original LESS sources. It does not modify the original production asset. Rebuild it after a Rust source change with `node tools/compile_admin_css.mjs` from `rust-app` (requires the pinned LESS compiler described in that script). Runtime images contain neither Node nor a LESS compiler.

Source refresh remains manual and never writes back to MySQL. Existing three-way reconciliation retains Rust-only rows, edits and deletions, records source conflicts privately and uses the website write epoch to prevent publication over concurrent edits. New numeric IDs are allocated above 2^32. Game/user natural-key edits update both comparison keys and raw child references atomically. Rust settings and images are outside the imported application generations and survive refreshes.

Run `migration/admin_schema.py`'s `initialize` with the protected Rust importer once before enabling admin; never use a source connection. The deployment operator creates the scoped token and metadata. The website cannot create schemas or publish migration generations.

Validation uses disposable MySQL and official libSQL fixtures. `tests/accounts.rs` covers admin authorization, current-role revocation, CSRF, private previews, CRUD, renamed relationships, passwords, image round trips, CSV retries and malformed-input rollback. `tools/browser_accounts.mjs` exercises rendered admin forms alongside public account and vote flows. No tests write to production or imported real accounts.
