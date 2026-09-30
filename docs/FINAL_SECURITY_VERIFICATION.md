# FINAL SECURITY VERIFICATION — Permetheon Website V3 PHP

- **Date:** 2026-09-30
- **Scope:** the carried-over security findings (rate limiting,
  `/api/admin/auth/refresh`, `APP_DEBUG`, authentication/authorization,
  meeting/calendar integrity, data protection) verified against the new
  independent React + Vite / Laravel / MySQL project before any data import or
  production deployment.
- **Method:** live HTTP probes against running instances, direct database
  inspection of state (counters, sessions, orphans, cookie-bearing rows), the
  full Pest suite, and the isolated E2E suites. Evidence is quoted inline.
- **Instances used:** `:8000` dev (`APP_DEBUG=true`), `:8020` production-like
  (`APP_ENV=production`, `APP_DEBUG=false`), `:8010` config variant with
  `TRUSTED_PROXIES=127.0.0.1` for the proxy positive-path test.

**Verdict: all security-critical items PASS.** One vulnerability was found and
fixed during verification (rate-limit key spoofing, item 1c) and re-verified.
Nothing else was modified.

---

## 1. Rate limiting

| Check | Result | Evidence |
| --- | --- | --- |
| `CACHE_STORE=database` active | **PASS** | Runtime probe: `cache.default: database`; DB: `permetheon` (mysql); `cache`/`cache_locks` tables present; counter rows observed persisted in `cache` |
| Inquiry requests reach 429 | **PASS** | `201 201 201 201 429 429 429` (6 rapid posts, max=5); availability endpoint `200×5 → 429` |
| Login requests reach 429 | **PASS** | 11 wrong-password attempts → `401×9`, then `429 429` (10/10min; one earlier hit consumed the 10th slot) |
| Rate-limit state is durable | **PASS** | Counters survived an unclean MariaDB process restart (429 still served immediately afterwards) |
| Limits cannot be bypassed via client-controlled headers | **PASS (after fix)** | See below — original behavior was vulnerable; fixed and re-verified |

### 1c. Header spoofing — vulnerability found and fixed

- **Original behavior:** the limiter keyed on `X-Forwarded-For` first. A
  direct client could rotate that header per request and never hit 429:
  three probes with different spoofed XFF values all returned `200`.
- **Fix (one behavioral change, made because this is a vulnerability):**
  `AppServiceProvider` now resolves the rate-limit key deny-by-default — the
  TCP peer is authoritative; `X-Forwarded-For` counts only when the peer is
  listed in the new `TRUSTED_PROXIES` env var (IP or CIDR), and then the
  rightmost valid untrusted entry of the chain is used.
- **Re-verification (untrusted client, `TRUSTED_PROXIES` empty):**
  `200 200 429 429 429 429 429` with a *fixed* spoofed XFF (keyed by socket IP);
  `429 429 429` rotating XFF per request (`5.6.7.8 → 9.8.7.6 → 4.3.2.1`) — the
  bypass is closed; garbage XFF (`not-an-ip`) also lands on the socket key → 429.
- **Proxy positive path (`TRUSTED_PROXIES=127.0.0.1`):** forwarded client
  `77.1.2.3` → `200×5 then 429` on its own key, socket key independent — real
  deployments keep per-customer limiting by adding their proxy to
  `TRUSTED_PROXIES`.
- Test config note: `phpunit.xml` sets `TRUSTED_PROXIES=127.0.0.1` (test-only)
  so the suite can exercise both keying modes; full suite re-run green (94/94).

---

## 2. `/api/admin/auth/refresh`

| Check | Result | Evidence |
| --- | --- | --- |
| Authenticated refresh works | **PASS** | `POST /api/admin/auth/refresh` with session cookie → `200` JSON envelope with `admin`, `permissions`, `csrfToken` |
| Session rotation | **PASS** | Response carries `Set-Cookie: admin_session=<new>` (+ fresh `admin_csrf`); server-side session row replaced |
| Old session token invalidated | **PASS** | Old token replayed at `/api/admin/auth/session` → `401`; new token → `200` |
| Unauthenticated refresh rejected | **PASS** | No cookie → `401` `UNAUTHORIZED` JSON envelope (never a 500/HTML page) |
| Disabled admin cannot refresh | **PASS** | Disabled account with a live session row minted directly in `admin_sessions` → refresh `401`, session probe `401` (`resolveSession` refuses inactive admins) |

---

## 3. APP_DEBUG

| Check | Result | Evidence |
| --- | --- | --- |
| Development may use `APP_DEBUG=true` | **PASS** | `:8000` (`APP_ENV=local`, `APP_DEBUG=true`) serves the detailed debug error page on an unhandled error — as intended for local development only |
| Production configuration requires `APP_DEBUG=false` | **PASS** | `:8020` runtime probe with `APP_ENV=production APP_DEBUG=false` → `app.env: production`, `app.debug: false`; `.env.example` and README document that production must set `false` |
| Production-like request exposes no stack traces / internals | **PASS** | Unhandled error on `:8020` → generic `Server Error` page, HTTP 500, no trace, no paths, no exception class. (Contrast on dev showed the detailed page, proving the switch is real.) |

Note: an unhandled error currently surfaces as Laravel's HTML 500 page under
APP_DEBUG=false. It discloses no internals, but the JSON API contract would be
cleaner if it returned the JSON error envelope; that is a hardening nicety,
not an exposure, and was deliberately not changed in this verification run.

---

## 4. Authentication / authorization

