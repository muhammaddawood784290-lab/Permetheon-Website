# SECURITY FINDINGS — CARRYOVER AND FIXES

This document preserves the results of the authorized security assessment
performed on the previous Permetheon workspace before the creation of this
independent project, and records how each finding was addressed here. It is
historical documentation; it describes the *old* architecture where
applicable.

## Findings carried over

### 1. Rate limiting was a no-op (CRITICAL) — FIXED

- **Old behavior:** `CACHE_STORE=array` in the old runtime. Under the
  per-request CGI-style topology the array cache died with each request, so
  every rate limiter counter reset every request. Proven by 105 rapid
  requests against a limiter of 100 → **zero 429 responses**. Account lockout
  (DB-backed) was the only working brute-force control.
- **Fix in this project:** `CACHE_STORE=database` is the committed default
  (backend/.env, backend/.env.example, backend/phpunit.xml), and the
  `cache` / `cache_locks` migrations ship with the project. Verified live:
  4 rapid inquiry submissions → `201 201 201 201 429 429 429`.

### 2. `POST /api/admin/auth/refresh` returned 500 + Laravel HTML error page (HIGH) — FIXED

- **Old behavior:** the route pointed at `AuthController::refresh`, which did
  not exist; the framework surfaced an unhandled-error HTML page (also a
  framework disclosure).
- **Fix in this project:** `refresh()` is implemented in
  `backend/app/Http/Controllers/Admin/AuthController.php`. It revokes the
  current session server-side, mints a fresh session token + CSRF cookie pair
  in the same response, and returns the standard JSON envelope. Verified live:
  `200` JSON, old token dead after rotation.

### 3. Verb mismatch returned framework HTML (LOW, documented) — FIXED IN FULL

- **Old behavior:** some verb-mismatch paths (e.g. `PATCH /api/inquiries`)
  produced Laravel's default 405 HTML page instead of the JSON envelope.
- **Fix in this project:** the admin route group keeps its catch-all
  `Route::any('/{any}', …)` → 400 `BAD_REQUEST` envelope (asserted by the G9
  test). On top of that, a catch-all JSON exception renderer
  (`ApiResponse::forException`, wired in `bootstrap/app.php`) converts
  **every** unhandled failure on `api/*` routes — including genuine 405s
  (with the `Allow` header preserved), router 404s, throttle rejections and
  unhandled 500s — to the standard envelope. Verified live: PATCH
  `/api/inquiries` → `405` JSON `METHOD_NOT_ALLOWED` + `Allow: GET, HEAD,
  POST`; unknown `/api/*` path → `404` JSON `NOT_FOUND`. Regression suite:
  `tests/Feature/ApiExceptionEnvelopeTest.php` (7 cases) +
  `tests/Unit/ApiResponseMappingTest.php` (7 cases, incl. the 12-row mapping
  table).

### 4. Unhandled API exceptions surfaced framework HTML (MEDIUM) — FIXED

- **Old behavior (found during this migration):** the exception handler only
  had three named renderables (Authentication/Validation/ModelNotFound), and
  those were themselves broken — they called `ApiResponse::error` with the
  arguments reversed, so any of them firing would have thrown a TypeError and
  fallen back to the framework HTML page. Any *other* unhandled throwable on
  an `api/*` route (router 404s on some verbs, throttle exceptions without a
  custom response, controller bugs) also fell through to framework HTML.
- **Fix in this project:** one catch-all renderer —
  `ApiResponse::forException(Throwable, Request)` — handles the full chain
  (HttpResponseException passthrough → ValidationException →
  AuthenticationException → ModelNotFound (direct or wrapped) →
  HttpExceptionInterface with header passthrough → generic 500). Unhandled
  errors map to a generic `INTERNAL_ERROR` envelope in **every** environment
  (details go to `storage/logs`, never the client — verified with
  `APP_DEBUG=true`). The SPA-host catch-all additionally refuses `api/*`
  paths so unknown API GETs can't fall through to the HTML shell.

### 5. Latent 500 on policy-conflict deletion (MEDIUM) — FIXED

- **Old behavior:** `Admin\InquiryController::destroy` caught a policy
  conflict and called `ApiResponse::conflict(...)`, but that method did not
  exist — a `prevent`-policy conflict would have crashed with a 500.
- **Fix in this project:** `ApiResponse::conflict()` (409, `CONFLICT`
  envelope) exists, so the deletion policy responds correctly.

### 6. `APP_DEBUG=true` in the local environment (LOW, guidance) — DOCUMENTED

- backend/.env carries `APP_DEBUG=true` **for local development only**, with
  an inline warning; .env.example and the README state it must be `false` in
  any shared or production environment (Hostinger or equivalent).

## What passed (unchanged, still enforced by the test suite)

- No IDOR: every admin route resolves rows server-side with authorization
  middleware (`admin.guard` + `can:permission`).
- No SQLi: all queries go through Eloquent/query-builder parameter binding.
- No stored XSS execution: dangerous markup is rejected at validation
  (`DANGEROUS_PATTERN`) across name/company/message.
- No mass assignment: only the canonical 8-field inquiry payload survives
  validation; privileged fields (`status`, `priority`, `role`, `id`,
  `createdAt`) are server-controlled (asserted by tests).
- CSRF double-submit: 24 negative cases → 403; `hash_equals` comparison;
  token also returned in the login/refresh response body.
- Login lockout: 5 failures / 15 min, DB-backed; the *correct* password also
  fails while locked (no bypass).
- Session security: opaque tokens stored SHA-256-hashed; 30-min sliding idle
  window + 24-h absolute lifetime; server-side revocation on logout and
  refresh.
- Rate limiters: inquiry 5/10min per IP (env-tunable), login 10/10min,
  admin reads 120/min — now actually backed by the database cache.

## Incident disclosure (2026-09-25)

During pentest diagnostics, a `php artisan migrate:fresh --force` was run in
the old workspace and hit the real application SQLite database
(`skeleton/database/database.sqlite`), wiping it: 7 inquiries, 1 meeting,
notes and the admin account were lost. Forensic carving recovered zero rows.
The only surviving data is the pre-migration archive `data/permetheon.db`
(5 inquiries + an admin, old schema) in the old workspace. This new project
starts with an empty MySQL database; restoring those 5 archived inquiries is
a separate, user-owned decision and has **not** been done here.
