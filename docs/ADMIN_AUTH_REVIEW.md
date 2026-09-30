# Admin Auth Security Review — Pre-Deployment

**Scope:** the admin authentication/authorization stack (`AdminAuthService`,
`AuthenticateAdmin`, `CsrfDoubleSubmit`, `RequirePermission`, rate limiters,
session model, cookie policy, frontend admin client), reviewed 2026-09-30 with
live probes against the :8000 build. Findings reference the mechanisms that
carry over from `docs/SECURITY_FINDINGS_CARRYOVER.md`.

---

## Verdict

The core design is **sound and above-average for this class of app**: opaque
hashed session tokens, replay-safe idle expiry, session rotation, server-side
permission gate, one-time bootstrap, generic errors with no enumeration
signal. The risks that matter for real deployment are **configuration
deployment gaps** (env posture, trusted proxies, HTTPS cookie flag), not
design flaws.

---

## Verified strengths (probed live)

| Mechanism | Evidence |
|---|---|
| Session tokens are 32 random bytes, stored only as SHA-256 | `admin_sessions.id = hash('sha256', token)`; a DB leak yields no usable credentials |
| Idle expiry **revokes** (G2) | `resolveSession` sets `revoked_at` on an idle-expired hit — no replay of stale tokens |
| Absolute 24 h lifetime | `absolute_expires_at` enforced independently of activity |
| Refresh rotates | `POST /auth/refresh` revokes the current token, then mints the new pair in the same response |
| Cookie flags (dev probe) | `admin_session: HttpOnly; SameSite=Strict`; CSRF cookie readable by design, SameSite=Strict |
| CSRF double-submit with `hash_equals` | mutating routes 403 without the header (probed: `PATCH` → 403) |
| Lockout + rate limit | 5 failed passwords → 15-min account lock; login throttle 10/10 min per IP (probed: 401×8 then 429) |
| No account enumeration | unknown email vs wrong-password: identical status (401) and indistinguishable timing (~0.24–0.29 s both) |
| One-time bootstrap (G5) | marker file `storage/app/admin-bootstrap.completed`; password wiped after mint; `config:cache`-safe |
| Authorization is server-side | every admin route carries `can:` via `RequirePermission`; roles constrained by DB CHECK `chk_admins_role` |
| Proxy spoofing denied by default | X-Forwarded-For counts only from `TRUSTED_PROXIES` for the inquiry limiter |
| Contract hygiene | JSON envelopes everywhere on `api/*` (no HTML leaks), 401 vs 403 semantics correct, oversized-body rejection |

---

## Findings

### F1 — HIGH (deployment config): rate limiting collapses to one shared bucket behind a reverse proxy

> **Status 2026-09-30: CODE APPLIED.** The `login` limiter now keys by
> `clientKey($request)` (same deny-by-default XFF rule as `inquiry` —
> `AppServiceProvider::boot()`), `TRUSTED_PROXIES` is documented with
> examples in `backend/.env.example`, and `LoginThrottleProxyTest` pins the
> behavior (per-client buckets, spoof denial, unset-fallback, rightmost-entry
> keying). Setting the actual `TRUSTED_PROXIES` value remains a deploy-time
> step.

`inquiry` keys by `clientKey()` (XFF-aware, trusted proxies only) — correct.
But **`login` keys by `$request->ip()`** (raw socket peer), and
`TRUSTED_PROXIES` is **empty by default**. Behind Hostinger's load balancer /
Cloudflare / nginx:

- `inquiry` (5 per 10 min): every visitor shares the LB's IP → **the public
  contact form breaks globally after 5 submissions** (and one attacker can
  keep it broken).
- `login` (10 per 10 min): same collapse — one attacker can lock out all
  admin logins, and the limit provides no per-client protection.

**Fix (required before deploy):** set `TRUSTED_PROXIES` to the proxy ranges
(e.g. `TRUSTED_PROXIES=173.245.48.0/20,...`) and make the `login` limiter use
`clientKey($request)` for parity with `inquiry`.

### F2 — HIGH (deployment config): `Secure` cookie flag is env-conditional and the env is currently `local`

