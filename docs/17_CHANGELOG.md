# 17 — DOCUMENTATION CHANGELOG

Format: date — change — traceability (Master source → document). Append-only; never overwrite history.

---

## 2026-09-29 — Tooling hygiene fixes carried from the Ver 3 2 workspace session

Fixes developed in a parallel workspace copy, transposed here with THIS checkout's layout (backend dir = `skeleton/`, legacy/ archive present — NOT the copy's `backend/` naming):

- **`serve:backend` port fix:** 3100 → **8000** to match the Vite dev-proxy default (`LARAVEL_API_URL=http://localhost:8000` in frontend/vite.config.mts). The `--working-dir=skeleton` path was already correct here and is unchanged.
- **E2E default backend:** `scripts/e2e-inquiry.mjs` now defaults to `--backend=laravel`; `--backend=next` is a hard error (exit 2) pointing at the legacy/ archive. Header updated.
- **Added `e2e:meetings` npm script** (the launcher existed with no alias).
- **Root `.gitignore` modernized** (was Next-era): now covers `frontend/dist/` + `.vite/`, `skeleton/database/*.sqlite*` (dev + E2E throwaway DBs), the E2E pidfile, skeleton compiled views/cache/logs (mirror of the nested Laravel gitignores), `.freebuff/`, and env files with `!.env.example` / `!frontend/.env.development` exceptions; legacy/ artifacts ignored if the archive is ever re-run. `legacy:next` script KEPT — legacy/ is real in this checkout.
- **Follow-up (needs git):** if the compiled Blade views under `skeleton/storage/framework/views/`, `laravel.log`, or `skeleton/bootstrap/cache/*.php` are already TRACKED, untrack with `git rm -r --cached skeleton/storage skeleton/bootstrap/cache` (ignore rules do not untrack tracked files), then `git check-ignore -v` to verify and commit.
- Traceability: migration map §4.6 cutover; no Master change.

## 2026-09-22 — Contact Number / WhatsApp added to the inquiry contract (Master amendment); run 027

- **Master amendment applied (spec §11/§15/§20/§47 as amended 2026-09-22):** the public form is now EXACTLY eight canonical fields — the single approved addition is `contact_number` (label "Contact Number / WhatsApp", required). One field, one format: canonical E.164 (`+12025550147`), normalized before submission (spaces/hyphens/brackets stripped, ITU `00` prefix converted) and structurally validated against the ITU calling-code registry + plan lengths; country codes are never guessed. No separate phone/WhatsApp field may exist (contract-tested).
- **Flow-through (§21/§27/§29/§30):** shared validator (`src/lib/inquiries.ts`) → public API → SQLite migration `005_contact_number` (append-only, VARCHAR(16)-equivalent + E.164 shape CHECK) → admin list + detail. Dashboard derives `tel:` and `https://wa.me/` from the stored E.164 only; no second representation is stored.
- **Migration safety (§44):** the pre-existing real inquiry (submitted before the field existed) keeps `''` — no number fabricated; the dashboard shows an honest "not provided" placeholder and renders NO call/WhatsApp actions for such rows. Production data untouched.
- **Rate-limit note:** public submission limit is now env-tunable (`INQUIRY_RATE_LIMIT_MAX`, default 5/10min per IP) so the isolated E2E instance can run its probe matrix; production keeps the strict default.
- **Verification:** contract suite 21/21 (new E.164 normalization/rejection table + form-integrity guard pinning the exact 8 fields) · tsc clean · build 27/27 · HTTP E2E 42/42 (9-case contact-number matrix, duplicate-field stripping, E.164 persistence, search-by-number, wa.me derivation) · browser pass on the real form (formatted input → success → dashboard shows `+12025550147` with tel/wa.me actions) and the legacy row (honest placeholder, no actions). Test DB deleted after.

---

## 2026-09-22 — Business Inquiry Admin system built + verified end-to-end; B-005 RESOLVED (run 026)

