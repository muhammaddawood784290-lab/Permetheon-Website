# MIGRATION MAP — Next.js App Router → React 19 + Vite (frontend) · Laravel 12 + MySQL (backend)

> Status: PLAN v2 — reviewed and amended after a critical pass (§2B-13/14, §3, §5-7/8/9).
> **IMPLEMENTATION STARTED (2026-09-24):** the Pest parity test suite (§4b rows + G1–G9)
> is WRITTEN AND GREEN — 68 tests, 306 assertions — against a minimal Laravel 12 skeleton
> in `skeleton/` (custom guard, migrations, E164 rule, FormRequests, controllers,
> middleware, rate limiters; SQLite :memory: for the suite, MySQL-ready schema).
> Run: `cd skeleton && ./vendor/bin/pest`. The frontend (Vite) migration has NOT started.
> No code changed yet outside `skeleton/` and this document. Produced from a full read of
> the current implementation (every file under `src/app`, `src/components`, `src/lib`,
> `src/data`, `database/`, `tests/`, `scripts/`). Target: preserve the existing
> UI/design, behavior, routes, SEO and admin functionality 1:1. No visual redesign.

---

## 0. What the current system is (verified inventory)

- **Frontend:** Next.js 15.4 App Router, React 19, TypeScript 5.8 (strict), Tailwind CSS 4
  (CSS-first `@theme` tokens in `src/app/globals.css`), `next/font` (Space Grotesk),
  `output: 'export'` in `next.config.mjs`.
- **Backend:** Next.js Route Handlers (`src/app/api/**`) on the Node.js runtime, SQLite via
  `node:sqlite` (`src/lib/admin/db.ts`), hand-rolled migrations, scrypt password hashing,
  server-side sessions, CSRF double-submit, in-memory rate limiting, first-login super-admin
  bootstrap. A parallel MySQL DDL exists at `database/mysql/001_business_inquiry_system.sql`.
- **Public routes (7 static + 4 dynamic case studies):** `/`, `/work`, `/case-studies`,
  `/case-studies/[slug]`, `/services`, `/process`, `/about`, `/contact`, `/system`
  (internal review page, noindex).
- **Admin routes:** `/admin` (dashboard, server-gated), `/admin/login`.
- **API endpoints:** `POST /api/inquiries`; admin: `POST /api/admin/auth/login`,
  `POST /api/admin/auth/logout`, `GET /api/admin/auth/session`,
  `GET|POST /api/admin/inquiries`, `GET|PATCH /api/admin/inquiries/[id]`,
  `GET|POST /api/admin/inquiries/[id]/notes`, `GET /api/admin/inquiries/stats`.
- **SEO machinery:** per-page `Metadata` objects, title template `%s | Permetheon` in the root
  layout, canonicals + OpenGraph via `src/lib/seo.ts`, `sitemap.ts` (static routes + case
  studies; `/system` excluded), `robots.ts` (blocks `/system`, `/admin*`, `/api/admin`),
  runtime-generated OpenGraph images (`next/og`) for the site and each case study.
- **Tests:** `tests/contract.test.ts` (21 tests, Node test runner, exercises validation +
  SQLite persistence), `scripts/e2e-inquiry.mjs` (HTTP lifecycle E2E).

---

## 1. Migration map — retain / rewrite / replace

### 1A. RETAINED AS-IS (copy into the Vite app, no behavior change)

| Current file(s) | Destination | Notes |
|---|---|---|
| `src/app/globals.css` | `frontend/src/styles/globals.css` | Tailwind 4 `@theme` tokens are framework-agnostic. Two edits: the `@import "tailwindcss"` stays; the `--font-display` var (injected by `next/font`) gets its variable from a `@fontsource`-style self-hosted font instead. |
| `src/data/projects.ts` | `frontend/src/data/projects.ts` | Pure data + types. Unchanged. |
| `src/data/case-studies.ts` | `frontend/src/data/case-studies.ts` | Pure data + types. Unchanged. |
| `src/lib/inquiries.ts` | `frontend/src/lib/inquiries.ts` **and** `backend/.../InquiryContract.php` port | The TS copy keeps validating client-side in the form (same shared contract); the server rules get re-expressed in Laravel Form Requests with IDENTICAL messages/boundaries so the 422 field-error contract does not change. |
| `src/components/Button, Badge, Card, Container, CTABlock, SectionHeading, BrowserMockup, ImageFrame, project/*` (all server components, no Next APIs) | `frontend/src/components/…` | Only change: `Button`'s internal `next/link` → `react-router-dom` `<Link>` (drop-in, same props used). Everything else byte-identical. |
| `src/components/InquiryForm.tsx` | `frontend/src/components/InquiryForm.tsx` | Change: remove `"use client"`; `fetch("/api/inquiries")` → `fetch(apiUrl("/api/inquiries"), …)` where `apiUrl()` uses `VITE_API_BASE`, which **defaults to `""` (same-origin)** and is only set to `http://localhost:8000` in dev. Validation logic, statuses, error mapping, copy unchanged. |
| `src/components/admin/AdminDashboard.tsx` | `frontend/src/components/admin/AdminDashboard.tsx` | Remove `"use client"`. API paths get the same `VITE_API_BASE` prefix. All fetch/CSRF/UX logic unchanged. |
| `src/components/admin/AdminLoginForm.tsx` | `frontend/src/components/admin/AdminLoginForm.tsx` | `useRouter` (next/navigation) → `useNavigate` (react-router). Otherwise unchanged. |
| `src/components/Navbar.tsx`, `ScreenshotCarousel.tsx` | same paths | `next/link` → router `Link`, `usePathname` → `useLocation`. Scroll/mobile-menu/carousel logic unchanged (animations preserved). |
| `public/screenshots/**`, `public/` static assets | `frontend/public/…` | Verbatim copy. |
| `docs/**` | untouched | Documentation stays authoritative. |