> **Status 2026-09-30: CODE APPLIED.** `AuthController::secureCookies()` now
> derives the flag from the ACTUAL serving scheme — request is HTTPS, OR
> `APP_URL` starts with `https://` (TLS terminated on a proxy). `APP_ENV` is
> irrelevant. The same derivation governs login, refresh, and the logout
> clearing pair, which previously hardcoded `secure=false` (a browser only
> replaces a cookie whose name+path+secure match, so a Secure=false clear
> under a Secure login left the session stranded). Pinned by
> `tests/Feature/AdminCookieSecureFlagTest.php`. REMAINING DEPLOY STEP:
> `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` in the
> production env (the env half of this finding).

**Fix (required before deploy):** `APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL=https://…`. Consider hardening the code: derive `Secure` from the
request scheme / `APP_URL` scheme rather than env name, so a misconfigured env
cannot silently drop the flag. Logout cookie clearing currently hardcodes
`secure=false` too.

### F3 — MEDIUM: CSRF token is not bound to the session

> **Status 2026-09-30: RESOLVED.** The admin CSRF token is now
> `<nonce>.<HMAC-SHA256(nonce, key = sha256(session token))>` — issued at
> login/refresh (`App\Services\CsrfToken`), validated by `CsrfDoubleSubmit`
> against the CURRENT session's hash (resolved from the HttpOnly cookie). A
> party who can SET the CSRF cookie still cannot mint a valid tag without
> the session secret, and cross-session replay fails on the differing key.
> The pinned 403 FORBIDDEN 'Invalid CSRF token.' contract is unchanged, and
> the SPA needed no changes (it mirrors the cookie into the header, which
> the bound scheme validates). Pinned by
> `tests/Feature/CsrfSessionBindingTest.php` (tossing, cross-session replay,
> tampering, rotation).

### F4 — MEDIUM: lockout is a denial-of-service lever against named accounts

> **Status 2026-09-30: RECOVERY + VISIBILITY SHIPPED.** Lockout engagement is
> logged (`admin.lockout.engaged`) and the new `php artisan admin:unlock
> <email>` command clears a lock + failure counter on demand (also logged).
> Since F1 the per-IP throttle bounds the DoS to ONE account per attacker —
> the residual risk (named-account lockout at will) is ACCEPTED for v1.
> Post-launch candidates: per-(account, IP) keying, exponential backoff,
> 2FA/TOTP. Pinned by `PreLaunchHardeningTest`.

Account lockout (5 fails → 15 min) plus the login throttle mean an attacker
can keep `admin@permetheon.com` permanently locked at will. Standard
trade-off; combined with F1 it gets worse behind a proxy.

### F5 — MEDIUM: no audit trail of admin actions

> **Status 2026-09-30: RESOLVED (v1 scope).** Append-only `admin_audit_log`
> (actor id + denormalized email, action, resource type/id, JSON summary,
> IP, ts) written on EVERY successful admin mutation: inquiry update/delete,
> notes, meeting update/delete, availability update, block create/delete,
> admin-user create/role/delete. Failures and permission denials record
> nothing; the model refuses updates/deletes. Inspect with `php artisan
> admin:audit [--admin=] [--action=] [--limit=]`. Console UI view and
> before/after diffs are post-launch scope. Pinned by `AuditLogTest`.

Status changes, deletions, availability edits are unlogged. The `system.*`
permissions exist but there is no record of *who* did *what* — a compromise or
insider mistake is invisible.

### F6 — LOW: `admin-read` limiter is registered but never wired

> **Status 2026-09-30: RESOLVED.** `throttle:admin-read` (120/min) is attached
> to every admin GET route (`inquiries`, `inquiries/{id}`, `inquiries/stats`,
> `inquiries/{id}/notes`, `meetings`, `meetings/{id}`, `availability`, `users`,
> `blocks`). The bucket keys by the RESOLVED ADMIN id — this required an
> explicit middleware priority in `bootstrap/app.php` (the kernel's default
> list floated `ThrottleRequests` ahead of the unlisted `admin.guard`, so the
> limiter ran first and fell back to socket-IP keying with one shared bucket).
> Write routes are deliberately not governed by it; unauthenticated callers
> get the plain 401 before any limiter runs. Pinned by
> `tests/Feature/AdminReadThrottleTest.php`.

`RateLimiter::for('admin-read', …)` exists; no route references
`throttle:admin-read`. Either wire it onto the read routes or remove the dead
code.

### F7 — LOW: password policy is a bare minimum

