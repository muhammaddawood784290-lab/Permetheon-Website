# Permetheon Website V3 — React + Vite + Laravel + MySQL

The Permetheon V3 public website, admin portal, business-inquiry system and
meeting/calendar system as a fully independent **React 19 + TypeScript + Vite**
frontend and **Laravel 12 (PHP 8.2+) + MySQL/MariaDB** backend.

- The frontend is the existing V3 design system, unchanged — same typography,
  spacing, layout, navigation, animations and responsive behavior.
- The backend is the authoritative validation, authorization and business-logic
  layer (Laravel API + migrations + seeders).
- All times in the meeting system are **UTC** (ISO 8601, `Z` suffix). No
  geographic timezone is used or hardcoded anywhere.
- The app database is **MySQL/MariaDB only**. SQLite is not supported or
  referenced anywhere in the application code.

## Project structure

```
Permetheon website V3 PHP/
├── frontend/     React 19 + TypeScript + Vite (+ vite-react-ssg prerender)
│   ├── src/          pages, components, lib (API + validation contract)
│   ├── public/       screenshots, OG images, robots.txt, fonts
│   ├── scripts/      sitemap / admin-shell / prerender verification
│   └── dist/         production build output (npm run build)
├── backend/      Laravel 12 API — serves the built SPA from ../frontend/dist
│   ├── app/          controllers, models, middleware, services
│   ├── routes/       api.php (the JSON contract) + web.php (SPA host)
│   ├── database/     migrations, seeders
│   └── tests/        Pest suite (94 tests)
├── scripts/      E2E suites + isolated E2E server launcher
├── docs/         historical project documentation
└── README.md
```

## Development commands (10-step guide)

Prerequisites: PHP 8.2+ with `pdo_mysql`, Composer, Node.js 20+, and a running
MySQL/MariaDB server (XAMPP's MariaDB on 127.0.0.1:3306 was used to verify this
project).

### Terminal 1 — backend

1. **Install PHP dependencies**

   ```bash
   cd backend
   composer install
   ```

2. **Configure MySQL** — create the application database (adjust
   credentials in the next step if you do not use root):

   ```sql
   CREATE DATABASE permetheon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Configure Laravel `.env`** — copy the template, then set
   `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` for your MySQL server:

   ```bash
   cp .env.example .env
   ```

4. **Generate the app key, run migrations and the seeder**

   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   ```

   The seeder creates the meeting-availability singleton row. The first admin
   account is **not** seeded: set `ADMIN_BOOTSTRAP_EMAIL` and
   `ADMIN_BOOTSTRAP_PASSWORD` in `.env`, log in once at `/admin/login`, and the
   bootstrap password is permanently wiped (it can never mint a second
   account).

5. **Start Laravel**

   ```bash
   php artisan serve
   ```

   → http://localhost:8000 (also serves the built frontend in production —
   see step 10).

### Terminal 2 — frontend

6. **Install frontend dependencies**

   ```bash
   cd frontend
   npm install
   ```

7. **Start Vite**

   ```bash
   npm run dev
   ```

8. **Open the Vite development URL** — http://localhost:5173

   In dev, `/api/*` is proxied to the Laravel server (vite.config.mts,
   `LARAVEL_API_URL`, default `http://127.0.0.1:8000`), so the site and the API
   are same-origin and CSRF cookies work unchanged.

9. **Build the frontend**

   ```bash
   npm run build
   ```

   → `frontend/dist/` — prerendered HTML for all 11 public routes plus the
   admin shell, hashed assets, sitemap.xml. `verify-prerender` runs ~140 SEO
   checks as part of the build and fails loudly on regressions.

10. **Run the production preview** (optional sanity check)

    ```bash
    npm run preview
    ```

    For the real single-origin deployment, Laravel serves `frontend/dist`:
    start Laravel (step 5) and visit http://localhost:8000 after building —
    pretty-URL fallback serves the prerendered pages, client-only routes
    (`/admin`, `/system`) get the empty shell, unknown paths return a real 404.

## Environment variables

### backend/.env

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL/MariaDB connection (mysql only) |
| `CACHE_STORE` | Must be `database` — the rate limiters persist counters there (an array store silently disables them) |
| `QUEUE_CONNECTION` / `SESSION_DRIVER` | `database` (framework completeness; admin auth uses its own table) |
| `ADMIN_BOOTSTRAP_EMAIL` / `ADMIN_BOOTSTRAP_PASSWORD` | One-time first-admin bootstrap; the password is wiped after first use |
| `INQUIRY_RATE_LIMIT_MAX` | Public inquiry submissions per 10 min per IP (default 5) |
| `MEETING_INQUIRY_DELETE_POLICY` | `delete_meeting` (default) or `prevent` — see `config/meetings.php` |
| `APP_DEBUG` | **false** in any shared or production environment |

### frontend/.env*

| Variable | Purpose |
| --- | --- |
| `VITE_API_BASE` | Base URL for API calls. Production default `""` (same-origin relative URLs — no hardcoded hosts). Dev uses the Vite proxy, also same-origin. |

## Testing

```bash
# backend: full Pest suite (94 tests) against MySQL (permetheon_test)
cd backend && php artisan test

# frontend: TypeScript
cd frontend && npx tsc --noEmit

# frontend: production build incl. ~140 prerender SEO checks
cd frontend && npm run build
```

## E2E suites (isolated — never touches `permetheon`)

```bash
node scripts/e2e-laravel-server.mjs      # boots :3102 with a throwaway MySQL DB
node scripts/e2e-inquiry.mjs             # 41 checks: validation, E.164, CSRF, authz…
node scripts/e2e-meetings.mjs            # 46 checks: availability, races, policy…
node scripts/e2e-laravel-server.mjs --stop   # stops the server, drops the throwaway DB
```

The launcher creates `permetheon_e2e`, migrates + seeds it fresh, raises
`INQUIRY_RATE_LIMIT_MAX`, and drops the database on `--stop`. MySQL client
discovery: `MYSQL_CLI` env var or the standard XAMPP / MySQL install paths.

## Documentation

- `docs/SECURITY_FINDINGS_CARRYOVER.md` — security assessment findings and the
  fixes applied in this project.
- `docs/` — historical project documentation (preserved as written; it
  describes earlier architectures and is not executable code).