### 1B. REWRITTEN (same behavior, new framework)

| Current | Replacement | Behavior contract to preserve |
|---|---|---|
| `src/app/layout.tsx` + `(site)/layout.tsx` + `(admin)/layout.tsx` | `frontend/src/App.tsx` (RouterProvider + layout routes); root `<html>`/`<body>` chrome moves to `frontend/index.html` | Skip-link, font variables, metadata are re-expressed per-page (see SEO row). Admin routes live outside the public chrome, exactly as the route-group layout does today. |
| Pages: `page.tsx` × 11 | `frontend/src/pages/*.tsx` | JSX is reused nearly verbatim; `next/link` → router `Link`; the two client pages (`work` filters, contact form) keep their state logic. Static pages are prerendered at build (see §3). |
| `src/app/sitemap.ts` | `frontend/scripts/generate-sitemap.mjs` (build step, run with `vite build`) | Same URL set: 7 static routes + 4 case-study routes, `/system` excluded, same priorities. **Note:** `lastModified: new Date()` today changes on every build; keep that (it's harmless) rather than inventing a git-based date. |
| `src/app/robots.ts` | `frontend/public/robots.txt` (static) + Laravel catch-all fallback | Same rules: allow `/`; disallow `/system`, `/admin`, `/admin/`, `/api/admin`; sitemap URL identical. |
| Root + per-page `Metadata` / `pageMetadata()` | `frontend/src/lib/seo.ts` — a per-route page-meta table consumed **only by the build-time prerender** (§3). No runtime head manipulation at all: indexed pages get their `<title>`/canonical/OG tags baked into the prerendered HTML; admin/system pages set `document.title` in a tiny effect (they're noindex anyway). | Title template `%s | Permetheon` preserved exactly (incl. the contract-test rule that data never pre-appends the suffix); canonical URLs identical; OG image routes become static PNGs (see 1C). |
| `src/app/opengraph-image.tsx` + `[slug]/opengraph-image.tsx` | Pre-generated static PNGs (`frontend/public/og/{index,pct,tbms,estatehub,travelnest}.png`), rendered once from the same design via a one-off script, referenced by OG meta | Same 1200×630 text-only cards, same colors/copy. |
| API routes `src/app/api/**` (8 route files) | Laravel 12 controllers + routes (`backend/routes/api.php`) — 1:1 endpoint map in §2 | Status codes, JSON envelope `{success, data}` / `{success, error:{code,message,fields}}`, validation messages, rate limits, CSRF, cookies — all identical. |
| `src/lib/admin/*` (session, csrf, guards, http, rateLimit, password, bootstrap, repositories, permissions, migrations) | Port to Laravel as a **fully custom guard** — do NOT engage Laravel's session machinery (`SESSION_DRIVER`/`StartSession`/`EncryptCookies`) at all; keep the own `admin_sessions` table, `hash_equals` CSRF double-submit, permission middleware, `Cache`-based RateLimiter, `Hash::make` (bcrypt — replaces scrypt, see 2B-4), bootstrap seeder, Laravel migrations for the 5 SQLite migrations | The behavioral rules (30-min idle sliding timeout, 24-h absolute expiry, hashed session tokens, lockout 5 fails/15 min, generic login errors, rate-limit windows 5/10 min public + 10/10 min login + 120/min admin reads) are carried over exactly. |
| `tests/contract.test.ts` | `frontend` — port the pure-validation/head/tag tests to Vitest; `backend` — port persistence tests to Pest/PHPUnit against MySQL | Same 21-behavior coverage, split by layer. |
| `scripts/e2e-inquiry.mjs` | Same script, `BASE` pointed at the Laravel host (or keep both, env-driven) | Same lifecycle assertions. |
| `package.json` (next/react scripts) | Two package.json: `frontend/` (vite, react, react-dom, react-router-dom, tailwindcss) and `backend/` (composer: laravel/framework 12) | No Next.js, no Node server runtime anywhere in production. |

### 1C. REPLACED / DROPPED (Next-specific, no replacement needed or allowed)

| Current | Why dropped | What covers the need |
|---|---|---|
| `next.config.mjs` (`output: 'export'`) | Not applicable | Vite build; Laravel serves API + SPA fallback. |
| `next/font` (Space Grotesk loader) | Next-only | Self-hosted font via `@fontsource/space-grotesk` + `@fontsource/inter`, same `--font-display-family` variable. |
| `next/link`, `usePathname`, `useRouter`, `redirect()` | Next-only | `react-router-dom` v7 (`Link`, `useLocation`, `useNavigate`, `<Navigate>` for the auth gate). |
| Server Component gating of `/admin` (`getAuthContext()` server-side + redirect) | No server runtime in the SPA | `/admin` becomes a client route with a session guard: `GET /api/admin/auth/session` on mount → `<Navigate to="/admin/login">` when 401. The **authoritative** checks stay server-side on every API route (unchanged) — the client gate is only UX, same trust model as today. |
| `MetadataRoute` types | Next-only | Plain TS helpers in the new `seo.ts`. |
| `node:sqlite` + custom migrations | Explicitly banned target | MySQL schema (below). |
| `process.env` at runtime (rate-limit max, bootstrap creds) | Node-only | Laravel `.env` config. |

---

## 2. Laravel 12 backend design

### 2A. Route map (1:1, same paths, same verbs)

| Current Next route | Laravel route | Controller |
|---|---|---|
| `POST /api/inquiries` | same | `Public\InquiryController@store` |
| `POST /api/admin/auth/login` | same | `Admin\AuthController@login` |
| `POST /api/admin/auth/logout` | same | `Admin\AuthController@logout` |
| `GET /api/admin/auth/session` (+HEAD) | same | `Admin\AuthController@session` |
| `GET /api/admin/inquiries` | same | `Admin\InquiryController@index` |
| `POST /api/admin/inquiries` → 400 | same | `Admin\InquiryController@createBlocked` |
| `GET /api/admin/inquiries/{id}` | same | `Admin\InquiryController@show` |
| `PATCH /api/admin/inquiries/{id}` | same | `Admin\InquiryController@update` |
| `GET|POST /api/admin/inquiries/{id}/notes` | same | `Admin\NoteController@index` / `@store` |
| `GET /api/admin/inquiries/stats` (+POST → 400) | same | `Admin\StatsController` |
| SPA pages (any non-API GET) | Laravel serves the Vite build (`public/` app) + explicit `/admin`, `/admin/login` fallbacks to `index.html`. Since every indexed route is prerendered, the fallback should 404 for truly unknown paths (matching `notFound()` today) rather than silently serving the homepage — a catch-all to `index.html` would create soft-404s. | — |

### 2B. Behavior preservation (the rules that must not change)

1. **Validation contract.** Every boundary in `src/lib/inquiries.ts` is re-expressed in a
   Laravel FormRequest with byte-identical error messages (verified against
   `tests/contract.test.ts`): name 2–100 + ≥1 letter + markup ban; company ≤150; email
   format + ≤254 + lowercase; contactNumber → E.164 normalize (strip `[\s().[]{}\-–—−]`,
   `00` → `+`) + ITU calling-code table + NANP/+7 fixed lengths, message
   "Enter a valid international contact number, e.g. +12025550147."; projectType enum
   (7 options); budget/timeline enums; message 20–5000 + markup ban. Unknown/privileged
   fields (`status`, `priority`, `role`, `id`, …) are silently dropped
   (mass-assignment protection §26) — `$request->only([...canonical 8])`.
2. **Response envelope + status codes.** 201 `{id,status:"NEW",createdAt}` · 422
   `{error:{code:"VALIDATION_ERROR", fields}}` · 400 malformed/oversized · 429 rate-limited ·
   500 generic. Envelope via a small `ApiResponse` helper mirroring `src/lib/admin/http.ts`.
3. **Rate limiting.** Laravel `RateLimiter`: `inquiry:{ip}` 5/10min (env-tunable, same
   `INQUIRY_RATE_LIMIT_MAX` semantics), `login:{ip}` 10/10min, `admin-read:{admin}:{ip}`
   120/60s.
4. **Passwords.** `Hash::make()` (bcrypt). Note: existing SQLite hashes (scrypt format) are
   not portable; there are no real accounts to migrate (bootstrap-on-first-login creates the
   only admin), so the Laravel system re-bootstraps with `ADMIN_BOOTSTRAP_EMAIL/PASSWORD`
   env + a seeder implementing the same wipe-after-first-use rule.
5. **Sessions.** Custom guard only — Laravel's own session machinery (`StartSession`,
   `SESSION_DRIVER`, session cookies) is NOT engaged for any route. Session token = 32 random
   bytes (base64url); DB stores SHA-256 hash only in the `admin_sessions` table; cookie
   `admin_session` HttpOnly + SameSite=Strict + `secure` in production; sliding 30-min idle
   timeout (touch `last_seen_at`) + 24-h absolute expiry; idle-expiry revokes the row; logout
   revokes + clears cookie. A custom guard + middleware reproduces the current model exactly —
   two session systems running side by side would be a bug magnet.
6. **CSRF.** Double-submit: `admin_csrf` cookie (readable, SameSite=Strict) must equal
   `x-csrf-token` header via `hash_equals` on all authenticated mutations; login also checks
   `Origin` host match (same as `assertSameOrigin` today). Laravel's own web-CSRF is not used
   for these stateless JSON APIs — the existing contract is preserved instead.
7. **Permissions.** Role→permission map identical (`permissionsForRole`); middleware
   `permission:inquiries.read` etc. Frontend visibility stays non-authoritative.
8. **Lockout.** 5 failed logins → 15-min lock; generic "Invalid email or password." for all
   failure modes (locked, unknown email, inactive, wrong password) — no enumeration.
9. **MySQL schema.** Convert the 5 SQLite migrations + the existing
   `database/mysql/001_business_inquiry_system.sql` into Laravel migrations with the same
   columns, CHECK constraints (MySQL 8 `CHECK`), indexes, and the `contact_number`
   `VARCHAR(16)` + E.164-shape rule; `_migrations` → Laravel's `migrations` table.
   `business_inquiries.contact_number` default `''`, status/priority enums as in the SQL.
10. **Doctrine-free queries.** Eloquent models (`BusinessInquiry`, `AdminUser`, `AdminSession`,
    `InquiryNote`) with the same search semantics (`LIKE %…%` across name/email/company/
    contact_number/message), `ORDER BY created_at DESC`, page/pageSize 10, stats aggregation
    incl. `last30Days` cutoff.
13. **PHP port of the shared contract is a fork, not a shared source.** The TS `inquiries.ts`
    keeps serving the client; the PHP FormRequest re-expresses the same rules. Mitigation:
    the Pest test table is generated from (or hand-mirrored 1:1 with) the TS boundary table
    in `tests/contract.test.ts`, including exact error-message equality, so divergence is a
    failing test, not a silent drift.
14. **`created_at` ordering ties.** SQLite stored ISO strings; MySQL `DATETIME` columns will
    store the same ISO-8601 strings (Laravel casts handle this) — but the E2E suite creates
    rows within the same second, where `created_at DESC` ties become possible. Keep
    `id` (UUIDv4) as a deterministic tiebreaker or use `DATETIME(3)`; decide at
    implementation and pin it in the Pest tests.
11. **CORS.** Dev-only `localhost:5173` allowed for the Vite dev server (with `supports_credentials`
    for the cookie flow); production serves same-origin (Laravel serves the built SPA), so no
    public CORS surface.
12. **Body-size guard.** 64 KB cap on public submissions (middleware) as today.

### 2C. Repository layout

```
frontend/                  # React 19 + TS + Vite (no Next)
  index.html               # <html> chrome, fonts, skip-link target
  src/
    main.tsx               # RouterProvider, routes, auth guard
    App/…routes            # layout routes mirroring (site) and (admin) groups
    pages/…                # 11 pages (verbatim JSX, router links)
    components/…           # verbatim + react-router links
    lib/inquiries.ts       # SHARED validation contract (verbatim)
    lib/seo.ts             # per-route page-meta table consumed by the prerender
    lib/api.ts             # fetch wrapper: apiUrl() (VITE_API_BASE, defaults to same-origin)
  scripts/prerender.mjs    # or vite-react-ssg — bakes head tags + full HTML per route
  public/                  # screenshots, robots.txt, og/*.png
backend/                   # Laravel 12 (api + serving the built SPA)
  app/Http/Controllers/…
  app/Http/Middleware/…    # auth, permission, csrf-double-submit, body-size
  app/Rules/E164PhoneNumber.php
  database/migrations/…    # MySQL schema
  routes/api.php
  tests/…                  # Pest ports of the contract suite
```

---

## 3. Rendering/SEO strategy for the SPA (preserving current SEO guarantees)

The current site is statically exported HTML — crawlers get full content. To preserve that:

- **Prerender at build:** `vite-react-ssg` renders all 7 static pages + 4 case-study pages
  to static HTML at build time with the same `<title>`, canonical, OG/Twitter tags the Next
  build produced. Client hydration then takes over for interactivity (work filters, form,
  carousel). This matches today's `output: 'export'` behavior most closely. DECISION:
  `vite-react-ssg` over `vite-plugin-prerender` (which needs Puppeteer/Chrome in CI) —
  validate the hydration contract early in step 3, because SSG libraries are the least
  battle-tested piece of this migration; if it fights the carousel/work-filter components,
  fall back to a tiny custom prerender script (renderToString + inject into the Vite
  template) rather than adopting Puppeteer.
- **`/system`, `/admin`, `/admin/login`** are excluded from prerender (admin/system are
  client-only + noindex meta, as today's `robots: {index:false}`).
- **sitemap.xml** generated at build with the identical route set; **robots.txt** static.
- **Case-study OG images** become the 5 pre-generated PNGs.
- **Laravel catch-all** serves `index.html` for unknown GETs (direct hits to deep links).

---

## 4. Migration order (each step independently verifiable)

1. **Scaffold** `frontend/` (Vite + React 19 + TS + Tailwind 4) and `backend/` (Laravel 12).
2. **Copy retained assets** (§1A): data files, tokens/globals.css, stateless components,
   shared `inquiries.ts`. Swap `next/link` → router `Link` mechanically.
3. **Rebuild pages + routing** (§1B): layouts, 11 pages, work-page filter state, contact
   form against a stubbed API base; prerender config; sitemap/robots/OG generation.
4. **Rebuild backend** (§2): migrations → models → FormRequests → controllers → middleware
   → rate limiters → bootstrap seeder → CORS → SPA fallback.
5. **Port tests:** Vitest (frontend validation/head) + Pest (backend contract, run against
   MySQL); keep `scripts/e2e-inquiry.mjs` working against the Laravel server.
6. **Cut over tooling:** root README/docs build instructions; remove Next deps; delete
   `src/app` Next implementation only after parity is verified.

---

## 4b. PARITY CHECKLIST — every behavior the current tests pin, and its migration fate

Produced from a line-by-line review of `tests/contract.test.ts` (21 tests) and
`scripts/e2e-inquiry.mjs`. This is the acceptance gate: the migration is not done until
every row below has a green test in the new stack.

### Pinned by contract tests → where it lands

| # | Pinned behavior | New stack owner | Gap found in review |
|---|---|---|---|
| 1 | Optional fields normalize `""` → `null` (company/budget/timeline) | PHP FormRequest (server) + TS (client) | **Map gap closed:** PHP must return JSON `null`, not `""`. Laravel `$request->boolean()`-style casting won't do it — explicit normalization needed. Pin in Pest. |
| 2 | Trim company, lowercase email | PHP | Easy to miss in Laravel because `trim`/`strtolower` aren't automatic. Pin in Pest. |
| 3 | Boundary table: name 2–100, ≥1 letter, company ≤150, email ≤254 + format, message 20–5000, projectType/budget/timeline enums — **each violation fires EXACTLY one field error** | PHP FormRequest | The "exactly one field" assertion is the sneaky one: Laravel's default validator reports ALL failing fields. The port must keep per-field independence (a bad name must not also emit a contactNumber error). Pest table must assert `assertCount(1, array_keys($errors))`. |
| 4 | Exact error-message strings ("Please tell us your name.", etc.) | PHP | **Byte-identical messages required.** Added to 2B-13: the Pest table carries the same message literals as the TS table. |
| 5 | Contact number: normalize (spaces/hyphens/dots/brackets, `00`→`+`), reject no-`+`, reject unassigned calling codes, reject >15 digits, NANP fixed 10-digit national length, country code never guessed | PHP `E164PhoneNumber` rule | The ITU code table must be copied verbatim (it's data). NANP `+1` must require exactly 10 national digits — a naive `digitsBetween` won't. |
| 6 | Enum vocabularies deep-equal (7/6/6 options; statuses ×7; priorities ×4; `DEFAULT_PRIORITY=MEDIUM`) | PHP + shared data | Vocabularies live in TS data today; PHP needs its own copy — Pest must assert deep equality so option lists can't drift. |
| 7 | Mass-assignment: hostile payload (`status/priority/role/id/createdAt/adminId/permissions`) → only the canonical 8 keys survive | PHP `$request->only()` | Must assert the **returned value's key set**, not just that status is NEW — forged `createdAt` must also be dropped. Pest pins key list. |
| 8 | Non-record payloads (`null/42/"string"/[array]`) → `form` error, not a 500 | PHP | Laravel type-hints can 500 on these; the FormRequest must catch and return the 422 `form` field. Pin all five payload types. |
| 9 | Persistence: create → server-set NEW/MEDIUM, `createdAt === updatedAt` on creation, verbatim message, E.164 persisted, fetchable | Pest + MySQL | `createdAt === updatedAt` at creation means timestamps need millisecond equality — see 2B-14 (`DATETIME(3)`). |
| 10 | Pagination honest: 10/page, total count, page 2 remainder, newest-first | Pest + MySQL | Newest-first with same-second inserts → 2B-14 tiebreaker. |
| 11 | Search: by email substring, name prefix ("Bulk Tester 1" → 3 hits incl. 10/11 — i.e., **numeric-aware substring, not prefix**), exact E.164 contact number | Pest + MySQL | MySQL `LIKE` is **case-insensitive by default collation** (utf8mb4_unicode_ci) — same net effect as today, but the test must pin case-insensitivity explicitly so a future `_bin` collation change fails loudly. |
| 12 | `updateInquiry` patches status/priority, never touches message | Pest | — |
| 13 | Notes: author join (`adminName`), append-only, never overwrite inquiry | Pest | FK + join must resolve — MySQL needs the `admin_users` row before note creation (test seeds one). |
| 14 | Stats: real aggregates, `byStatus` complete map, `last30Days ≤ total` | Pest | `last30Days` cutoff is 30×24h from **now** — the test's fixed counts require same-run data; keep the Pest equivalent time-relative. |
| 15 | SEO: `%s \| Permetheon` template, canonical = `SITE_URL + path`, OG title suffixed once, **data never pre-appends the suffix**, `siteUrl()` no double slash at root | Vitest over the prerender output | Port asserts against **generated HTML** (`<title>`, `<link rel=canonical>`, `og:title`), not against a `pageMetadata()` return value — the mechanism changed from Next Metadata to baked tags. Same assertions, different surface. |
| 16 | Work filters: fixed 7 categories, all project filters ∈ list, visible-project rule (incl. empty "Portals" honest empty state) | Vitest | Data file copied verbatim → keep test verbatim. |
| 17 | Showcases: PNG magic bytes on disk, path ownership `^/screenshots/{slug}/`, exact story order per study, caption prefixes, no claim language | Vitest | Test reads `public/` relative to repo root — **file layout change**: the Vitest port must read `frontend/public/` and the regex stays identical. |
| 18 | B-002 honesty: exactly 4 studies with showcases, exactly 4 projects with previews | Vitest | — |
| 19 | Form integrity: exactly 8 `name=` attributes in order, "Contact Number / WhatsApp" label, no `phone/whatsapp/country` inputs | Vitest | Source-read test — same assertion, new path (`frontend/src/components/InquiryForm.tsx`). |

### Pinned by the E2E script → Laravel acceptance gate

| # | Pinned behavior | Gap found in review |
|---|---|---|
| 20 | 201 → `{data:{id,status:"NEW"}}`; 422 → per-field errors; 400 on `not-json` body; **400 on oversized body** | The 64 KB body guard (2B-12) is asserted nowhere in the E2E today — **add a case** posting >64 KB and asserting 400 in the Laravel E2E port. |
| 21 | Phone-injection probes: `whatsappNumber`/`phone`/`contact_phone` fields are silently ignored, and **the detail row contains no shadow fields** | Pin the response key set (row 7 of the contract table) — this E2E check maps to the same fix. |
| 22 | `/admin` unauthenticated → **307 redirect to `/admin/login`** | ⚠️ **Behavior change flag:** today this is a Next server redirect; the SPA port serves `/admin` as a page and redirects client-side. The E2E check `r.status === 307` **cannot pass** against the SPA. Decision: the Laravel fallback returns the prerendered `/admin` shell (200) and the client guard `<Navigate>`s to login; the E2E port replaces this check with "`/admin` serves the app shell + `/api/admin/inquiries` returns 401". The authoritative 401 stays (row 23). Documented, deliberate. |
| 23 | Unauthenticated API: list → 401, PATCH → 401 | Unchanged, passes as-is. |
| 24 | Wrong password → 401 `UNAUTHORIZED` generic code; no enumeration | Unchanged. |
| 25 | Bootstrap login: 200, SUPER_ADMIN, permissions include `system.auth.manage`, **HttpOnly `admin_session` cookie**, `csrfToken` in body | Laravel cookie attributes must match (`HttpOnly`, SameSite=Strict). E2E asserts `httponly` on the wire — pin SameSite in Pest (E2E can't see it). |
| 26 | `GET /api/admin/auth/session` → authenticated admin | Unchanged. |
| 27 | List: total 6, newest first, **every contactNumber matches `^\+\d{5,15}$`**, formatted input persisted canonical, search by name/email/E.164, status filter precise | Same as rows 10–11 plus the response-shape check. |
| 28 | Stats endpoint → real aggregates | Unchanged. |
| 29 | Mutation without CSRF header → **403**; with header → 200 + message untouched | Laravel must return 403 (not 419 — Laravel's default CSRF status). This is a deliberate contract difference from Laravel defaults; pin it. |
| 30 | Notes: 201 with `adminName` author, retrievable | Unchanged. |
| 31 | Logout → 200; **revoked token → 401 even with the cookie still present** | Session revocation must be server-side row invalidation, not cookie clearing — the current test proves it. Pin. |
| 32 | Persisted probe row: NEW/MEDIUM, no shadow fields, email lowercased on persist, exact E.164, `wa.me` derivable by stripping `+` | Unchanged — this is the acceptance row for 2B-1/7. |

### Gaps in BOTH suites (not pinned today — add to the new tests)

| # | Unpinned behavior | Action |
|---|---|---|
| G1 | **Rate limiting is never tested.** `rateLimit` exists, is imported by the contract test, but no test drives it to a 429. The E2E never triggers it either (max 5/10min; probes stay under). | Pest: drive the limiter to exhaustion → 429 with the exact envelope. E2E port: optional (needs env-tunable max — `INQUIRY_RATE_LIMIT_MAX` exists; use it). |
| G2 | **Session expiry (30-min idle, 24-h absolute) is never tested.** Neither suite ages a session. | Pest: create session, backdate `last_seen_at`/`absolute_expires_at`, assert 401 + revocation on next use. |
| G3 | **Account lockout (5 fails → 15 min) is never tested end-to-end.** `authenticateAdmin` has it; no test drives 5 failures. | Pest: 5 failed logins → 6th with the CORRECT password still fails generic; lock expires. |
| G4 | **Login rate limit (10/10min) and admin-read limit (120/min) untested.** | Pest, same as G1. |
| G5 | **Bootstrap wipe-after-first-use** (`delete process.env.ADMIN_BOOTSTRAP_PASSWORD`) is untested — a second bootstrap login after wipe must fail. | Pest. |
| G6 | **Password verify round-trip**: `hashPassword/verifyPassword` are imported but never called in any test. | Pest: `Hash::check` round-trip + wrong-password negative. (Trivial but pins the bcrypt swap.) |
| G7 | **422 response carries `code: "VALIDATION_ERROR"` + exact envelope shape** — the contract tests assert repository-level behavior only; the envelope is only E2E-covered for 201/400/401. | The Pest HTTP layer (feature tests) covers all statuses via one envelope assertion helper. |
| G8 | **`HEAD /api/admin/auth/session` → 204** exists today; nothing tests it. The E2E doesn't need it; keep the route, drop the test or add one line in Pest. | Decide at implementation; keep route for parity. |
| G9 | **Trailing-slash / method-mismatch behaviors** (`POST /api/admin/inquiries` → 400 "created by the public form", POST stats → 400) are untested. | Pest: two lines, pin the 400s — they're part of the documented contract. |

### Verdict

The suites are strong on validation boundaries and persistence, but **authentication
lifecycle (G2/G3/G5), rate limiting (G1/G4), and the HTTP envelope for 422 (G7) are the
weakest spots** — exactly the areas the Laravel rewrite re-implements from scratch. Those
five gaps go into the Pest suite as first-class tests, not nice-to-haves. Row 22 is the one
intentional behavior change; everything else is 1:1.

---

## 5. Risks / decisions surfaced by the inspection

1. **SEO parity is the hard part.** Next gave per-route `<head>` control for free. The
   prerender + head-helper approach preserves it, but the 21-test suite pins the title
   template and canonical mechanism — those tests are the acceptance gate.
2. **Auth is hand-rolled and behavior-rich.** The sliding idle timeout + hashed session
   tokens + double-submit CSRF must be reproduced exactly in Laravel middleware — easiest
   to keep the current algorithms rather than adopt Laravel Sanctum, whose model differs
   (SPA cookie auth ≈ but not identical; e.g. Referer-based CSRF vs double-submit header).
3. **Password hashing changes** (scrypt → bcrypt). Safe only because accounts are
   bootstrap-created; documented above.
4. **Rate limiting moves from in-process memory to Laravel cache** — behavior identical
   per deployment; multi-server deployments would need a shared cache store (Redis) noted
   in deployment docs.
5. **`/system` review page** stays but is excluded from prerender/sitemap as today.
6. **E2E script** assumes cookie + CSRF flow over real HTTP — it will validate the Laravel
   port for free once `BASE` is pointed at it.
7. **Laravel session machinery must stay OFF for these routes** (added during review): the
   plan originally said `SESSION_DRIVER=database`, but Laravel's cookie-based session id and
   our opaque-token `admin_session` cookie are two different mechanisms; running both gives
   two session lifetimes and a `StartSession` cookie the SPA never asked for. The guard is
   fully custom against the `admin_sessions` table, exactly mirroring the current algorithm.
8. **Same-origin by default (added during review):** production always serves the built SPA
   and the API from one origin, so `VITE_API_BASE` defaults to `""`; only the dev Vite
   server sets it. This keeps CSRF/origin semantics identical to today's deployment.
9. **Pre-generating OG PNGs needs a renderer** (added during review): the current cards are
   `next/og` (Satori) output. One-off options: a temporary Node script with `satori` +
   `resvg` (reuses the exact design JSX), or manual exports. The 5 images are static
   history, not dynamic content — generate once, commit the PNGs, and note that future
   case studies need a card added by hand or by re-running the script.

## §6c — MySQL dialect verification (completed 2026-09-24)

Migrations were executed and the full Pest suite run against a real MariaDB 10.4
server (XAMPP; API-compatible with MySQL 8 for everything used here). Results:

**Verified clean:**
- All 4 migrations run without error (`migrate:fresh` against a fresh utf8mb4 database).
- All 10 named CHECK constraints exist (`information_schema.TABLE_CONSTRAINTS`) and are
  **enforced**: invalid status/name-length/message-length/non-E.164 inserts are rejected
  with `ERROR 4025 CONSTRAINT ... failed`.
- `DATETIME(3)` columns verified (`datetime(3)` in `SHOW COLUMNS`); millisecond values persist.
- Full Pest parity suite: **69 tests, 341 assertions, green on both SQLite and MariaDB.**

**Dialect issues found and fixed during verification:**

1. **PHP `date('v')` / `DateTime::format('v')` is broken on Windows — always `.000`.**
   `microtime(true)` carries a real fraction, but every `gmdate('Y-m-d H:i:s.v')` call
   wrote `.000`, collapsing all DATETIME(3) values to second precision. On MySQL this
   made `ORDER BY created_at DESC` nondeterministic for same-second rows (parity row 10),
   and the pagination test failed with a stable-but-wrong order. On Linux this would have
   "worked" silently — a platform landmine, not a logic bug.
   **Fix:** new `app/Support/Clock.php` (formats from `microtime(true)`, handles the
   `.9995` → second rollover); all persistence code now uses `Clock::now()` /
   `Clock::offset()` instead of `gmdate()`.

2. **Laravel's schema builder has no `->check()` column modifier.** The fluent call was a
   silent no-op: ColumnDefinition stored the attribute, the grammar never emitted it.
   **Fix:** `app/Services/SchemaChecks.php` adds named table-level CHECK constraints via
   post-create `ALTER TABLE`; dialect-aware — a no-op on SQLite (no support, and the
   suite's `:memory:` driver has no `information_schema.TABLE_CONSTRAINTS`), real DDL on
   mysql/mariadb.

3. **MySQL DATETIME rejects ISO literals** (`T`/`Z` → "Incorrect datetime value"). The DB
   wire format is plain `'Y-m-d H:i:s.v'`; ISO-8601 stays at the API boundary via
   `toISOString()` in model serialization (fixed earlier, documented here for completeness).

**Test-runbook (verify on any MySQL/MariaDB):**

```bash
mysql -u root -e "CREATE DATABASE permetheon_migration_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd skeleton
DB_CONNECTION=mariadb DB_DATABASE=permetheon_migration_test DB_USERNAME=root DB_PASSWORD="" \
  php artisan migrate:fresh
DB_CONNECTION=mariadb DB_DATABASE=permetheon_migration_test DB_USERNAME=root DB_PASSWORD="" \
  ./vendor/bin/pest --compact
mysql -u root -e "DROP DATABASE permetheon_migration_test"
```

(For genuine MySQL 8, use `DB_CONNECTION=mysql` — the SchemaChecks helper emits the same
DDL; MariaDB 10.4 was the available local server and its CHECK/`DATETIME(3)` behavior
matches MySQL 8.0.16+. A CI run against real MySQL 8 remains worthwhile before cutover.)

## §6d — E2E lifecycle now runs against the Laravel backend (completed 2026-09-24)

`scripts/e2e-inquiry.mjs` takes `--backend=laravel` (or `--backend=next`, the default)
and `E2E_BASE` (default `http://localhost:3102`). The new launcher
`scripts/e2e-laravel-server.mjs` provisions the isolated Laravel instance exactly
like the Next recipe: fresh throwaway SQLite DB, bootstrap env vars, raised
`INQUIRY_RATE_LIMIT_MAX`, `php artisan serve` on 3102 — and deletes the temp DB on
`--stop`.

**Results (2026-09-24):**
- Next backend: **42/42 checks pass** (unchanged).
- Laravel backend: **41 pass · 0 fail · 1 skip** — the only skip is the
  `/admin` → `/admin/login` 307 redirect, which is a Next *page-route* behavior
  the PHP backend cannot have before the SPA exists (§4b documented difference).
  Every API-level check — validation table, E.164 probes, mass-assignment strips,
  bootstrap login, cookie flags, pagination/search/filters, CSRF 403, notes with
  author, server-side session revocation, lowercase email persistence — passes
  byte-identically on both backends.

This closes the loop on §2A: the Laravel route map is now proven equivalent at the
HTTP-lifecycle level, not just at the Pest-assertion level.

## §6e — Vite frontend migration: route shell + prerender + shared contract (completed 2026-09-25)

Status per §4 order — steps 1–3 done, verified end-to-end:

**Route shell (§4.1)** — `frontend/` is a standalone React 19 + TS + Vite app (react-router v7
data mode via vite-react-ssg, Tailwind 4, `@/` alias). All 11 routes from §1B exist; zero Next
imports remain (`grep next/` clean). tsc strict clean.

**Prerender (§4.2)** — `vite-react-ssg build` bakes all 11 pages (7 static + 4 case studies via
`getStaticPaths`). SEO parity is enforced, not assumed: `scripts/verify-prerender.mjs` (140
assertions, wired into `npm run build`) checks per-page title/description/canonical/OG against
the Next metadata contract, robots disallows, and the 11-URL sitemap (generator:
`scripts/generate-sitemap.mts`, port of Next `sitemap.ts`). Canonical for `/` is slashless
(`https://permetheon.com`), matching `siteUrl()`.

**Shared TS validation contract (§4.3)** — `frontend/src/lib/inquiries.ts` is diff-identical
logic to the Next `src/lib/inquiries.ts`; 28/28 headless assertions pass
(`node --experimental-strip-types`, documented semantics: structural E.164 country-code
validation, not per-country length tables).

**Hard-won gotchas (all verified, not guessed):**
1. `formatting: "prettify"` (vite-react-ssg default) breaks React 19 hydration with #418 —
   prettier injects whitespace text nodes between elements. Must be `formatting: "none"`.
   (Diagnosed with a `--mode development` build + browser hydration diff.)
2. `react-helmet-async` (vite-react-ssg's `<Head>`) silently drops conditional tags nested
   inside fragments — conditional OG/Twitter tags stay FLAT in the SeoHead fragment.
3. Node-run TS scripts (`generate-sitemap.mts`) need explicit `.ts` import extensions
   (`allowImportingTsExtensions` in tsconfig) and relative paths — `@/` doesn't resolve in
   plain Node.
4. Next's `<Link>`-equivalents must fully destructure framework-only props (`variant`, `dark`,
   `className`) before spreading; leakage renders `dark="true"` onto the DOM anchor.
5. Dev-only `--mode development` builds are the debugging tool for hydration mismatches —
   production minifies #418 into an unactionable stack.

**Verified live (production build, `vite preview`):** hydration clean on every route (zero
console errors), client-side nav works, /work category filters behave identically to Next,
/contact shows all 8 fields + `+1 202 555 0147` helper text and renders the shared contract's
exact 5 error messages on invalid submit, case-study deep links hydrate with correct
title/canonical/og:image, /admin gates to /admin/login with noindex, /system noindex +
correct title, 404 page matches Next not-found, fonts/screenshots load (200/304), visual
design pixel-faithful to the Next original (screenshot-verified).

## §6f — Vite frontend wired to the Laravel backend (completed 2026-09-25)

Resolution of §2B-11 (dev CORS): instead of opening a dev CORS surface, the Vite
dev server PROXIES `/api/*` → the Laravel backend (`server.proxy` in
`frontend/vite.config.mts`, target `LARAVEL_API_URL`, default `http://localhost:8000`).
`VITE_API_BASE` stays empty in every environment — the browser is always same-origin,
so SameSite=Strict cookies, CSRF double-submit, and the login Origin-vs-Host check
(§2B-6) behave identically in dev and production with zero special-casing.

All frontend fetches flow through `apiUrl()` (`AdminLoginForm` was the last hardcoded
same-origin fetch). The launcher `scripts/e2e-laravel-server.mjs` accepts
`LARAVEL_PORT` for the dev topology (E2E default remains 3102).

Proven end-to-end in a real browser (Vite :5173 → proxy → `php artisan serve` :8000):
contact form submit (formatted `+1 (202) 555-0147` normalized to canonical E.164,
email lowercased, status NEW) → row verified in the DB → admin gate redirect →
bootstrap login → dashboard with live stats/list → PATCH status REVIEWING + priority
HIGH (original message untouched, verified in DB) → internal note with author →
search by email → logout (session 401 after) → public form still submits post-logout.
HTTP E2E regression on the same instance: 41 passed · 0 failed · 1 skipped.

## §6g — Cutover: Next.js retired, Laravel serves the SPA (completed 2026-09-25)

Per §4.6, after parity was proven (§6c–§6f):

1. **Data migration** (`skeleton/database/migrate_from_next.php`): all 5
   business_inquiries copied row-for-row (ids/E.164/statuses verified, emails
   lowercased per contract, ISO timestamps converted to DATETIME(3) format);
   the admin account kept its id/role with the password re-hashed scrypt→bcrypt
   (§2B-4 — hashes not portable); sessions intentionally NOT migrated.
   The old `data/permetheon.db` is retained untouched as archive.
2. **SPA host** (`skeleton/routes/web.php`): static dist files (immutable cache
   for /assets/*), prerendered *.html for pretty URLs, SPA shell with a REAL 404
   for unknown paths, /api/* untouched. OG PNGs (1200×630) were extracted from
   the Next build cache into `frontend/public/og/` (map risk #9 resolved).
3. **Session machinery fully disengaged**: the web group now also drops
   StartSession/AddQueuedCookiesToResponse/ShareErrorsFromSession/ValidateCsrfToken
   — no laravel-session/XSRF-TOKEN cookies on any page or asset (§2B-5 applied to
   the whole surface; ValidateCsrfToken was the last thing needing a session).
4. **Next.js archived** to `legacy/` (git mv — history preserved): src → next-src,
   tests → next-tests, Next configs alongside. Root package.json replaced with
   frontend/backend handover scripts; Next deps no longer declared.
5. **Live verification on the cutover server (:3100, prod DB):** homepage renders
   with real screenshots + zero console errors; contact POST → 201 (row #6 in DB);
   admin login (re-hashed password) → dashboard shows Total 6 with the migrated
   and new rows; prerendered pages, sitemap, robots, OG images, fonts all 200;
   unknown paths 404 with the shell. E2E: 41 passed · 0 failed · 1 skipped.

The deployment topology is now exactly the target architecture: one origin,
Laravel + MySQL-compatible schema, prerendered React SPA, zero Node.js runtime.

Addendum to §6g (2026-09-25, later): the client-only routes (/admin*, /system)
were served the prerendered index.html shell, whose baked root + hydration data
belong to the homepage route — hydrating a client-only route against it threw
React #418 (recoverable re-render, but a console error on every admin visit).
Fix: postbuild `generate-admin-shell.mjs` emits `dist/admin-shell.html` (same
head/assets, EMPTY root, no hydration data); the Laravel SPA host serves it for
/admin* + /system. Admin login → dashboard now runs with a clean console.
