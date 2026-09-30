# 05 — COMPONENT ARCHITECTURE

Source: Master Project Document — Section 23 (reusable components), Phase 01 (core components), Phase 03 (project system components).
Status: EXTRACTED FROM MASTER. Component list is closed; additions require a recorded decision.

---

## 1. COMPONENT REGISTRY (Master Sec. 23 — authoritative list)

| Component | Master Source | Purpose |
|---|---|---|
| Navbar | Sec. 03 | Sticky desktop nav + mobile menu |
| Footer | Sec. 17 | Footer navigation, featured work, legal |
| Button | Sec. 27-P01 | Primary + secondary button styles |
| SectionHeading | Sec. 27-P01 | Consistent section headers |
| ProjectCard | Sec. 07, Phase 03 | Reusable project presentation |
| CaseStudyCard | Sec. 08 | Case-study index presentation |
| ServiceCard | Sec. 09 | Service presentation |
| ProcessTimeline | Sec. 11, Phase 10 | Interactive 6-stage process |
| TechnologyGroup | Sec. 14 | Grouped technology display |
| BrowserMockup | Sec. 19, Phase 03 | Premium browser framing for real screenshots |
| ScreenshotGallery | Sec. 19, Phases 05–08 | Full-width project galleries — realized as `ScreenshotCarousel` (showcase: one screenshot at a time, prev/next, dots, swipe; run 022; live on all four case studies — run 025) |
| CTASection | Sec. 15 | Dark full-width CTA band |
| ContactForm | Sec. 16, Phase 12 | Inquiry form with required states |
| AnimatedText | Sec. 18 | Hero text reveal |

## 2. ADDITIONAL CORE COMPONENTS (Master Phase 01)

Navbar · Mobile navigation · Footer · Primary Button · Secondary Button · Section Heading · Container · Card · Badge · Browser Mockup · Image Frame · CTA block.

## 3. PROJECT SYSTEM COMPONENTS (Master Phase 03)

ProjectCard · FeaturedProject · BrowserMockup · ProjectMetadata · ScreenshotGallery · ProjectCategory · ExternalProjectButton · CaseStudyButton.

**Reusability gate (Master Phase 03 Gate test):** adding a temporary fictional fifth project must NOT require modifying ProjectCard itself — data-driven only. Any failure = architecture not reusable = gate FAIL.

**Reusability rule (Master Critical Rule 05):** if the same component appears more than twice, it should become reusable. Case-study pages must use a common content architecture, not four independent implementations.

## 4. COMPONENT RULES

- Components consume the shared project data model (06_PROJECT_DATA_MODEL.md) — no hardcoded projects (Master Phase 03: "Use structured data rather than hardcoding each project into individual components").
- Motion behavior per component defined in 14_QUALITY_STANDARDS.md §3.
- Framework/technology for components: **TBD — DECISION REQUIRED** (Master defines none).

## 5. IMPLEMENTATION STATUS (2026-09-21 — Phase 01 build)

Implemented (Next.js App Router + Tailwind v4, TypeScript, `src/components/`):
Container · Button (primary/secondary/ghost, light+dark, link/external/disabled states) · SectionHeading (eyebrow/title/description, light+dark) · Card · Badge · Navbar (sticky, desktop items + CTA, mobile menu per Master Sec. 03) · Footer (Sec. 17 structure) · BrowserMockup (Sec. 19 frame) · ImageFrame (real-screenshot-or-clearly-marked-temporary-placeholder) · CTABlock (Sec. 15 dark band + subtle grid).

App shell: `src/app/layout.tsx` (skip link, placeholder metadata), `/system` design-system showcase.

## 6. PHASE 02 STATUS (2026-09-21)

- **Data layer:** `src/data/projects.ts` — structured single source for all project surfaces (per docs/06); technologies intentionally empty (Blocker 04).
- **Homepage:** complete per Master Sec. 27-P02 required order — hero (Sec. 04) · capability strip (Sec. 05) · Featured Work ×4 (Sec. 06) · Services ×6 (Sec. 09) · The Difference (Sec. 10) · Process preview (Sec. 11) · Why Permetheon (Sec. 12) · CTA (Sec. 15) · Footer (layout).
- **Route stubs (honest placeholders, named phase + conversion path intact):** /work, /services, /process, /about, /contact, /case-studies + /case-studies/[slug] ×4 (SSG from data).
- **Verification:** production build passes (11 routes); all routes HTTP 200; Gate 02 self-check via DOM probe — four projects represented ✓, services/process understandable ✓, primary CTA visible ✓, mobile navigation exists ✓, footer exists ✓, no lorem ipsum ✓, no fabricated claims ✓. Featured Work visible before excessive scrolling ✓ (hero visual sits directly under the hero copy).
- **Gate 02 note:** gate approval (Business Owner) still required per governance; all technical criteria self-verified PASS.