| Check | Result | Evidence |
| --- | --- | --- |
| IDOR / BOLA (object-level) | **PASS** | Unknown IDs under valid auth: inquiry GET `404`, meeting PATCH `404`, block DELETE `404`; unknown-id mutations unauthenticated → `401` |
| Role / permission enforcement | **PASS** | ADMIN-role account: inquiries read `200`, meetings read `200`, availability PUT `403`, block create `403`; SUPER_ADMIN passes all |
| Session revocation (logout) | **PASS** | Logout `200` → replayed token at `/api/admin/auth/session` → `401`; refresh also revokes the prior token (`401` on replay) |
| CSRF (double-submit) | **PASS** | PATCH without `x-csrf-token` → `403 FORBIDDEN`; identical request with header → `200`; CSRF-less block create / inquiry delete / meeting PATCH → `403` (E2E suite: 24 negative cases) |
| Cookie flags | **PASS** | Production-like wire capture: `admin_session: secure; httponly; samesite=strict`; `admin_csrf: secure; samesite=strict` (readable by design for double-submit) |
| Disabled account login refused | **PASS** | Correct credentials, `is_active=0` → `401` generic `Invalid email or password.` (no enumeration) |
| Account lockout | **PASS** | 5 failures lock the account (DB-backed); covered by Pest G3 test, green in the final run |

---

## 5. Meeting / calendar

| Check | Result | Evidence |
| --- | --- | --- |
| Double-booking race | **PASS** | Same slot by a second customer → `409 SLOT_CONFLICT`, loser inquiry rolled back (atomic); DB `unique_slot_guard` + `lockForUpdate` (Pest race test + E2E green) |
| Adjacent slots | **PASS** | 10:00 and 10:30 both book (`201`), both shown `BOOKED` in the calendar |
| Overlapping / off-grid slots | **PASS** | Off-grid 10:15 → `409` (`outside working hours`); a held slot re-booked by another customer → `409 SLOT_CONFLICT`; a 30-min meeting cannot overlap its neighbor (grid + guard) |
| Cancellation | **PASS** | PATCH → `CANCELLED` preserves history; terminal transition `CANCELLED → BOOKED` → `422`; freed slot re-bookable (`201`) with exactly one winner |
| Deletion policy | **PASS** | `DELETE /api/admin/inquiries/{id}` with a BOOKED meeting → `200`; slot freed |
| Inquiry/meeting relationship (no orphans) | **PASS** | SQL probe after deletion: `orphaned_meetings = 0` (`meetings LEFT JOIN business_inquiries`) |
| UTC handling | **PASS** | Calendar/timezone reports `UTC`; wire instants strict `Y-m-dTH:i:sZ` (e.g. `2026-10-15T10:00:00Z`); Pest timezone-neutrality suite (server-TZ relocation, offset-input collision, no geographic zone anywhere) green |

---

## 6. Data protection

| Check | Result | Evidence |
| --- | --- | --- |
| No production database used by tests | **PASS** | `phpunit.xml` pins `DB_CONNECTION=mysql`, `DB_DATABASE=permetheon_test` (distinct from `permetheon`); zero SQLite references |
| Test/E2E databases isolated | **PASS** | Post-run SQL: `permetheon_test` business rows `0/0/0/0` (RefreshDatabase rollback); `permetheon` contains zero Pest fixtures (query for test names: 0) |
| E2E isolation | **PASS** | Launcher creates `permetheon_e2e` (fresh migrate+seed), raises the rate limit, and **drops the database** on `--stop`; verified end-to-end (`E2E_BASE=:3102`, 87 checks) |
| `migrate:fresh` cannot hit production via the documented workflow | **PASS (with residual risk documented)** | The README/E2E workflow never invokes `migrate:fresh` against `permetheon`: phpunit targets `permetheon_test` via `phpunit.xml`, the E2E launcher exports `DB_DATABASE=permetheon_e2e` before migrating. However `php artisan migrate:fresh --force` run manually inside `backend/` **would** target whatever `.env` says — that was the mechanism of the 2026-09-25 incident (see `SECURITY_FINDINGS_CARRYOVER.md`). Mitigations here: the two databases are separate named schemas, tests never read `.env`'s `DB_DATABASE`, and the E2E workflow drops its throwaway DB. Operational rule that must be kept: run destructive artisan commands only through the launcher/phpunit entry points, never ad-hoc against `permetheon`. |

---

## Result summary

- **PASS:** all security-critical items in sections 1–6.
- **FAIL → FIXED → PASS:** rate-limit key spoofing via `X-Forwarded-For`
  (item 1c) — fixed in `AppServiceProvider` with a deny-by-default
  `TRUSTED_PROXIES` model; re-verified live; full Pest suite re-run
  **94/94 (1,591 assertions)** after the change.
- **NOT TESTED:** none within the requested scope. (Out of scope here and not
  exercised: TLS/certificate handling, rate limiting of the SPA static-host
  routes, mail-delivery flows — the mailer is `log` by design.)

## Changes made during this verification

1. `backend/app/Providers/AppServiceProvider.php` — trusted-proxy-aware,
   deny-by-default rate-limit key resolution (vulnerability fix; the only
   application behavior change).
2. `backend/phpunit.xml` — test-only `TRUSTED_PROXIES=127.0.0.1` so the suite
   exercises both keying modes.
3. Probe artifacts (temporary helper scripts, probe admin/session/limiter
   rows, variant server instances) — all removed; application database left
   empty and clean. Verification of cleanup included in the evidence above.
