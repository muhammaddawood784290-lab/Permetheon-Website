# RELEASE ARTIFACT REPORT — Permetheon Website V3 PHP

- **Release version:** V3-PHP r2 (2026-09-30, 12:56 local)
- **Artifact location:**
  - Tree: `D:\Permetheon website V3 PHP\release\`
  - Zip: `D:\Permetheon website V3 PHP\permetheon-v3-release.zip`
  - Zip size: 16,117,938 bytes · **SHA-1 `a7df4355f1dde969acd3191c3e8d8f46f394ac05`**
- **Source of truth:** `docs/HOSTINGER_DEPLOYMENT_PLAN.md` (§4 layout, §6/§7 doc
  root, §10 .htaccess, §13 env, §23 commands, §27 smoke tests, §30 DB rules).
- **Status: READY for Hostinger deployment** — every readiness check below
  passed; no deployment has been performed (per instruction).

### r2 changelog (this revision)

- **Catch-all JSON exception renderer** (`ApiResponse::forException`, wired in
  `bootstrap/app.php`): every unhandled failure on `api/*` — router 404s,
  verb-mismatch 405s (with `Allow` preserved), throttle rejections, unhandled
  500s — now answers the standard envelope instead of a framework HTML page.
  This closes the last two carried-over pentest findings (findings 2 & 3 in
  `docs/SECURITY_FINDINGS_CARRYOVER.md`) and releases known risk #5 from r1.
  Unhandled errors never leak internals — not even with `APP_DEBUG=true`.
- **API guard in the SPA-host catch-all** (`routes/web.php`): unknown `api/*`
  GETs can no longer fall through to the HTML shell (they return the JSON 404
  envelope). No competing API catch-all route was added — it would shadow
  routes registered later and mask genuine 405s.
- **+20 regression tests** (`tests/Feature/ApiExceptionEnvelopeTest.php`,
  `tests/Unit/ApiResponseMappingTest.php`); suite is now **114/114 (1,662
  assertions)**. One latent time-of-day-dependent premise in
  `MeetingEngineTest` (PAST ⇔ "already ended") was corrected to the real
  contract (PAST ⇔ ended ∨ inside lead time) — stricter, not weaker.
- Artifact re-synced, production-mode smoke re-run on `:8030`, zip rebuilt and
  re-verified (dotfiles in, no `.env`/marker/tests out).

## 1. Exact folder structure (matches plan §4)

```
release/
├── backend/                          ← Laravel 12 app (upload to ~/permetheon-app/backend)
│   ├── app/                          controllers, models, middleware, services (production code)
│   ├── bootstrap/                    app.php, providers.php + cache/ (packages.php, services.php)
│   ├── config/                       app, admin, auth, cache, database, meetings, queue, session, …
│   ├── database/migrations/          10 migrations (additive; no SQLite anywhere)
│   ├── database/seeders/             DatabaseSeeder (meeting_settings singleton)
│   ├── lang/  resources/  routes/    api.php + web.php (SPA host)
│   ├── public/                       ← THE DOCUMENT ROOT (plan §6/§7)
│   │   ├── index.php                 front controller
│   │   ├── .htaccess                 production rules: HTTPS force, front controller,
│   │   │                             secrets deny-list, immutable asset cache
│   │   └── robots.txt                SEO rules copy (matches frontend/dist exactly — diff-verified)
│   ├── storage/                      empty skeleton: app/, framework/{cache/data,sessions,views}/,
│   │                                 logs/  (all present, no files)
│   ├── vendor/                       PRODUCTION ONLY — composer install --no-dev (53 pkgs, no dev)
│   ├── artisan  composer.json  composer.lock
│   └── .env.example                  template ONLY — no real .env packaged (verified)
├── frontend/
│   └── dist/                         ← built SPA — REQUIRED sibling of backend/ (plan §4/§5)
│       ├── index.html                (homepage, prerendered)
│       ├── admin-shell.html          empty client-only shell
│       ├── about.html work.html services.html process.html contact.html case-studies.html
│       ├── case-studies/{pct,tbms,estatehub,travelnest}.html
│       ├── admin/meetings.html
│       ├── assets/                   hash-named JS/CSS/fonts (immutable cache)
│       ├── og/  screenshots/         public assets
│       ├── static-loader-data/ + manifest json
│       ├── sitemap.xml               11 URLs
│       └── robots.txt                SEO rules (admin/system/api/admin disallowed)
└── (zip contains exactly these two trees)
```

## 2. Build results (final run, this release)

| Step | Result |
| --- | --- |
| `npm run build` (frontend) | **PASS** — 12 pages prerendered, sitemap 11 URLs, admin-shell emitted, **140/140 SEO checks passed** |
| `npx tsc --noEmit` (TypeScript) | **PASS** — zero errors |
| `php artisan test` (backend) | **PASS** — **114/114 tests, 1,662 assertions** (against `permetheon_test`; includes the 20 exception-envelope regression tests) |

## 3. Artifact verification (all performed live)

1. **dist contents** — all 12 prerendered pages present; `admin-shell.html`;
   `sitemap.xml` (11 URLs); `robots.txt`; `assets/` (11 hash-named files);
   `og/*.png`; `screenshots/**`; `static-loader-data/`. ✔
2. **`backend/public/robots.txt`** — byte-identical to `frontend/dist/robots.txt`
   (`diff` clean): disallows `/system`, `/admin`, `/admin/`, `/api/admin`,
   carries the sitemap pointer. ✔
3. **No dev-only files in the artifact** — verified by scan:
   - no real `.env` (only `.env.example`), no `.git`, no `node_modules`
   - no `tests/`, no `phpunit.xml` — removed from the artifact
   - no SQLite files anywhere, no E2E temp DBs, no pidfiles
   - no probe scripts, debug artifacts, local logs (storage skeleton is empty)
   - `vendor/` rebuilt with `composer install --no-dev` → phpunit/pest/pint/
     faker/mockery/collision/sail/pail/tinker all **absent**
4. **No production secrets** — artifact contains zero credentials; the smoke
   `.env` used a throwaway local DB and was deleted before packaging. ✔
5. **Document root requirements** — `backend/public/` holds exactly
   `index.php`, `.htaccess`, `robots.txt` (favicon placeholder removed — the
   SPA has no favicon reference). Doc root per plan §6/§7: point Hostinger at
   `permetheon-app/backend/public` (symlink or copy variant). ✔
6. **Structure matches plan §4** — `backend/` and `frontend/dist/` are
   siblings inside the release root; the SPA host's
   `realpath(dirname(__DIR__,2).'/frontend/dist')` resolves correctly in this
   layout (proven live, §3.7). ✔
7. **Runs without Node.js** — the artifact ships **compiled** `dist/` and a
   PHP-only backend; no JS runtime is referenced anywhere in the Laravel
   config/routes (Vite exists only as a build-time dev tool). ✔
8. **Production smoke run** (artifact booted on `:8030` with `APP_ENV=production`,
   `APP_DEBUG=false`, cached config+routes, throwaway DB `permetheon_release_smoke`,
   dropped afterwards):
   - Routes: `/` `/work` `/services` `/process` `/about` `/contact`
     `/case-studies` `/case-studies/{pct,tbms,estatehub,travelnest}`
     `/admin` `/admin/login` `/admin/meetings` → **all 200** ✔
   - Unknown route `/zzz` → **404** (real status, app shell) ✔
   - **Unknown api route → 404 JSON envelope** (not the HTML shell) ✔
   - **PATCH /api/inquiries → 405 JSON envelope with `Allow` preserved** ✔
   - `GET /api/meetings/availability` → **200**; inquiry POST → 201;
     rate limiter → 429 after 5 (DB-backed, config-cached) ✔
   - `/api/admin/*` protected: unauthenticated list → **401**; CSRF-less
     authenticated PATCH → **403**; bootstrap login → 200; HEAD `/session`
     → **204**; authenticated list → **200**; refresh → 200 with old token
     dead (401) ✔

## 4. Files intentionally excluded from the artifact

`.env` (real), `.git`, `node_modules`, `frontend/src`, `frontend/node_modules`,
`frontend/package*.json`, `frontend/*.config*`, `frontend/scripts/`,
`backend/tests/`, `backend/phpunit.xml`, all `docs/`, all `scripts/` (E2E),
`legacy/`, all logs, storage runtime files, E2E pidfiles/DBs, dev composer
packages (tests tooling stripped from `vendor/` via `--no-dev`).

## 5. Production environment variables required (create on the server)

Per plan §13 — in `permetheon-app/backend/.env` (chmod 640, never uploaded):

```
APP_NAME=Permetheon            APP_ENV=production
APP_KEY=<php artisan key:generate --force>
APP_DEBUG=false                APP_URL=https://permetheon.com
DB_CONNECTION=mysql            DB_HOST=<hPanel host>      DB_PORT=3306
DB_DATABASE=<u…_permetheon>    DB_USERNAME=<u…_perm>      DB_PASSWORD=<strong>
CACHE_STORE=database           QUEUE_CONNECTION=database
SESSION_DRIVER=database        SESSION_SECURE_COOKIE=true
ADMIN_BOOTSTRAP_EMAIL=admin@permetheon.com
ADMIN_BOOTSTRAP_PASSWORD=<one-time; remove .env value after first login>
INQUIRY_RATE_LIMIT_MAX=5       MEETING_INQUIRY_DELETE_POLICY=delete_meeting
TRUSTED_PROXIES=<post-deploy per plan §13a>
MAIL_MAILER=log
```

## 6. Hostinger upload/deployment instructions (condensed; full plan §23–§25)

1. Upload the **contents** of the release tree so the server has
   `~/permetheon-app/backend/…` and `~/permetheon-app/frontend/dist/…`
   (keep `backend` ↔ `frontend/dist` siblings).
   **Dotfiles:** ensure `backend/public/.htaccess` and `backend/.env.example`
   survive the transfer (zip tools often hide dotfiles — verify after extract).
2. Point the domain document root at `permetheon-app/backend/public`
   (plan §6; copy variant §7-B if the panel refuses).
3. On the server: `cp .env.example .env`, fill §13 values,
   `php artisan key:generate --force`.
4. `php artisan migrate --force` **only** (plan §30 — never fresh/refresh/wipe)
   → then `php artisan db:seed --force`.
5. `php artisan config:cache && php artisan route:cache`.
6. First admin: log in once at `/admin/login` with the bootstrap pair, then
   **remove `ADMIN_BOOTSTRAP_PASSWORD` from `.env`** (server additionally
   writes `storage/app/admin-bootstrap.completed` — bootstrap is permanently
   disabled after first use, verified in this release).
7. Run the plan §27 smoke tests; check §13a (TRUSTED_PROXIES) with a real
   inquiry; enable Force HTTPS + SSL.

## 7. Pre-deployment checklist

- [x] `npm run build` 140/140 · `tsc` clean · `php artisan test` 114/114 (this release)
- [x] dist + robots + sitemap + admin-shell verified in artifact
- [x] artifact free of dev files, secrets, SQLite, Next references (§3, scans)
- [x] `backend/public/.htaccess` production rules present (HTTPS, deny-list, cache)
- [x] zip dotfiles verified (`backend/public/.htaccess`, `backend/.env.example`)
- [ ] hPanel: PHP ≥ 8.2 selected; MySQL DB created (utf8mb4); doc root set to `backend/public`
- [ ] server `.env` created (§5 values); `ADMIN_BOOTSTRAP_PASSWORD` removed after first login
- [ ] `migrate --force` + `db:seed --force` only (§30 rules acknowledged)
- [ ] `config:cache` + `route:cache` run; §27 smoke tests executed
- [ ] backup taken (mysqldump/phpMyAdmin export) before the migrating deploy

## 8. Post-deployment smoke tests

Execute plan §27 (20 checks) — the same set proven locally on this artifact
(§3.8), against the live domain: `/up`, all 11 public routes, `/admin*`,
API matrix (availability 200, inquiry 201, limiter 429, admin 401/403/200/204,
refresh rotation), `/sitemap.xml`, `/robots.txt`, unknown-route 404,
`/.env`+`/.git`+`/vendor` non-servable, force-HTTPS 301, cookie flags,
SSL Labs grade.

## 9. Rollback instructions

Per plan §28: redeploy the previous artifact/zip (this release is v1 — keep
the SHA-1 above with the deployment record), re-run `config:cache` +
`route:cache`; frontend-only changes: re-upload previous `dist/`; `.env`
restore from server-side backup; schema changes only forward (no
`migrate:rollback` in production without a fresh backup and a documented
reason); `php artisan down` as the brake while working.

## 10. Known risks

1. **Doc-root/403 class risk** (the old Next.js failure mode) — mitigated by
   the plan's §29 ordered troubleshooting; verify `/up` immediately after
   pointing the doc root.
2. **Dotfile loss in transfer** — `.htaccess`/`.env.example` verified inside
   the shipped zip; re-check after any re-extraction.
3. **TRUSTED_PROXIES granularity** (plan §13a) — limiter works regardless;
   per-client granularity needs the proxy IP set post-deploy.
4. **Manual artisan misuse** — `migrate:fresh` in `backend/` targets whatever
   `.env` says; §30 rules + the persistent bootstrap marker (`storage/app/
   admin-bootstrap.completed`) are the guards; operational discipline applies.
5. **HTML error pages on unhandled API errors** — **FIXED in r2**: the catch-all
   JSON exception renderer answers every `api/*` failure with the standard
   envelope (verified live on the artifact, production config).
6. **Stale dist** — no server build step; ship `dist/` on every release.
7. **No cron configured** — fine today (no scheduler/queue workers); add per
   plan §22 if queue features arrive.

---

**VERDICT: READY for Hostinger deployment.**
Artifact: `permetheon-v3-release.zip` (SHA-1 `a7df4355…ac05`) +
`release/` tree. Nothing has been deployed; no DNS, domain, production-DB, or
archive-import actions were taken.
