# 14 — QUALITY STANDARDS
(Responsive · Motion · SEO · Accessibility · Performance · Testing & QA)

Source: Master Project Document — Sections 18, 20, 21, 22, 27-P09–P13, Phases 13–17.
Status: EXTRACTED FROM MASTER. Consolidated to avoid duplicate documents; each section cites its Master source.

---

## 1. RESPONSIVE (Master Sec. 20, Phase 13)

Targets: Desktop · Tablet · Mobile. Mobile must be **deliberately designed**, not a shrunken desktop.
Priorities: Typography · Project screenshots · CTA visibility · Navigation · Case-study readability · Touch targets.
Test matrix: large desktop, standard desktop, tablet, mobile. Check: navigation, typography, project cards, screenshots, galleries, forms, CTA buttons, case-study layouts, horizontal scrolling, touch targets.
Pass = no horizontal overflow, no broken layouts, no unreadable screenshots at supported sizes. Exact breakpoints: TBD — DECISION REQUIRED (Phase 01).

## 2. MOTION (Master Sec. 18, Phase 14)

Premium but restrained. **Do not over-animate.** Site must feel fast and intentional. Motion supports content — never added merely because technology allows it.

| Area | Motion |
|---|---|
| Hero | Text reveal · subtle product movement |
| Projects | Image parallax · hover scale · smooth transition |
| Case studies | Scroll reveals · sticky project information · image transitions |
| Process | Interactive step navigation |
| Buttons | Micro-interactions |

Implementation order: page transitions → section reveal → hero animation → project hover → image transitions → process interaction → button micro-interactions.
Gate rules (Gate 14): supports hierarchy · doesn't block interaction · no layout shifts · no excessive loading · respects reduced-motion · consistent across pages. Measurable performance degradation → simplify or remove.

## 3. SEO (Master Sec. 22, Phase 15)

- Homepage title: **Permetheon — Digital Products, Websites & Business Systems**
- Homepage description: **Permetheon designs and builds websites, web applications, booking platforms and custom business systems for modern businesses.**
- Unique metadata for: Work · Services · About · Contact · each case study.
- Case-study title patterns (verified): "PCT — Permetheon Command Terminal | Permetheon" · "TBMS — Restaurant Reservation & Table Management System | Permetheon" · "EstateHub — Real Estate Listing Platform | Permetheon" · "TravelNest — Travel Discovery & Planning Platform | Permetheon"
- Generate: sitemap.xml · robots.txt · Open Graph metadata (+ OG image) · canonical URLs · structured data where appropriate.

**IMPLEMENTED (Phase 15, 2026-09-21):**
- All 11 public routes serve unique title + meta description + OG tags (verified via live HTTP response inspection).
- Case-study titles follow the verified patterns exactly (e.g. "PCT — Permetheon Command Terminal | Permetheon").
- `src/lib/seo.ts` = single source for titles/descriptions/OG from the verified copy baseline; shared OG defaults via root layout.
- `sitemap.xml` (11 URLs) and `robots.txt` (allow all, sitemap reference, Disallow /system) generated via Next.js metadata routes.
- `/system` carries `noindex, nofollow` (internal design-system showcase — not a public page).
- Text-only OG cards generated via `next/og` for the root and all four case studies (no fabricated product imagery; brand-safe placeholder until real OG assets are approved).
- **Canonical domain:** `permetheon.com` is used as a placeholder for all absolute URLs — flagged as decision **D-011** (13_DECISION_GOVERNANCE.md §4); swap is a one-line constant change in `src/lib/seo.ts` once confirmed.

## 4. ACCESSIBILITY (Master Sec. 21, Phase 15)

Semantic HTML · accessible navigation · keyboard support · focus states · proper contrast · alt text · reduced-motion support · accessible forms · proper heading hierarchy.

## 5. PERFORMANCE (Master Sec. 27-P11, Phase 16)

Optimize: images · screenshots · fonts · JavaScript · CSS · animations · lazy loading · code splitting.
Screenshots are heavy: WebP/AVIF where possible · responsive image sizes · lazy-load below the fold · do not load every case-study screenshot immediately.
Pass = fast on desktop and mobile without sacrificing project visuals.

## 6. TESTING & QA (Master Phases 17–18 + Launch checklist)

**Factual QA (Phase 17):** verify project features, technologies, client info, business claims, outcomes, team info, metrics, testimonials — remove anything unverifiable. Also typos, grammar, terminology, broken links, missing alt text, placeholders.
**Conversion QA (Phase 18):** journey Homepage → Featured Work → Project → Case Study → Services → Start a Project, desktop + mobile; every CTA tested.
**Final design test (Sec. 26):** understand in 10s · see real work in 20s · website/webapp/business-system distinction · case studies open · process understood · easy start.
Detailed checklists: 15_LAUNCH_CHECKLIST.md.

## 7. NOT DEFINED IN MASTER (flagged)

CI, security hardening specifics, monitoring: **TBD — DECISION REQUIRED** (owners: Development / Business Owner).

**Automated testing — DECIDED (2026-09-21):** a minimal executable contract suite was introduced (`tests/contract.test.ts`, Node's built-in `node:test` — zero dependencies). Scope: pure business logic only — contact validation boundaries, contact submission branch table (503/422/200/502/500), SEO title/canonical composition, work-filter data contract. UI rendering stays under manual phase-gate QA per the Master; the suite is run with `node --test tests/contract.test.ts` alongside `tsc --noEmit`.

**Analytics/error monitoring: DECIDED — NOT REQUIRED (D-008, 2026-09-21).** No analytics or error-monitoring code is added for launch; revisit anytime via a new decision.
