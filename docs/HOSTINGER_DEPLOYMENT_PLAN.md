# HOSTINGER DEPLOYMENT PLAN — Permetheon Website V3 PHP

**Status: PLAN ONLY — do not deploy yet.** No DNS changes, no production-domain
changes, no archive import, no deployment execution. Application code was not
modified except where explicitly noted as deployment compatibility fixes.

**Stack:** React 19 + TypeScript + Vite (`vite-react-ssg`, prerendered
`frontend/dist`) · Laravel 12 (PHP 8.2+) · MySQL/MariaDB · **single origin**
(Laravel serves the SPA and the API from one domain — this is why cookies,
CSRF and CORS all stay trivial).

**Critical architectural fact (from inspection):** the SPA host
(`backend/routes/web.php`) resolves the prerendered site as
`realpath(dirname(__DIR__, 2) . '/frontend/dist')` — i.e. `../frontend/dist`
**relative to the `backend/` directory**. The deployed layout must preserve
that sibling relationship, or the site returns 500s. The layout below does.

---

## 1. Hostinger architecture

Hostinger shared/premium hosting = **Apache 2.4 + LiteSpeed, PHP via
mod_lsapi, MySQL (MariaDB), cPanel-style hPanel, one document root per
domain/subdomain, SSH available on paid plans**. Node.js is NOT reliably
available as a runtime — therefore:

- **All building happens locally / in CI. The server only executes PHP.**
- The server never runs `npm`, never runs Vite. Upload the built `dist/`.
- Composer: prefer uploading the committed `vendor/` (composer.lock exists);
  running `composer install` over SSH is the fallback (see §23).
- Deployment artifact = Laravel app (with `vendor/`) + built `frontend/dist`,
  laid out as in §4.

**How routing works (single origin):**

```
browser → https://permetheon.com/… → Apache/LiteSpeed (public_html)
  ├── real static file exists (assets/, og/, screenshots/, *.html)? → serve directly
  ├── /api/* and anything else → frontend controller = Laravel (see §6/§10)
  └── Laravel routes/web.php:
        ├── /api/* (routes/api.php) — JSON API
        └── everything else — SPA host:
              1. file exists in ../frontend/dist → serve it (MIME-correct, cache headers)
              2. /contact → ../frontend/dist/contact.html (prerendered page, 200)
              3. /admin*, /system → ../frontend/dist/admin-shell.html (empty shell, 200)
              4. anything else → ../frontend/dist/index.html with HTTP 404
```

This keeps **/api and SPA routes coexisting on one domain** (verify list in
§27) with zero CORS surface: SameSite=Strict cookies, CSRF double-submit and
the login Origin-vs-Host check all work unchanged.

**Why the previous Next.js deployment 403'd:** the Next deployment had no
static document root that Apache could serve — its runtime model (a Node
server process) does not exist on shared hosting, so the doc-root pointed at
directories Apache was not allowed/able to list (or into `node_modules`-style
paths that LiteSpeed hard-deny) → 403 Forbidden. **This plan does not reuse
any of that architecture.** The new model exposes a classic Apache document
root (`public/`) with real files on disk and a PHP front controller — exactly
the deployment shape LiteSpeed expects. The 403-troubleshooting procedure is
still §29 because doc-root misconfiguration remains the top risk.

## 2. Required PHP version / extensions

- **PHP 8.2 or 8.3** (composer requires `^8.2`; Laravel 12 supports 8.2–8.4;
  pick the newest the panel offers, ≥8.2 hard floor). Select per-domain in
  hPanel → PHP Configuration. Also set the **CLI PHP version** to match (the
  `php` on PATH over SSH).