> **Status 2026-09-30: PARTIALLY RESOLVED.** Every site that SETS a password
> (bootstrap login, admin:create, admin:reset-password, users API) now
> enforces 12+ chars via `App\Support\PasswordPolicy`; existing hashes are
> grandfathered (login verifies the hash, not the policy) — rotate at deploy.
> Composition rules and forced rotation deliberately NOT adopted (NIST
> 800-63B). TOTP remains post-launch. Pinned by `PreLaunchHardeningTest`.

Only `MIN_PASSWORD_LENGTH = 8`, no composition/rotation rules, single-factor
auth, no 2FA. For an admin surface: 12+ characters recommended; TOTP is the
highest-value hardening step available.

### F8 — LOW: sessions table grows unbounded

> **Status 2026-09-30: RESOLVED.** `php artisan admin:prune-sessions`
> (`App\Console\Commands\AdminSessionsPrune`) deletes rows that were already
> dead N days ago — revoked OR absolutely expired, measured from the moment
> the row became unusable (revocation/expiry, not last activity), so the
> retention window has consistent meaning. Live rows are never touched
> regardless of age. Options: `--days=` override, `--dry-run`. Default
> retention `SESSIONS_PRUNE_DAYS` env (30); scheduled daily at 03:20 in
> `routes/console.php` — run `php artisan schedule:work` (or cron
> `schedule:run`) in production. Verified live on the dev DB (dry-run count
> matched, synthetic dead row removed, live rows preserved). Pinned by
> `tests/Feature/AdminSessionsPruneTest.php`.

### F9 — LOW: misc hardening notes

> **Status 2026-09-30: HEADERS RESOLVED.** `SecurityHeaders` middleware
> (appended globally, web + api) stamps `X-Frame-Options: DENY`,
> `X-Content-Type-Options: nosniff`, `Referrer-Policy:
> strict-origin-when-cross-origin` and a CSP (`default-src 'self'; script-src
> 'self' 'unsafe-inline'; style-src 'self'; img-src 'self' data:; font-src
> 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self';
> base-uri 'self'`). HSTS is scheme-gated (F2 rule: HTTPS request or https
> APP_URL) with `includeSubDomains`. `'unsafe-inline'` on script-src is the
> known SSG caveat — hash-pin the two bootstrap scripts post-launch. SPA
> verified clean under the CSP (zero violations). Pinned by
> `PreLaunchHardeningTest`.

- `RejectOversizedBody` trusts the `Content-Length` header; a chunked request
  bypasses the check (PHP's `post_max_size` still bounds it — acceptable).
- No security headers on web responses: ~~HSTS, `X-Frame-Options: DENY`,
  `Content-Security-Policy`, `Referrer-Policy` are absent~~ RESOLVED (above).
- Rate-limit buckets live in the DB cache (`CACHE_STORE=database`);
  `cache:clear` wipes counters. Prefer Redis in production.
- Dev DB uses MySQL `root` — create a least-privilege app user for deploy.
- `Admin.md` holds real passwords — verified `.gitignore`d; rotate before
  production and move to a password manager.

---

## Pre-deployment checklist

1. [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
2. [ ] `TRUSTED_PROXIES=<proxy CIDRs>` (F1) + make `login` use `clientKey` — code half done (see F1 status); set the env value at deploy
3. [ ] HTTPS end-to-end; verify `Secure` appears on both cookies after deploy — cookie flag now scheme-derived (see F2 status); just confirm on the live domain
4. [ ] Rotate both admin passwords (`php artisan admin:reset-password`) and
       delete `Admin.md` values from any shared location
5. [ ] Least-privilege MySQL user; `CACHE_STORE=redis` (or keep `database`
       consciously)
6. [ ] `php artisan config:cache && php artisan route:cache` (bootstrap
       marker logic verified safe under `config:cache`)
7. [x] Wire the `admin-read` limiter (F6) — done, per-admin keying enforced by middleware priority
8. [ ] Add session-pruning schedule (F8)
9. [x] Add security headers + CSP (F9) — done; hash-pin the SSG inline scripts post-launch
10. [x] Decide 2FA/audit-log roadmap (F4, F5) — audit log SHIPPED (see F5); lockout recovery shipped (F4); TOTP post-launch

---

*Verified live on 2026-09-30 against the Laravel :8000 build. Probes used only
the local dev environment and credentials already documented in `Admin.md`.*
