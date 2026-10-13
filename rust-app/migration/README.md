# Rust Turso migration, synchronization and rollback

Scope: `rust`, `game-loadouts-rust`, project `game-loadouts`, europe-west1,
https://rust.gameloadouts.com. Original `master`, both original `game-loadouts`
services, root/www routing, VPS/MySQL data and `hand-forged` are protected.

The private `gameloadouts-rust` database uses libSQL and the official Rust
`libsql` 0.10 remote/TLS client. Group `rust-websites` is in aws-eu-west-1,
Ireland, near the existing Rust Cloud Run service. It shares the Free account
with AT's database. Overages remain disabled; the database cap is 1 GB.
No embedded replica or Cloud Run filesystem is authoritative.

The website runtime identity `rust-turso-web` uses `rust-turso-read` for browsing
and a separate fine-grained `rust-turso-write` token for existing account,
password, submission and vote operations, sequence allocation and write epochs.
It cannot change source data or schema. It retains existing cookie, mail, CAPTCHA,
assets, routes and query batching. Scoped tokens use the server-supported `data_add` action; `data_insert` is rejected by the endpoint. Cascading deletes are restricted to the same account/loadout/link tables, while catalog and schema writes are denied.

Source DB environment variables are removed
only from Rust. The `rust-turso-sync` identity accesses `rust-turso-source` and
`rust-turso-import`. Only these Rust secrets are created or updated.

## Full import and recovery snapshots

`source_snapshot.py --config /protected/mysql-source.json --export
--lock-nontransactional --output /protected/snapshot` uses read-only sessions,
a consistent InnoDB snapshot, and read locks on the three small MyISAM tables
(`betas`, `migrations`, `password_reminders`) while those 111 records are copied.
It imposes a five-second budget and 1,000-row limit on that special case. The
measured lock was about 0.6 seconds from the operator workstation. There is no
global lock, schema mutation, migration, or source DML. A changed nontransactional
schema or unsupported declarations fail closed for inspection.

`snapshot_sqlite.py --snapshot /protected/snapshot --database gameloadouts-rust
--token-file /protected/import.token --generation 1 --report /protected/report.json
--publish` explicitly converts the inspected live schema, preserves IDs, fields,
nulls, exact values, timestamps and password hashes, imports bounded batches and
validates every normalized row, table count, foreign key, uniqueness and integrity.
The completed 0700 recovery snapshot directory remains outside Git. Rerunning
an identical import validates and reuses it without duplicate rows; active or
unrelated data is not overwritten. Reports in this directory contain counts and
validation outcomes, never records or credentials.

Legacy utf8mb3_unicode_ci comparisons and PAD SPACE uniqueness use exact Unicode
4 primary-weight shadow keys, while raw strings remain unchanged. Foreign keys
and indexes use matching comparison keys. Case/accent-aware LIKE uses character
weights, preserving MySQL's distinct wildcard and expansion semantics; bounded
weapon search remains one query. Unknown source types or collations fail closed.
Generated numeric source IDs remain intact. Rust-only numeric IDs begin at
4,294,967,296, above the unsigned 32-bit source range, allocated transactionally.

## Ownership and refresh

The original MySQL database owns incoming source records. Rust independently owns
its new accounts, loadouts, votes and credential changes. There is no writeback.
The private `_source_baseline` stores exact prior source rows. A three-way merge
applies source changes to untouched fields and preserves destination edits,
local deletions and independent inserts. Destination edits win overlapping
conflicts; incoming and destination rows are archived in `_sync_conflicts` for
private review. Source-deleted parents needed by local references are retained.
Original authentication hashes are imported without rehashing; later Rust-only
password changes remain destination-owned. Shared email/username collisions use
the inspected collation and are preserved rather than silently discarded.

Each request pins a published read snapshot. Writers use an immediate transaction
and increment `_rust_write_epoch`; source planning verifies that epoch before
publishing, retrying planning up to three times if a Rust write intervenes.
Deltas publish atomically with the baseline and metadata. Changed rows are checked
in bounded network batches before commit. Large changes use fully validated
private staging and an atomic copy into stable application tables, avoiding
partial datasets and preserving the writer token's table permissions. Failed
validation or transport retains the prior active dataset; retries reconcile
against committed metadata. Import tooling never automatically replays uncertain
writes. A 15-minute database lease prevents overlapping jobs.

Job `game-loadouts-rust-sync` uses `Dockerfile.rust-sync`, one task, 600-second
maximum, and two task retries. Scheduler `game-loadouts-rust-sync-schedule`
runs at minutes 17 and 47 UTC through a dedicated Rust scheduler identity with
job-only Run Invoker. The 30-minute interval keeps edits reasonably fresh without
constant live-source scans: a source export measured about six seconds and an
unchanged run used 23 Turso rows read and 4 written. Full refreshes are reserved
for changes too large for the bounded delta. Sync logs contain counts, duration,
freshness and safe error categories; private conflicts never enter logs. The
last 2,000 run reports remain in Turso. Source schema changes require conversion
review before publication. Plan usage must be monitored as actual future edit
rates and public traffic vary; overages remain disabled.

## Validation and safe rollback

Python controlled tests cover inserts, updates, deletions, source parent
replacement, local reference retention, independent edits, conflicts, failed
publication, retry, overlapping leases, concurrent writer epochs, large staged
refresh and idempotent imports. They run against SQLite and a separate official
libSQL server. Rust fixture tests preserve account, security, mail, CAPTCHA,
submission, vote, pagination, search and query-budget assertions. Test source
hosts and database names are allowlisted disposable fixtures, never production.

Verified Turso baseline revision: `game-loadouts-rust-00011-xxj`.

Pause only the Rust scheduler before data recovery. Keep a verified prior
Turso-backed Rust revision for application rollback:
`gcloud run services update-traffic game-loadouts-rust --project game-loadouts
--region europe-west1 --to-revisions=PREVIOUS_TURSO_REVISION=100`.

Do not route interactive traffic to legacy `game-loadouts-rust-00008-s8x`:
that preserved old revision uses the original MySQL writer and violates this
migration's post-deployment isolation. A rollback must use a retained Turso
revision/image or redeploy the verified Turso commit with existing Rust secrets.
For data restoration, use protected recovery snapshots plus preserved Rust-only
rows/conflicts and baselines. Never overwrite local edits with a raw source import.
Never change production, original jobs, databases, schemas, DNS or Hand-Forged.