- Required extensions (all standard on Hostinger):
  `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `ctype`, `json`, `tokenizer`,
  `filter`, `session`, `fileinfo`, `curl`, `dom`/`xml`, `zlib`.
  Verify over SSH: `php -m | grep -E 'pdo_mysql|mbstring|openssl'`.
- `opsion` note: LiteSpeed + Opcache is on by default; harmless here.
- Memory: `memory_limit ≥ 256M` for artisan/composer one-offs.

## 3. MySQL database setup

In hPanel → **Databases → MySQL Databases**:

1. Create database: e.g. `u123456789_permetheon` (Hostinger prefixes the
   account name; use the **full** name in `.env`).
2. Create user: e.g. `u123456789_perm` with a generated strong password; add
   the user to the database with **ALL PRIVILEGES** on that database only.
3. Host: **use the exact host hPanel shows** (usually `localhost` on shared,
   sometimes `srv123.hstgr.io`-style remote host for remote MySQL).
4. Charset: create with `utf8mb4` / `utf8mb4_unicode_ci` (matches
   `config/database.php`; if the panel doesn't offer a choice, run
   `ALTER DATABASE ... CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` once).
5. **The production database starts EMPTY.** Schema arrives only via
   `php artisan migrate --force` (§30 rules). The meeting-settings singleton
   arrives via `php artisan db:seed --force` (idempotent `updateOrInsert`).
6. Record the credentials for §13/§17. Do not reuse any dev/test password.

**Never** create a `permetheon_test` / `permetheon_e2e` database on Hostinger.
Tests and E2E never run against production (§30).

## 4. Laravel directory placement

The web root must contain ONLY `backend/public` (see §6). The Laravel app
itself (plus `frontend/dist`) lives **one level above the web root**:

```
/home/u123456789/
├── permetheon-app/            ← NOT web-accessible
│   ├── backend/               ← the Laravel app (full repository backend/)
│   │   ├── app/ bootstrap/ config/ database/ routes/ tests/
│   │   ├── vendor/            ← uploaded or composer-installed
│   │   ├── storage/ bootstrap/cache/   ← writable (§11/§12)
│   │   ├── .env               ← production secrets (§13)
│   │   ├── public/            ← the ONLY dir exposed via symlink (§6/§7)
│   │   └── artisan
│   └── frontend/
│       └── dist/              ← built SPA (prerendered pages + assets)  ← REQUIRED sibling of backend/
│           ├── index.html, admin-shell.html, *.html, case-studies/
│           ├── assets/ og/ screenshots/ static-loader-data/
│           └── sitemap.xml robots.txt
└── public_html/               ← DOMAIN DOCUMENT ROOT (web root)
    └── (contents of backend/public — see §6/§7 for how)
