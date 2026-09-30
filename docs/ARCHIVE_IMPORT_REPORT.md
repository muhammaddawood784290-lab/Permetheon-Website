# ARCHIVE IMPORT REPORT — Next-era data/permetheon.db → MySQL

- **Date:** 2026-09-30
- **Authorized by:** the project owner (this import closes the open question
  from the 2026-09-25 data-loss incident — see
  `SECURITY_FINDINGS_CARRYOVER.md`).
- **Result: SUCCESS — 5/5 archive rows imported, verified, zero data loss,
  original archive untouched.**
- **Scope: the local development database** (`permetheon` on 127.0.0.1). The
  production MySQL database on Hostinger does not exist yet; when it is
  provisioned, either repeat this import there (the archive file is
  preserved) or restore this database from the backup dump after migration —
  never run destructive artisan commands against it (§30 rules).

## 1. Source

| Item | Value |
| --- | --- |
| File | `D:\Permetheon main website V 3\data\permetheon.db` (SQLite, Next-era archive) |
| Size / SHA-1 before | 65,536 bytes · `c04007e1e06ce372da9fd5fc22927f2190165454` |
| Size / SHA-1 after | **identical** — the original was never opened for writing; all reads went through a byte copy opened with `SQLITE_OPEN_READONLY` |
| Contents | `business_inquiries` **5 rows**, `admin_users` 1 row (old scrypt hash), `admin_sessions` 5 (expired), `inquiry_notes` 0 |

## 2. Target

| Item | Value |
| --- | --- |
| Database | `permetheon` (MySQL/MariaDB 10.4.32, XAMPP, 127.0.0.1) |
| Pre-state | **empty** — 0 inquiries, 0 meetings, 0 admins (verified before import) |
| Table | `business_inquiries` (Laravel migration `2026_09_24_000001`, named CHECK constraints) |
| Backup taken first | `backups/permetheon-pre-archive-import-20260930-130527.sql` (mysqldump, `--add-drop-database`) |

## 3. Field mapping

| Archive column | New column | Transformation |
| --- | --- | --- |
| `id` | `id` | **preserved exactly** (UUID PKs; safe — target was empty) |
| `name` | `name` | verbatim |
| `company` | `company` | `''` → `NULL` (new schema treats empty as null) |
| `email` | `email` | verbatim |
| `contact_number` | `contact_number` | verbatim (`''` is valid; E.164 values pass the CHECK) |
| `project_type` | `project_type` | verbatim (within the new CHECK enum) |
| `budget` / `timeline` | `budget` / `timeline` | verbatim, `''` → `NULL` |
| `message` | `message` | verbatim |
| `status` / `priority` | `status` / `priority` | verbatim (within the new CHECK enums) |
| `created_at` / `updated_at` | `created_at` / `updated_at` | ISO 8601 UTC wire (`…T01:56:34.344Z`) → storage format `Y-m-d H:i:s.v` via explicit UTC `DateTimeImmutable` conversion (process-TZ independent) |
| `admin_users` (scrypt) | — | **not imported** — old scrypt hashes are incompatible with the new bcrypt scheme and no admin was requested; recreate via bootstrap or seeding |

Every row was validated against the new schema's CHECK constraints
(lengths, E.164 shape, all three enums) **before** any write; the import ran
as a single transaction (all-or-nothing) and was verified by re-query.

## 4. Imported rows

| # | id (prefix) | name | email | type | contact | created (UTC) | status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `50a81b11…` | Jhon Doe | jhondoe@xyz.com | Custom Digital Product | (none) | 2026-09-22 01:56:34.344 | NEW/MEDIUM |
| 2 | `3a5cf281…` | Moin Udden | moin@example.com | Booking / Reservation System | +12025550147 | 2026-09-24 02:29:55.969 | NEW/MEDIUM |
| 3 | `8fd228a6…` | Moin Udden | `phone-probe-2x7eaqqp32b@example.com` | Booking / Reservation System | +12025550147 | 2026-09-24 02:29:56.008 | NEW/MEDIUM |
| 4 | `3ca28d89…` | Moin Udden | `phone-probe-iq5r2pfckgp@example.com` | Booking / Reservation System | +12025550147 | 2026-09-24 02:29:56.018 | NEW/MEDIUM |
| 5 | `e0ac04f0…` | Moin Udden | `phone-probe-9jc8onu76zf@example.com` | Booking / Reservation System | +12025550147 | 2026-09-24 02:29:56.028 | NEW/MEDIUM |

⚠️ **Triage note:** rows 3–5 are clearly automated test/probe submissions
(`phone-probe-*` emails, created 10–20 ms apart during the 2026-09-24
E2E phone-validation run). All 5 rows were imported as instructed; the probe
rows can be deleted in the admin panel (`DELETE /api/admin/inquiries/{id}`)
or left for reference. Row 1 is the only genuinely external inquiry; row 2 is
the owner's original manual test submission.

## 5. Verification

- Post-import count query: **5** rows in `business_inquiries`; 0 notes; 0 meetings.
- Timestamps re-read from MySQL match the archive's UTC instants to the
  millisecond (no TZ drift).
- Original archive hash re-verified **unchanged** after the import.
- No admin account was created or modified; `admin_users` remains empty
  (bootstrap login still available on first run).

## 6. Safety record

- Pre-import mysqldump backup retained in `backups/`.
- Dry-run executed first (mapping printed, zero writes), then `--execute`.
- Single-transaction import with automatic rollback on any error.
- All temporary scripts (`tmp-archive-inspect.php`, `tmp-archive-import.php`,
  `tmp-archive-copy.db` + WAL/SHM sidecars) deleted after verification.
- The old workspace was touched **read-only** throughout.