- **Full pipeline live:** canonical inquiry form (7 fields at build time; `contact_number` added 2026-09-22 by Master amendment — see run 027) on /contact → client+server shared validation (one contract, `src/lib/inquiries.ts`) → POST /api/inquiries (201/422/400/429/500 per §27) → SQLite persistence (node:sqlite, zero new deps; MySQL cutover DDL in `database/mysql/`) → authenticated /admin dashboard.
- **Security model (§05/§09/§37):** server-side sessions (HttpOnly+SameSite=Strict+Secure, SHA-256-hashed at rest, 30-min idle + 24-h absolute), scrypt password hashing, SUPER_ADMIN/ADMIN role→permission enforcement on every API route, CSRF double-submit on mutations, login rate limiting + account lockout, first-run SUPER_ADMIN bootstrap via env (credentials consumed on first login), mass-assignment protection (privileged fields ignored — contract-tested).
- **Portal lockdown (§04/§41):** /admin outside the public chrome (route groups), no public links, noindex + robots disallow, server-side redirect gate; production DB holds ZERO test records (E2E ran on an isolated instance + temp DB, deleted after).
- **Reconciliations:** old /api/contact external-endpoint pipeline + ContactForm removed (superseded by the canonical contract; D-005's env-forwarding approach fulfilled by real persistence instead).
- **Verification:** contract suite 20/20 (validation boundaries, enums, mass-assignment, real-DB persistence/list/notes/stats) · tsc clean · build 27/27 · HTTP E2E 25/25 (public contract, authz, CSRF, logout revocation, server-controlled fields) · full browser pass (form → dashboard → detail → status → note → honest zero-state).
- **Docs:** 12 (B-005 → RESOLVED), 16 (top risks, tracker item 5), this changelog; run.md updated with ADMIN_DB_PATH/ADMIN_BOOTSTRAP_* procedures.

---

## 2026-09-22 — TravelNest real screenshots integrated; B-002 RESOLVED (run 025)

- **B-002 CLOSED:** owner provided 7 TravelNest captures, all unique (no duplicates) → `public/screenshots/travelnest/`. **All four projects now carry verified owner-provided showcases** — PCT (6), TBMS (8), EstateHub (6 unique), TravelNest (7).
- **Journey carousel** (discover → explore → choose → trust → stories → site chrome): home hero + search (destination/date/guests/budget), Adventure Categories, Popular Destinations, Featured Tour Packages, Why TravelNest trust panels, Traveler Stories, footer. All six former pending asset slots retired; captions strictly describe visible UI (D-012 numerical claims never referenced).
- **De-staled everywhere:** all 4 featuredImageLabels drop "pending (B-002)"; homepage hero caption + /system imagery note updated to reflect real screenshots; TravelNest hero flows to homepage/Work/index cards.
- **Verification:** contract suite 14/14 (TravelNest caption prefixes + story order + showcase/preview whitelists now all four studies) · tsc clean · build 23/23 · live carousel exercised (dot jump, next, wrap forward 7→1, wrap back 1→7) · 0 foreign assets · homepage/Work/index serve the real hero · console clean.
- **Docs:** 12 (B-002 row → RESOLVED), 07 (asset status), 08 (§5 dependency + §6 gates status), 16 (phases 05–08, blockers list, tracker item 11, snapshot 9/14, next-steps), this changelog.

---

## 2026-09-22 — EstateHub real screenshots integrated as journey showcase (run 024)

- **B-002 partial:** owner provided 9 EstateHub captures → 6 unique screens after duplicate removal (hero appeared 2×, footer 3×; highest-quality versions kept) → `public/screenshots/estatehub/`.
- **Journey carousel** (discover → browse → filter → platform management → site chrome): home hero + search, featured listings, properties grid + filters, admin dashboard, admin inquiries, footer. Captions strictly describe visible UI.
- **Honesty preserved:** property detail + meeting-scheduling screens were NOT in the set — those sections keep marked B-002 placeholders (the D-004-verified scheduling capability stays without a published visual). TravelNest remains fully placeholder.
- **Verification:** contract suite 14/14 (caption prefixes + cross-project asset guard + whitelist now pct/tbms/estatehub) · tsc clean · build 23/23 · carousel interactions exercised live (next, dot jump, swipe) · no foreign assets on the EstateHub page · TravelNest still renders 14 honest pending markers.
- Traceability: Master Sec. 19/22, docs/07 EstateHub asset status, docs/12 B-002 row, docs/16 state snapshot.

## 2026-09-22 — TBMS real screenshots integrated as two-experience showcase (run 023)

- **B-002 partial:** owner provided 8 verified TBMS screens (customer booking site ×4, restaurant admin ×4) → `public/screenshots/tbms/`; no duplicates, unaltered. **B-012 evidence update:** the set presents the product under the live "TableReserve" brand with the documented two-experience structure — narrows the mismatch; D-010 (URL target) unaffected.
- **Showcase carousel** reused for TBMS: customer experience first (home → profile → featured dining → hours/location with staff sign-in), restaurant admin second (dashboard → reservations → tables → customers). Captions name only what each screen shows; zero new claims.
- **TBMS case-study placeholders retired:** customer/admin/gallery slots removed; sections carry verified descriptive copy. Index card shows the real homepage screenshot.
- **Contract suite generalized:** per-study verified caption prefixes, cross-project asset guard (`/screenshots/<slug>/`), showcase whitelist pinned to pct + tbms — 14/14 pass · tsc clean · build 23/23 · carousel behavior (jump, wrap, boundary) exercised live.
- Traceability: Master Sec. 19/22, docs/07 TBMS asset status, docs/12 B-002 + B-012 rows, docs/16 state snapshot.

## 2026-09-22 — PCT real screenshots integrated as showcase carousel (run 022)

- **B-002 partial:** owner provided 6 verified PCT screens (dashboard, projects, tasks, reviews, notifications, activity) → copied unaltered to `public/screenshots/pct/`; asset integrity (real PNGs, no duplicates) pinned in the contract tests.
- **Showcase carousel built** (`ScreenshotCarousel` — the Sec. 19 ScreenshotGallery realization): one primary screenshot at a time, prev/next, pagination dots, touch swipe, arrow-key navigation, wrap-around, `prefers-reduced-motion` honored, original aspect preserved (object-contain, no cropping/stretching), no fake browser frames. Live on `/case-studies/pct` hero.
- **PCT case-study restructure:** former per-module placeholder sections (Dashboard · Projects · Teams & Tasks · Reviews & Activity · Reports) consolidated — the real screens carry the module story (docs/08 amendment). Captions name only what each screen shows; zero new claims.
- **PCT cards updated:** homepage featured slots, /work card, /case-studies index card now show the real dashboard screenshot (via `previewImage` on the project entry); the other three projects keep honest B-002 placeholders.
- **Verification:** contract suite 14/14 · `tsc --noEmit` clean · build 23/23 · carousel behavior (next/prev/dots/keyboard/swipe/wrap) exercised live in the preview · console clean.
- Traceability: Master Sec. 19/22, docs/08 §3.1 amendment, docs/07 PCT asset status, docs/12 B-002 row, docs/16 state snapshot.

## 2026-09-21 — Contract tests + code simplification (run 021)

**Tests:** `tests/contract.test.ts` (10 tests, node:test, zero deps) — validation boundary table, submission branch table (503 not-configured / 422 invalid / 200 ok / 502 upstream-error / 500), payload-forwarding assertion, SEO single-suffix composition + verified case-study patterns, siteUrl composition, work-filter data contract. All pass; `tsc --noEmit` clean.

**Simplification the contract enabled:** `src/lib/contact.ts` extracted as pure logic (validation + submission branches, injected POST) — client form and API route now share one source; **server-side validation added (422)**, closing the hardening gap recorded during e2e verification. `src/lib/seo.ts` reduced (dual-branch `absoluteTitle` machinery removed — the layout `%s | Permetheon` template is the single suffix mechanism; case-study verified titles moved into data as `seoTitle` cores). Dead `RouteStub` component removed (zero references after Phases 09–12). Sitemap/robots use the one `siteUrl()` helper.

**Contract caught a real bug:** initial data refactor double-suffixed case-study titles ("… | Permetheon | Permetheon") — fixed before it could ship. Build re-verified; served titles unchanged and correct; preview restarted on the new build.

**Docs:** 14 §7 records the automated-testing decision (minimal contract suite; UI stays manual per Master).

## 2026-09-21 — IMPLEMENTATION MODE: Phases 09–12 — Services, Process, About, Contact (run 020)

**Built:** /services (hero + 6 verified service cards with capability lists + proof links + CTA); /process (hero + 6 stages, each with What happens / What you get / Your involvement from PROCESS_STAGE_DETAILS in the data layer, plus "beyond launch" connection line); /about (verified heading + copy, what-we-build chips, 4 principle titles — no invented descriptions); /contact (ContactForm client component with Master-verified fields, validation, aria error wiring) + /api/contact route (D-005 env-based via CONTACT_ENDPOINT, explicit 503 until B-005 configures the destination — no fabricated email/service).

**Governance note — service→proof mapping:** the Master's verified mapping includes "Real Estate Platforms → EstateHub" and "Travel Platforms → TravelNest" as proof targets. The verified services list has no Real Estate/Travel service card, so those proof links cannot be rendered as service→proof pairs; the mapping is preserved in docs/09 for the owner to either accept as-is or resolve via a decision. No service card was invented to host them.

**Verification:** build passes (23 routes); all four pages 200 with verified h1s; Services DOM probe (6 services, 3 proof links); Process DOM probe (6 stages × 3 detail columns); Contact live interaction tests (empty submit → 3 validation errors; valid submit → honest 503-driven pending message; API returns 503 "not-configured").

**Docs updated:** 11 (Gate 09–12 build notes), 16 (phases 09–12 status), this changelog.

## 2026-09-21 — Owner replies processed: items 3, 6, 7, 8, 14 + evidence upgrades (run 019)

**Owner decisions received and processed (via structured capture):** item 3 (D-004/B-003: all four capability lists CONFIRMED), item 6 (D-009/B-004: generic "Custom Web Application" authorized as final), item 7 (D-007/B-008: footer legal links removed), item 8 (D-008/B-009: analytics not required), item 14 (new **D-012**: TravelNest/EstateHub live-site numbers NEVER published on permetheon.com).

**Evidence upgrades (no owner input required):** B-010 RESOLVED — valid Let's Encrypt certificate confirmed via openssl + strict-TLS fetch (valid through 2026-11-23); EstateHub "schedule a viewing" flow verified in the live app bundle — Master's "Schedule Meeting" flow step now evidence-verified; 07 updated.

**Code changes (authorized by the decisions):** Footer legal links removed (D-007); case-study Technology copy finalized to generic wording without pending-markers (D-009); data-file headers updated. Build re-verified (22 pages); footer + case-study copy verified live via HTTP.

**Registers updated:** decision log (4 RESOLVED + D-012 created), blocker register (B-003, B-004, B-008, B-009, B-010 RESOLVED), 07 verification statuses + content-safety rulings, 09 footer spec, 15 launch checklist, tracker 8/14 answered · 1 deferred · 5 open.

## 2026-09-21 — IMPLEMENTATION MODE: Phase 15 SEO metadata (run 018)

**Built:** `src/lib/seo.ts` (single SEO source from the verified copy baseline); per-route metadata for all 11 public routes (root layout defaults, 6 page routes, case-study index + 4 case studies with Master Sec. 22 title patterns); text-only OG cards via next/og (root + per-case-study, no fabricated imagery); `sitemap.ts` (11 URLs) + `robots.ts` (Disallow /system); /system noindex.

**Verification:** production build passes (22 pages incl. OG images, sitemap, robots); served metadata inspected via curl across all 11 routes — every route has unique title + description + og:title/og:image; /system confirmed noindex; sitemap lists exactly the 11 public routes.

**Decision created:** D-011 (canonical production domain — permetheon.com placeholder in one constant, one-line swap when confirmed).

## 2026-09-21 — IMPLEMENTATION MODE: Phases 05–08 case studies (run 017)

**Built:** `src/data/case-studies.ts` (verified copy per project, zero invented claims; Blocker-04-safe Technology wording); ProductFlow component; full case-study template rewritten (hero + snapshot + product structure flow + alternating story sections with marked asset slots + PCT non-numerical Result + closing CTA); /case-studies index rebuilt as real "Behind the Build." page.

**Verification:** 15 routes build; all 5 case-study routes 200; PCT section audit via HTML extraction (Challenge/Context/Approach/Product/Dashboard/UX-UI/Technology/Gallery/Result/Product Structure all present); visual checks confirm hero + module sections with pending-asset slots.

**Gates 05–08 remain OPEN by design** — accuracy gate requires B-002 screenshots + B-003 feature verification; TBMS gallery also gated on B-012/D-010.

## 2026-09-21 — IMPLEMENTATION MODE: Phases 03–04 + Gate 03 test (run 016)

**Built:** reusable project components (ProjectCard, FeaturedProject, ProjectMetadata, ProjectCategory, CaseStudyButton, ExternalProjectButton — all data-driven per docs/06); data layer extended (industry, filters, FILTER_CATEGORIES — proposed filter mapping pending D-003); /work full page with working category filters + counts (replaced stub); homepage Featured Work refactored to consume FeaturedProject.

**Gate 03 reusability test: PASSED.** Fictional fifth project added as DATA ONLY — rendered on Work grid + Portals filter + homepage + auto-generated case-study route (200) with zero component changes — then removed (build reverted to 15 pages; test route 404s).

**Gate 04:** technical criteria self-verified (filters interaction-tested: Booking Systems → TBMS + EstateHub); owner sign-off pending. Case-study phases authorized (B-002 assets still gate visual approval).

## 2026-09-21 — Gate 02 review + PASS (run 015)

**Gate 02 (Homepage Approved): PASSED.** All ten criteria verified against the live build with objective evidence (DOM measurements + mobile-menu visual check + footer text extraction + copy audit vs docs/09). Featured Work at ~1,615px; four projects represented; CTA visible; no lorem/fabricated claims. Temporary screenshot placeholders permitted at this gate per the gate's own dependencies. Decision recorded in docs/11 Gate 02 block. Phase 03 authorized.

## 2026-09-21 — IMPLEMENTATION MODE: Phase 02 homepage (run 014)

**Built:** complete homepage per Master Sec. 27-P02 required order (hero with layered product mockups, capability strip, Featured Work ×4, Services ×6 with proof links, The Difference sequence, Process preview, Why Permetheon ×6, CTA band); `src/data/projects.ts` structured data layer (docs/06 model, verified content only, technologies empty per Blocker 04); honest route stubs for /work, /services, /process, /about, /contact, /case-studies + SSG /case-studies/[slug] ×4 (later-phase pages named, conversion path kept).

**Verification:** build passes (11 static routes), all routes HTTP 200, Gate 02 technical criteria self-verified via DOM probe (projects ✓ sections ✓ CTA ✓ mobile nav ✓ footer ✓ no lorem ✓ no fabricated claims ✓). Visual checks: hero, capability strip, featured-work card, dark services — all per spec.

**Governance:** Gate 02 owner sign-off still required (checklist in docs/11); docs 05 §6 + 16 updated.

## 2026-09-21 — First owner replies processed (run 013)

**Trigger:** Business Owner answered items 1, 2, 4, 5 (via structured questions; items 6–15 still outstanding).

**Processed per protocol (13 §6):**
- D-001 RESOLVED — REV 2 approved as working brand foundation → B-001 RESOLVED → **Gate 01 PASSED** → code markings updated (globals.css header, layout font comment, /system badge, home badge "GATE 01 PASSED").
- D-002 RESOLVED — homepage approved as documented → Phase 02 cleared to start.
- D-005 RESOLVED (approach) — email/form service with env-based config → B-005 IN PROGRESS (verified contact email still required before launch, Blocker 08 note).
- D-010/B-012 — owner "not sure yet" → DEFERRED, stays OPEN, D1 reminder clock applies; continues gating TBMS capture (item 11).

**Tracker:** 3/14 answered, 1 deferred, 10 outstanding. Docs updated: 12, 13, 16 (implementation status: Phase 01 COMPLETE/Gate 01 PASSED; Phase 02 READY TO START).

## 2026-09-21 — Temporary design REV 2 (run 012)

**Trigger:** user-requested review of /system showcase before owner review.

**Changes (still TEMPORARY — REQUIRES BRAND APPROVAL):**
- Accent: default indigo #4F46E5 → ember #C2410C (+hover #9A3412) — avoids the generic-SaaS look banned by Master Sec. 02; placeholder pending brand.
- Typography: added Space Grotesk display face via next/font (TEMPORARY) with fluid editorial scale (hero 44→72px, section 30→44px, lead 17→20px) — replaces fixed sizes for large-headline direction (Master Sec. 02).
- Spacing: section rhythm raised to 80–120px for generous whitespace.
- /system showcase updated (REV 2 badge, fluid type specimens); CTABlock/home/SectionHeading use the fluid scale.

**Verification:** production build passes; computed styles confirm live (accent rgb(194,65,12), Space Grotesk hero, fluid clamp active). Preview screenshots temporarily blocked by webview compositing issue — verified via DOM/CSS probe instead (recorded per tool-failure rule).

## 2026-09-21 — IMPLEMENTATION MODE: Phase 01 foundation build (run 011)

**Trigger:** explicit user instruction to begin Implementation Mode under the Blocker 01 path (temporary design system marked REQUIRES BRAND APPROVAL).

**Mode transition:** Documentation Engineering → Implementation (explicit authorization; source-code write scope declared).

**Built:** Next.js 15 App Router + TypeScript + Tailwind v4 (`package.json`, `tsconfig.json`, `postcss.config.mjs`, `next.config.mjs`, `.gitignore`); design tokens in `src/app/globals.css` (TEMPORARY — REQUIRES BRAND APPROVAL header; accent #4F46E5 flagged as placeholder); 10 core components (`src/components/`: Container, Button, SectionHeading, Card, Badge, Navbar, Footer, BrowserMockup, ImageFrame, CTABlock); app shell (`src/app/layout.tsx` with skip link + a11y basics), temporary `/` foundation page, `/system` design-system showcase for the Gate 01 review.

**Verification:** `next build` passes (3 static routes, ~106 kB first load); served on port 3100; visual check via preview (dark/light sections, sticky nav, mobile menu, cards/badges, mockup frames with clearly marked temporary placeholders — no fabricated UI). One deliberate simplification: CTA grid kept static (motion is Phase 14; Master Sec. 18 "do not over-animate").

**D-006 handled as a documented reversible decision:** stack = Next.js + Tailwind (recorded here + 16; owner visibility via request item 10).

**Gate status:** Phase 01 deliverables exist but Gate 01 CANNOT PASS until B-001/D-001 brand approval. Phases 02+ untouched (no scope creep).

**Docs updated:** 04 §2.1, 05 §5, 12 (B-001), 16 (implementation status + tracker item 1).

## 2026-09-21 — Process page copy draft (run 010)

**Created:** `20_PROCESS_PAGE_COPY_DRAFT.md` — expanded per-stage copy for the Process page (intro, six stages × Objective/Activities/Deliverables/Your involvement, closing line, CTA, approval checklist). B-007 "authorize drafting" path: derived strictly from Master-verified stage definitions (Sec. 11) plus non-factual elaboration; zero factual claims by design; no [OWNER INPUT] gaps were required.

**Wired in:** 12 (B-007 → draft-ready note), 18 + 19 (item 13 now offers review/approve instead of provide-or-authorize), 16 (tracker item 13 + P1 gap note), 00_INDEX (row 20, marked DRAFT NOT APPROVED). On approval: merge into 09 → B-007 RESOLVED → tracker ✅.

## 2026-09-21 — Owner-reply processing protocol (run 009)

**Updated:** 13_DECISION_GOVERNANCE.md — added §6 processing protocol: fixed 5-step sequence for handling owner replies when they arrive (tracker → decision record → blocker row → affected documents → changelog), with a per-item impact map (all 14 items → exact documents to revise) and declined/deferred handling. No replies have arrived; nothing processed, nothing fabricated — tracker remains 0/14.

## 2026-09-21 — Root README (run 008)

**Created:** `README.md` at repo root — one-page navigation for new contributors: the one rule (Master authority), role-based reading paths, grouped documentation map, current-state snapshot, and the house rules short version. Deliberately does not duplicate `00_INDEX.md` (inventory/traceability stay there); links into the docs system only.

## 2026-09-21 — Owner-response tracker (run 007)

**Updated:** 16_PROJECT_STATE.md — added RESPONSE TRACKER table mapping all 14 plain-request items to their decisions/blockers, windows, status and recording locations. Initial snapshot: 0/14 answered (5 D1-urgent, 5 D2, 4 phase-gated). Next-action item 0 now references the tracker. Same-run fix: corrected the doc-18↔doc-19 item mapping line (plain items mirror doc 18 items 1–13 + 15; doc 18 item 14/B-008 folds into plain item 7).

## 2026-09-21 — Plain-language owner request (run 006)

**Created:** `19_OWNER_INPUT_REQUEST_PLAIN.md` — external-ready variant of doc 18. All internal blocker/decision IDs, gate names and register references removed; content and item coverage identical (14 items). Internal ID mapping for traceability recorded in the 00_INDEX inventory row.

## 2026-09-21 — Consolidated owner input request (run 005)

**Trigger:** explicit user request to prepare the consolidated Business Owner input request.

**Created:** `18_BUSINESS_OWNER_INPUT_REQUEST.md` — covers all OPEN blockers B-001…B-010, B-012 (B-011 RESOLVED, excluded) and OPEN decisions D-001…D-010, organized by decision-priority window (D1 urgent → D2 → launch-critical), with checkbox response form mapping directly back into the registers.

**Cross-references added:** 00_INDEX inventory row, 16_PROJECT_STATE next-action item 0 + decision-range correction (D-001…D-010).

**Consistency audit fix (same run):** initial draft omitted D-002 (homepage positioning approval); added to the urgent D1 section and numbering corrected — the request now covers all ten OPEN decisions.

## 2026-09-21 — TBMS re-verification (run 004)

**Trigger:** explicit user request to browser-level-verify tbms.permetheon.com and resolve B-011.

**Findings:** direct fetch non-extractable; lenient-TLS proxy → HTTP 200. URL is LIVE, but content is **"TableReserve — Seasonal Dining in Hayes Valley, San Francisco"** — a single-restaurant marketing/reservation site (Hostinger-hosted images), not the documented TBMS Customer/Booking Portal + Restaurant Admin Portal structure. None of the documented platform capabilities are externally verifiable; a `/reserve` booking link is visible. Restaurant details classified as sample/demo content — barred from republication (Blocker 20 rule).

**Register changes:** B-011 **RESOLVED** (availability confirmed). New blocker **B-012** (P1, Business Owner + Development) for the presentation mismatch; linked decision **D-010** opened in 13. Gate impact: Gate 05–08 accuracy + Gate 04 external links.

## 2026-09-21 — EstateHub re-verification (run 003)

**Trigger:** explicit user request to re-check estatehub.permetheon.com and determine whether B-010 can be closed.

**Findings:** direct HTTPS still fails strict certificate verification (sandbox environment); lenient-TLS proxy fetch returned HTTP 200 with full server-rendered content — EstateHub is LIVE. Verified visible: property listings, category discovery (7 categories), property detail pages, agent portal/sign-in, "Contact Agent" inquiry flow. "Schedule Meeting" step only partially verified (inquiry path visible; dedicated scheduling UI to be confirmed on property pages during B-002 capture). Marketing counters observed — recorded only, republication barred (Blocker 20 rule).

**B-010 determination: NOT closed** — exit criterion ("loads securely, valid HTTPS") still unmet externally; blocker narrowed from "availability" to "certificate validity pending team-side evidence" (browser/SSL Labs check by Development Owner; reissue if genuinely invalid, close with evidence if valid from team environment).

**Documents updated:** 07_PROJECT_CONTENT.md (EstateHub LIVE VERIFICATION rewritten), 12_BLOCKER_MANAGEMENT.md (B-010 narrowed), 16_PROJECT_STATE.md (gap 6 + verification table).

## 2026-09-21 — Live project verification (run 002)

**Trigger:** explicit user authorization for read-only verification of the four documented project URLs (external read-only access permitted per documentation boundaries).

**Method:** static read-only fetches only. No form submissions, no writes, no logins, no configuration changes (external systems remain READ-ONLY).

**Results (full detail in 07_PROJECT_CONTENT.md LIVE VERIFICATION notes):**
- PCT — HTTP 200; title "PCT — Permetheon Command Terminal" verified; content client-rendered.
- TBMS — no readable text/title returned; availability UNVERIFIED (access limitation).
- EstateHub — TLS certificate verification failure on HTTPS and HTTP; inaccessible → blocker B-010 (P1).
- TravelNest — HTTP 200; categories, destinations, packages, traveler stories, gallery verified visible; search/date/guest/budget selections still unverified; Elementor artifact observed (unpublished, pending repo confirmation); live marketing stats recorded but barred from republication (Blocker 20 rule).

**Registers updated:** blockers B-010, B-011 created (12 §6); 16_PROJECT_STATE.md live-verification table added; no Master Document changes.

## 2026-09-21 — Initial documentation reconciliation (run 001)

**Trigger:** Documentation Engineering run — extract the Master Document (`DESIGN.md`) into a complete, traceable supporting documentation system.

**Inventory result:** exactly one pre-existing document — `DESIGN.md` (Master, 5,717 lines, Sections 01–31). No source code, no other docs. No conflicts existed because no supporting docs existed; no Master content was altered (Master write-restriction respected).

**Created (all EXTRACTED from Master, per-document source sections cited inside each file):**

| Document | Extracted from Master |
|---|---|
| 00_INDEX.md | whole-document map, inventory, traceability, maintenance rules |
| 01_PRD.md | Sec. 01, 02, 24, 25, 26, 27 |
| 02_INFORMATION_ARCHITECTURE.md | Sec. 03, 05, 14, 15, 16, 17, 27-P14 |
| 03_ROUTES.md | Sec. 07, 08, 23, Phases 04–12 |
| 04_DESIGN_SYSTEM.md | Sec. 02, 18, 20, 27-P01, Phase 01 |
| 05_COMPONENTS.md | Sec. 23, Phase 01, Phase 03 |
| 06_PROJECT_DATA_MODEL.md | Phase 03, Gate 03, Sec. 06/07/27-P03 |
| 07_PROJECT_CONTENT.md | Sec. 04, 06, 08, 25, 28, Phase 03 |
| 08_CASE_STUDY_SPECIFICATION.md | Sec. 08, Phases 05–08, Gate 05–08 |
| 09_PAGE_CONTENT.md | Sec. 04–17 |
| 10_IMPLEMENTATION_PLAN.md | Sec. 27, 28 (dependency chain) |
| 11_PHASE_GATES_DEPENDENCIES.md | Sec. 28 (all gates) |
| 12_BLOCKER_MANAGEMENT.md | Sec. 29 (+ active register B-001…B-009) |
| 13_DECISION_GOVERNANCE.md | Sec. 30, 31 (+ initial log D-001…D-009) |
| 14_QUALITY_STANDARDS.md | Sec. 18, 20, 21, 22, 27-P09–13, Phases 13–17 |
| 15_LAUNCH_CHECKLIST.md | FINAL LAUNCH CHECKLIST, RELEASE CANDIDATE + LAUNCH GATES |
| 16_PROJECT_STATE.md | derived state report (governance requirement) |
| 17_CHANGELOG.md | this file |

**Decisions created:** D-001 … D-009 (13_DECISION_GOVERNANCE.md §4) — all OPEN, owners assigned.
**Blockers created:** B-001 … B-009 (12_BLOCKER_MANAGEMENT.md §6) — all OPEN, owners/actions/outputs assigned.

**Consistency audit (post-creation):** verified across all documents — project names/positionings (PCT, TBMS, EstateHub, TravelNest), project URLs, route list, case-study slugs, service list + service→proof mapping, capability strips, 18-phase order and dependency chain, gate criteria, blocker severities/owners, decision priorities/deadline windows, CTA copy, footer, SEO titles/descriptions. One fact per fact, one source (Master). Result: PASS — no unresolved conflicts.

**Not done (out of scope):** Master Document modifications (none required); source-code changes (none exist); asset creation (prohibited — fabricating screenshots/brand assets); web research (not needed — no external verification dependency).