```

`permetheon-app` may be named anything; if Hostinger places a mandatory
`sites`/`domains` level, keep the **relative** distance `backend → ../frontend/dist`
intact. If hPanel *forces* the doc root inside the same tree, see the variant
in §7.

## 5. React/Vite production build placement

- Built **locally** before upload: `cd frontend && npm run build`
  → `frontend/dist/` (12 prerendered pages, 11-URL sitemap, admin-shell,
  140 SEO checks green, assets hash-named under `assets/`).
- Upload **all of `dist/`** to `<app>/frontend/dist/` (structure preserved —
  `case-studies/*.html`, `admin/meetings.html`, `og/*.png`,
  `screenshots/**`, `static-loader-data/`, `sitemap.xml`, `robots.txt`,
  `index.html`, `admin-shell.html`).
- **Rebuild + re-upload on every frontend change.** The SPA host serves
  whatever is on disk; there is no server-side build step.
- `VITE_API_BASE=""` is baked at build time (same-origin). If the API ever
  moves to another domain, rebuild with `VITE_API_BASE=https://api.…` — never
  hand-edit dist.
- Do not upload `node_modules/`, `src/`, or the frontend dev files.

## 6. Public document root configuration

hPanel → **Websites → permetheon.com → Document Root** must be:

```
/home/u123456789/permetheon-app/backend/public
```

(On Hostinger the doc-root picker may only offer paths under the account root
or `domains/<domain>/` — choose/enter the deepest offered path that points at
`backend/public`; if the panel refuses paths outside `public_html`/`domains`,
use the symlink-or-copy variant in §7.) This is the #1 item to verify before
anything else — a doc-root pointing at `backend/` (or the repo root) is
exactly the class of mistake that produced the old Next.js 403, and would
also expose `.env`/`vendor/`.

After setting it, `https://permetheon.com/` must hit
`backend/public/index.php` (Laravel) and `https://permetheon.com/assets/…`
must hit `../frontend/dist` via the SPA host.

## 7. Laravel public/ directory handling

Two supported variants — **decide at deployment time by what hPanel allows**:

**Variant A — symlink (preferred, keeps one source of truth):**
```
public_html -> /home/u123456789/permetheon-app/backend/public
```
`ln -s ../permetheon-app/backend/public public_html` over SSH (or ask Hostinger
support to point the doc root at `backend/public`). If symlinks are refused by
LiteSpeed's doc-root check, use Variant B.

**Variant B — copy (works everywhere, needs re-sync on changes):**
copy the **contents** of `backend/public` into the real doc root
(`index.php`, `.htaccess`, `favicon.ico`, `robots.txt`), then make the two
path references in the copied `index.php` absolute-safe by adding a
bootstrap path file:
```
index.php:  require __DIR__.'/../../backend/vendor/autoload.php'
            require __DIR__.'/../../backend/bootstrap/app.php'
```
(calculated to match the actual copied location — with the §4 layout and
`public_html` at `/home/u123456789/public_html`, the prefix is
`__DIR__.'/../permetheon-app/backend/…'`). Verify with `php -l` and one
`/up` request before going live.

**What must exist in the web root:** `index.php` (front controller),
`.htaccess` (§10), `favicon.ico`, `robots.txt` (SEO-rules copy — see the
compatibility fix note in §30 preface).

**What must NEVER appear in the web root:** `.env`, `.git`, `storage/`,
`vendor/`, `tests/`, `database/`, `node_modules/`, `docs/`, `scripts/`,
`frontend/src/`, any `*.md`, backup files (`*.bak`, editor swaps).

## 8. SPA route fallback configuration

Already implemented server-side by `routes/web.php` (no host changes needed):

- `/` → `dist/index.html` (200), `/work` → `dist/work.html` (200), …
  `/case-studies/pct` → `dist/case-studies/pct.html` (200) — prerendered,
  SEO-correct pages.
- `/admin`, `/admin/login`, `/admin/meetings`, `/system` →
  `dist/admin-shell.html` / `dist/index.html` (200, noindex, client-only
  routes render there).
- **Unknown route → `dist/index.html` with a real 404 status** (never a
  redirect to the homepage).
- Static assets (fonts, og images, screenshots) → file handler with
  immutable cache for `assets/*`.

The `.htaccess` must not fight this: it only routes to `index.php`
(§10) — Laravel then decides. Trailing-slash handling: Apache's redirect in
`.htaccess` + Laravel's own fallback both tolerate `/contact/`.

## 9. API routing

- All API paths are `https://permetheon.com/api/*` (25 routes in
  `routes/api.php` under the `api` prefix), handled by the front controller —
  **no subdomain, no separate doc root, no proxy rules**.
- `POST /api/inquiries` (rate-limited), `GET /api/meetings/availability`,
  `POST /api/admin/auth/login|logout|refresh`, `GET /api/admin/auth/session`,
  the `/api/admin/*` resource routes (guard + permission + CSRF), `GET /up`
  health.
- `.htaccess` passes `Authorization` and `X-XSRF-Token` headers through
  (already in Laravel's stock `.htaccess`) — keep that block.
- **No CORS configuration is needed** (§19) because frontend and API share
  the origin. Do not add permissive CORS headers.

## 10. .htaccess requirements

Keep Laravel's stock `backend/public/.htaccess` (verified present) and **add**
the hardening/cache blocks. Target content:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On

    # Authorization / XSRF passthrough
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Force HTTPS (Hostinger terminates TLS in front of Apache)
    RewriteCond %{HTTPS} !=on
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Trailing slashes (except existing dirs)
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Front controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# --- hard block sensitive files even if the doc root is ever mispointed ---
<FilesMatch "^\.env|\.env\.|composer\.(json|lock)|artisan$">
    Require all denied
</FilesMatch>

# --- long-cache immutable Vite assets ---
<IfModule mod_headers.c>
    <FilesMatch "\.(woff2?|css|js|png|jpe?g|webp|svg|ico)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
```

LiteSpeed reads `.htaccess` Apache syntax; `Require all denied` (Apache 2.4
syntax) is correct. If Hostinger's LSPHP ignores `<FilesMatch>` for
dotfiles, the doc-root rule (§6) is the real protection — this is belt-and-
braces. (Prerendered `*.html` pages bypass PHP entirely: Apache serves them
as static files — faster and exactly as intended.)

## 11. Storage symlink requirements

`php artisan storage:link` creates `backend/public/storage` →
`backend/storage/app/public`. **This app stores no user uploads** (inquiries,
notes, meetings live in MySQL; screenshots are static files in the SPA), so
the symlink is optional. Recommended: **skip it** — one less path in the web
root. If a future feature needs public uploads, run
`php artisan storage:link` once over SSH and verify `/storage/…` serves;
never copy `storage/` into the web root itself.

## 12. File / folder permissions

Hostinger runs PHP as the account user, so defaults are mostly fine. Verify:

- `backend/.env` → **640** (owner read/write, group read; never world-read).
- `backend/storage/` (recursive) and `backend/bootstrap/cache/` → **755**
  dirs / **644** files; the web user must be able to create files in
  `storage/framework/{cache,sessions,views}`, `storage/logs`,
  `storage/app`, and `bootstrap/cache`.
- Everything else 644/755. **Never 777.**
- After the first deploy, prove writability: a login attempt must append to
  `storage/logs/laravel.log` and write `bootstrap/cache/config.php` after
  `php artisan config:cache`.
- Over SSH: `find storage bootstrap/cache -type d -exec chmod 755 {} \;`
  and `find storage bootstrap/cache -type f -exec chmod 644 {} \;`.

## 13. Production .env configuration

Create `backend/.env` **on the server only** (never upload the dev file):

```ini
APP_NAME=Permetheon
APP_ENV=production
APP_KEY=            # generated on the server, see §23
APP_DEBUG=false     # see §15
APP_URL=https://permetheon.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning            # not debug in production
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=localhost            # exact host from hPanel (§3)
DB_PORT=3306
DB_DATABASE=u123456789_permetheon
DB_USERNAME=u123456789_perm
DB_PASSWORD=<generated-strong-password>

CACHE_STORE=database         # REQUIRED — rate limiters depend on it
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null          # same-host cookie; do not set a domain
SESSION_SECURE_COOKIE=true   # cookies only over HTTPS

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

ADMIN_BOOTSTRAP_EMAIL=admin@permetheon.com
ADMIN_BOOTSTRAP_PASSWORD=<one-time, set before first login, REMOVE after>  # see §23

INQUIRY_RATE_LIMIT_MAX=5
MEETING_INQUIRY_DELETE_POLICY=delete_meeting
TRUSTED_PROXIES=             # see §13a
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@permetheon.com
MAIL_FROM_NAME="${APP_NAME}"
```

**13a. TRUSTED_PROXIES on Hostinger (important):** the rate limiter keys on
the socket peer unless it is a trusted proxy; `X-Forwarded-For` is ignored
otherwise (security fix from the verification run). On shared LiteSpeed the
TLS/edge proxy sits in front, so client IPs may appear as the proxy's IP —
all clients would share one limiter bucket. After the first deploy, check
`storage/logs/laravel.log` / a test inquiry: if `$request->ip()` is not the
visitor's public IP, set `TRUSTED_PROXIES` to the proxy address hPanel
documents (often `127.0.0.1` on the same host; load-balancer ranges are shown
in Hostinger's docs/support). This only affects limiter *granularity* — the
limit itself works either way.

## 14. APP_ENV=production

- Set `APP_ENV=production` in the server `.env` (§13).
- Effects relied upon: `secure` cookie flag on `admin_session`/`admin_csrf`
  becomes active (`config('app.env') === 'production'` in AuthController);
  debug pages off; Laravel error reporting to logs.
- Verify after deploy: `php artisan about` (SSH) shows
  `Environment ... production`; response headers must show `Set-Cookie: … secure`
  on login (smoke test §27).

## 15. APP_DEBUG=false

- `APP_DEBUG=false` **mandatory in production** (verified behavior during the
  security run: unhandled errors return a generic page — no stack traces, no
  paths, no environment details).
- Never set `true` on the production `.env`, even temporarily for debugging —
  read `storage/logs/laravel.log` instead.
- Verify: request an erroring route (e.g. `/api/admin/inquiries?search[]=x`
  while logged out) → generic 500 page, no internals.

## 16. APP_URL

- `APP_URL=https://permetheon.com` (scheme included, **no trailing slash**).
- Used for URL generation, canonical links consistency, and origin checks.
  Every cookie/API response is same-origin, so a wrong APP_URL mostly
  surfaces as broken generated links — set it right once.
- If the site is also reachable via `www.`, decide one canonical host and
  301 the other (Hostinger's "force HTTPS + redirect www" toggles, or an
  `.htaccess` rule mirroring the HTTPS block for `www→apex`).

## 17. Database credentials configuration

- Values come from §3 into the §13 `.env` keys (`DB_HOST/DB_PORT/DB_DATABASE/
  DB_USERNAME/DB_PASSWORD`). Use the hPanel-provided host verbatim.
- The credentials exist **only** in the server `.env` (640). Never in git,
  never in `dist/`, never in docs. `.gitignore` already excludes `.env`.
- Connectivity check before migrating:
  `php artisan db:monitor` (Laravel 12) or `php artisan tinker`-free probe:
  `php -r` one-liner with PDO, or simply `php artisan migrate --force` and
  watch the first "Preparing database" line (see §30 for the safety rules).
- If the panel gives a `mysql` socket path instead of TCP, keep TCP host —
  Hostinger MySQL is TCP.

## 18. Cache / session / queue configuration

- `CACHE_STORE=database` — mandatory (rate limiting correctness); `cache` +
  `cache_locks` tables come from migrations.
- `SESSION_DRIVER=database` — framework sessions table exists; the admin
  auth system doesn't use it (own `admin_sessions` table with hashed tokens,
  30-min idle / 24-h absolute), this is just consistency.
- `QUEUE_CONNECTION=database` — jobs tables exist; no queue worker is needed
  today (no queued jobs in the codebase). If one is added later, run
  `php artisan queue:work` via a Hostinger cron loop (§22).
- After deploy: `php artisan config:cache && php artisan route:cache`
  (route:cache is safe — the route map is static; **do not** run
  `optimize` on old Laravel versions assumptions, 12 handles both fine).
  Re-run these after every future deploy that changes config/routes.

## 19. CORS configuration if required

**Not required.** The SPA and the API are same-origin by construction
(`VITE_API_BASE=""`, Laravel serves both). Do not add
`config/cors.php` with permissive origins; the CSRF double-submit and
SameSite=Strict cookies assume the same origin. If a future mobile/3rd-party
consumer appears, whitelist explicit origins only — never `*` with
credentials.

## 20. HTTPS configuration

- Hostinger provides free Let's Encrypt certificates per domain — issue one
  for `permetheon.com` (+ `www`) in hPanel → Security → SSL, and enable
  **Force HTTPS**.
- The `.htaccess` HTTPS redirect (§10) is the second layer.
- Cookies: `SESSION_SECURE_COOKIE=true` (§13) + Laravel sets `secure` on
  auth cookies automatically when `APP_ENV=production`. The security-verified
  wire format is `admin_session: secure; httponly; samesite=strict`.
- After issuing, verify with an external checker (SSL Labs) — no mixed
  content: the SPA references only relative/`/`-rooted assets, so no mixed
  content is possible.

## 21. Security headers

Add via `.htaccess` (Apache `<IfModule mod_headers.c>` block) — keep them
conservative to not break the prerendered pages:

```apache
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
# Optional first-pass CSP (report-only first, then enforce):
# Header always set Content-Security-Policy-Report-Only "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'"
```

(HSTS can be enabled in hPanel/SSL settings after confirming all subdomains
are HTTPS-only: `Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"`.)

The security-critical headers that matter for this app are already the
cookies (`HttpOnly`, `Secure`, `SameSite=Strict`) — headers above are
hardening on top.

## 22. Cron requirements if any

**None required for current functionality** (no scheduler tasks, no queue
workers, no email digests; `MAIL_MAILER=log`).

If later needed, hPanel → Cron Jobs:
- Queue worker (only when queue features are added):
  `* * * * * cd /home/u123456789/permetheon-app/backend && php artisan queue:work --stop-when-empty --max-time=50`
- Laravel scheduler (only when scheduled tasks are added):
  `* * * * * cd /home/u123456789/permetheon-app/backend && php artisan schedule:run`
- Optional log rotation guard: monthly `php artisan log:clear` is not built
  in; instead rotate `storage/logs/laravel.log` by size manually or via cron
  `mv` + `touch`.

## 23. Deployment commands

**First deployment (SSH):**

```bash
# 0) local: build the frontend
cd frontend && npm ci && npm run build

# 1) upload (SFTP/rsync/git — see §24/§25) so that:
#    <app>/backend/**  (WITHOUT .env, WITHOUT node_modules)
#    <app>/frontend/dist/**
# 2) on the server:
cd /home/u123456789/permetheon-app/backend
php -v                                   # must be >= 8.2
[ -f .env ] || cp .env.example .env      # then EDIT .env per §13 (do not ship dev values)
php artisan key:generate --force
php -d memory_limit=512M /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   # (or upload vendor/ from local `composer install --no-dev` — preferred if composer is restricted)
php artisan migrate --force              # ADDITIVE ONLY — see §30
php artisan db:seed --force              # meeting_settings singleton (idempotent)
php artisan config:cache
php artisan route:cache
# 3) first admin: log in ONCE at https://permetheon.com/admin/login with
#    ADMIN_BOOTSTRAP_EMAIL + ADMIN_BOOTSTRAP_PASSWORD from .env,
#    then IMMEDIATELY remove ADMIN_BOOTSTRAP_PASSWORD from .env
#    (bootstrap wipes it in-process; removing it from disk closes the hole).
# 4) smoke tests §27.
```

**Subsequent deployments (zero-downtime-ish):**

```bash
# local
cd frontend && npm ci && npm run build
# upload changed files (rsync example; adjust paths/keys)
rsync -av --delete backend/app backend/config backend/database backend/routes backend/resources user@server:permetheon-app/backend/
rsync -av --delete --exclude '.env' backend/vendor user@server:permetheon-app/backend/   # if shipping vendor
rsync -av --delete frontend/dist user@server:permetheon-app/frontend/
# server (only if changed)
php artisan migrate --force        # additive migrations only
php artisan config:cache && php artisan route:cache
```

`php artisan down` before big migrations; `php artisan up` after (maintenance
mode page comes from `storage/framework/maintenance.php` — index.php checks
it automatically).

## 24. Git deployment workflow

The project is not yet in a remote; recommended flow once it is:

1. Push `main` (or `release/*`) to GitHub/GitLab private repo.
2. **Option A — hPanel Git deployment:** connect the repo, set the target
   branch and the deployment path `permetheon-app/`; add a post-deploy hook
   running `php artisan migrate --force && php artisan config:cache && php
   artisan route:cache`. Frontend `dist/` must be **committed** (CI builds
   locally and commits the artifact, or a GitHub Action builds and pushes
   `dist` to a `dist-artifact` branch the panel deploys) because the server
   cannot run npm.
3. **Option B — Actions + rsync/FTP:** CI runs `npm ci && npm run build`,
   then ships `backend/**` (minus `.env`) + `frontend/dist/**` over
   SFTP/rsync on every tagged release (`git tag v1.2.3 && git push --tags`).
4. `.env` is NEVER in git. Keep `.gitignore` as-is (already covers it).
5. Tag every production release; the tag hash is the rollback coordinate (§28).

## 25. Manual deployment fallback

If SSH/composer/git are unavailable (strict shared plan):

1. Local: `composer install --no-dev --optimize-autoloader` (produces a
   production `vendor/`), `npm ci && npm run build`.
2. Zip two trees: `backend/` (with `vendor/`, **without** `.env`,
   `storage/logs/*`, `node_modules`) and `frontend/dist/`.
3. Upload the zip via hPanel File Manager, extract to
   `~/permetheon-app/` keeping the §4 layout.
4. Create `.env` via File Manager (copy of §13), `APP_KEY` generated locally
   with `php artisan key:generate --show` on the dev machine and pasted in.
5. Run migrations **without SSH** — two options, in order of preference:
   - hPanel "Terminal" if the plan has it (same commands as §23), or
   - a **temporary, DELETE-AFTER-USE** migration endpoint is NOT acceptable;
     instead use phpMyAdmin? No — migrations must run through artisan.
     If no CLI exists at all, do not deploy yet; ask Hostinger support to
     enable SSH (available on all paid plans) — running migrations by any
     other means risks §30 violations.
6. File Manager cannot set `chmod 640 .env` reliably — use the panel's
   permissions dialog if present; otherwise confirm via support that the
   file is not world-readable.

## 26. Pre-deployment checklist

- [ ] Frontend built clean: `npm run build` → 140/140 prerender checks pass.
- [ ] TypeScript: `npx tsc --noEmit` clean.
- [ ] Backend suite: `php artisan test` → 94/94 (against local `permetheon_test`).
- [ ] E2E: `node scripts/e2e-laravel-server.mjs` + both suites → 87/87, then
      `--stop` (throwaway DB dropped).
- [ ] `frontend/dist/robots.txt` content = SEO rules (admin/system/api/admin
      disallowed) — and `backend/public/robots.txt` matches it (compatibility
      fix applied; re-verify after any rebuild).
- [ ] No `APP_DEBUG=true`, no dev DB credentials, no `ADMIN_BOOTSTRAP_PASSWORD`
      in any file that gets uploaded.
- [ ] Production `.env` ready (§13) with `APP_ENV=production`,
      `APP_DEBUG=false`, `APP_URL=https://permetheon.com`, live DB creds,
      `CACHE_STORE=database`, `SESSION_SECURE_COOKIE=true`.
- [ ] Doc root will point at `backend/public` (§6) — confirm with the panel
      BEFORE uploading.
- [ ] `backend/public/.htaccess` contains the HTTPS + hardening blocks (§10).
- [ ] Database created (§3), credentials recorded, database is EMPTY.
- [ ] `TRUSTED_PROXIES` decision deferred to post-deploy check (§13a) — noted.
- [ ] Rollback coordinates ready (§28): current release tag/zip stored.

## 27. Post-deployment smoke tests

Run in order; any failure → §28/§29 before proceeding.

| # | Check | Expected |
| --- | --- | --- |
| 1 | `curl -I https://permetheon.com/up` | `200` (Laravel health) |
| 2 | `https://permetheon.com/` | homepage HTML, `<title>Permetheon — Software Studio…`, HTTP 200 |
| 3 | `/work /services /process /about /contact /case-studies` | each 200 with per-route `<title>`/canonical |
| 4 | `/case-studies/pct`, `/tbms`, `/estatehub`, `/travelnest` | each 200, og:image present |
| 5 | `https://permetheon.com/sitemap.xml` | 11 URLs, correct host |
| 6 | `https://permetheon.com/robots.txt` | SEO rules (Disallow /admin, /system, /api/admin + Sitemap line) |
| 7 | `/this-route-does-not-exist` | **HTTP 404** with the app shell (never a homepage redirect) |
| 8 | `/admin` and `/admin/login` | 200, render the admin login (client-only shell) |
| 9 | `GET https://permetheon.com/api/meetings/availability` | JSON `success:true`, `timezone:"UTC"`, 31 days |
| 10 | Submit a real inquiry via `/contact` | 201 persisted; row visible in DB; **delete it afterwards** (test data) |
| 11 | Book a real meeting slot via the form | 201 + meeting BOOKED; double-submit → 409; **delete afterwards** |
| 12 | Admin login with bootstrap creds | 200; cookies `secure; HttpOnly; SameSite=Strict`; then **remove ADMIN_BOOTSTRAP_PASSWORD from .env** |
| 13 | Admin: list/notes/status PATCH/meetings calendar | all function (CSRF-protected actions succeed) |
| 14 | `POST /api/admin/auth/refresh` (authenticated) | 200, old token dead |
| 15 | Unauthenticated `GET /api/admin/inquiries` | 401 JSON envelope |
| 16 | 6 rapid inquiries (one IP) | 201×5 then 429 (proves DB cache limiter live) |
| 17 | `http://permetheon.com/anything` | 301 → `https://` |
| 18 | Direct URL to secrets: `/.env`, `/.git/config`, `/vendor/` | 403/404 — **never** file contents |
| 19 | SSL Labs scan | A or A+; no mixed content |
| 20 | `php artisan about` (SSH) | environment production, cache/route cached |

## 28. Rollback procedure

1. **Code rollback:** redeploy the previous release artifact (tag from §24 or
   the pre-deploy zip). `rsync` the old tree back (or re-point the hPanel Git
   deploy to the previous tag), then on the server:
   `php artisan config:cache && php artisan route:cache`.
2. **Frontend rollback:** re-upload the previous `frontend/dist/` (keep the
   last known-good dist zipped on the server, e.g. `~/releases/dist-<hash>/`)
   — instant, no backend touch.
3. **Config rollback:** restore the previous `.env` (keep a server-side,
   non-web-readable backup, e.g. `~/.env-backups/.env.<date>` with 600 perms).
4. **Schema rollback — LAST RESORT, must be pre-planned per migration:**
   Laravel migrations have `down()` methods, but running
   `php artisan migrate:rollback` on production is a data-affecting operation:
   only for a release whose migrations are proven reversible and only after
   a fresh `mysqldump` backup (hPanel → phpMyAdmin → Export, or SSH
   `mysqldump "$DB_NAME" > ~/backup-$(date +%F).sql`). Prefer forward-fix
   (a corrective migration) over rollback whenever data exists.
5. **Emergency brake:** `php artisan down` with a custom message while
   working on any of the above.

## 29. 403 troubleshooting procedure

Ordered by likelihood for this exact stack (the old Next.js 403 was a doc-root
class failure — check #1 first):

1. **Doc root wrong** → hPanel shows a path that is NOT `…/backend/public`.
   Fix per §6/§7 (symlink if supported, copy variant otherwise). Symptom:
   every URL 403s including `/up`; or directory listing is forbidden.
2. **Doc root at `backend/public` but `.htaccess` missing/stripped** (zip
   tools skip dotfiles!) → `Options -Indexes` absent and `index.php` not
   resolved: homepage 403, `/up` 404. Re-upload `.htaccess` explicitly and
   enable "show hidden files" in the File Manager.
3. **LiteSpeed symlink refusal** (Variant A) → 403 on everything. Switch to
   Variant B (real copy in the doc root, adjusted `index.php` paths).
4. **File permissions** → files not readable by the web user (e.g. uploaded
   as 600): 403 on assets. `chmod 644` files / `755` dirs (§12); check
   ownership matches the account user.
5. **`storage/` or `vendor/` symlinked/copied INTO the doc root** by mistake
   → LiteSpeed 403s the whole tree. Remove them from the web root; only the
   four web-root files of §7 belong there.
6. **ModSecurity rule trip** (POSTs with JSON can trigger generic WAF rules):
   single URL 403 on form submit, everything else fine → hPanel → Security →
   Advanced → ModSecurity log; whitelist the specific rule for
   `/api/inquiries` (do not disable the WAF globally).
7. **PHP version/lsapi mismatch** → 403 with an lsapi error page: re-select
   PHP ≥ 8.2 for the domain (§2).
8. After each fix: re-run smoke tests 1, 2, 9, 18 (§27) before moving on.

## 30. Database safety rules

**ABSOLUTE — never run on the Hostinger production database:**

```
php artisan migrate:fresh          # DROPS ALL TABLES — the 2026-09-25 incident command
php artisan migrate:refresh       # rollback + re-migrate = data loss
php artisan db:wipe               # drops all tables
php artisan migrate:rollback      # only as §28's last resort, never in deploys
```

**The only permitted schema command in production:**

```
php artisan migrate --force
```

Run it **only after verifying the target database**:
1. `php artisan tinker`-free check first — `php artisan db:show` (Laravel 12)
   or `SELECT DATABASE();` via the panel's phpMyAdmin on the configured
   connection: confirm it reports `u123456789_permetheon` (NOT a local/test
   name, NOT empty-of-expectations when re-deploying).
2. Confirm `.env` `DB_DATABASE` matches the hPanel database name
   character-for-character (prefix included).
3. New migrations must be **additive** (create table / add column with
   defaults); review `database/migrations/` diff before every deploy; any
   migration that would drop/rename/columns-with-data-loss is forbidden
   without a documented, backed-up, owner-approved plan.
4. Tests never touch production: `phpunit.xml` pins
   `DB_DATABASE=permetheon_test` locally; **never create permetheon_test or
   permetheon_e2e on Hostinger**, never run `php artisan test` or the E2E
   launcher with production creds; the E2E launcher only ever touches the
   throwaway `permetheon_e2e` (created AND dropped by itself).
5. `php artisan db:seed --force` is permitted (idempotent singleton upsert;
   it does not create admin accounts — the first admin comes from the
   one-time bootstrap login, §23 step 3).
6. Take a `mysqldump`/phpMyAdmin export backup **before every deploy that
   includes migrations**; store it outside the web root
   (`~/backups/`, never `public_html/`).

---

## Deliverable summaries

### Exact Hostinger folder structure

```
/home/u123456789/
├── permetheon-app/                    (not web-accessible)
│   ├── backend/
│   │   ├── app/  bootstrap/  config/  database/  public/  resources/
│   │   ├── routes/  storage/  tests/  vendor/
│   │   ├── .env            (640, server-only)
│   │   ├── artisan  composer.json  composer.lock  phpunit.xml
│   │   └── public/
│   │       ├── index.php   ├── .htaccess
│   │       ├── favicon.ico └── robots.txt (SEO-rules copy)
│   └── frontend/
│       └── dist/           (full built SPA — REQUIRED sibling of backend/)
└── <doc root>              (hPanel points here)
    └── → backend/public    (symlink preferred; copy variant §7-B fallback)
```

### Exact files that must be uploaded

- `backend/**` — everything except: `.env` (create on server), `node_modules/`,
  `storage/logs/*` contents, `storage/framework/{sessions,views,cache}/data`
  contents (empty dirs must exist), `.git*`, `tests/` may be omitted (never
  web-reachable either way).
  Include: `vendor/` (if shipping pre-built) or run composer on the server.
- `frontend/dist/**` — the entire built directory (12 pages, admin-shell,
  assets, og, screenshots, static-loader-data, sitemap.xml, robots.txt).
- Explicitly **verify after upload** (dotfiles get lost): `backend/public/.htaccess`
  is present in the served doc root; `backend/.env` is NOT in any web path.
- Do **not** upload: `docs/`, `scripts/`, `frontend/src/`, `frontend/public/`
  (its contents are already inside `dist/`), old `legacy/`, any `*.md`, any
  local `.env*`.

### Exact environment variables required (server `backend/.env`)

`APP_NAME, APP_ENV=production, APP_KEY, APP_DEBUG=false,
APP_URL=https://permetheon.com, APP_LOCALE, APP_FALLBACK_LOCALE,
BCRYPT_ROUNDS, LOG_CHANNEL=stack, LOG_STACK=single, LOG_LEVEL=warning,
DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD,
CACHE_STORE=database, QUEUE_CONNECTION=database, SESSION_DRIVER=database,
SESSION_LIFETIME, SESSION_ENCRYPT=false, SESSION_PATH=/, SESSION_DOMAIN=null,
SESSION_SECURE_COOKIE=true, BROADCAST_CONNECTION=log, FILESYSTEM_DISK=local,
ADMIN_BOOTSTRAP_EMAIL, ADMIN_BOOTSTRAP_PASSWORD (temp only — remove after
first login), INQUIRY_RATE_LIMIT_MAX=5, MEETING_INQUIRY_DELETE_POLICY,
TRUSTED_PROXIES (post-deploy, §13a), MAIL_MAILER=log, MAIL_FROM_ADDRESS,
MAIL_FROM_NAME`

### Exact deployment commands

See §23 (first deploy + updates) — the authoritative sequence; §25 for the
no-SSH fallback; §24 for the git flow. Never deviate from §30 for schema.

### Exact verification URLs/routes

`/up` · `/` · `/work` · `/services` · `/process` · `/about` · `/contact` ·
`/case-studies` · `/case-studies/pct` · `/case-studies/tbms` ·
`/case-studies/estatehub` · `/case-studies/travelnest` · `/admin` ·
`/admin/login` · `/api/meetings/availability` · `/api/inquiries` (POST) ·
`/api/admin/auth/login|session|refresh|logout` · `/api/admin/inquiries` ·
`/sitemap.xml` · `/robots.txt` · one intentionally-unknown path (must 404) ·
`/.env` (must 403/404). Full ordered checklist: §27.

### Exact rollback procedure

§28 — previous-artifact redeploy + `config:cache`/`route:cache`; dist swap
for frontend; `.env` restore; `migrate:rollback` only as a backed-up last
resort; `php artisan down` as the brake.

### Known risks

1. **Doc-root/403 risk (highest):** hPanel may restrict doc-root paths; the
   copy variant (§7-B) exists but needs exact `index.php` path edits — test
   `/up` before DNS/production traffic.
2. **Dotfile loss on upload:** `.htaccess` and `.env` handling are the classic
   silent failure — verify presence (§26, §27-18).
3. **TRUSTED_PROXIES granularity:** without it, shared-host proxying can put
   all clients in one limiter bucket (limit still enforced; granularity
   wrong). Fix after first deploy per §13a.
4. **No server-side build:** forgetting to rebuild/upload `dist/` after UI
   changes ships stale frontend (no build errors — just old pages). Make the
   dist upload part of every release.
5. **HTML 500 envelope:** unhandled API errors return Laravel's HTML error
   page (disclosed nothing during the security run, APP_DEBUG=false). Hardening
   to the JSON envelope is a known follow-up, not a blocker.
6. **Bootstrap-window exposure:** the one-time admin bootstrap password lives
   in `.env` until first login — minimize the window and remove it
   immediately (§23 step 3).
7. **Cron-less queueing:** if queue features are ever added, they silently
   won't run without the §22 cron — flagged for future work.
8. **Shared-host rate-limit ceiling:** Hostinger enforces its own entry-process
   limits; a traffic spike may 508/429 at the panel level before Laravel's
   limiter — acceptable for current traffic, revisit on growth.
