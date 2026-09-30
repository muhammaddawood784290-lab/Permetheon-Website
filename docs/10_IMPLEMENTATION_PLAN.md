# 10 — IMPLEMENTATION PLAN (18 PHASES)

Source: Master Project Document — Section 27 (Implementation Priority + Phase Deliverables), Section 28 (Dependency Model), Sec. 27 IMPLEMENTATION RULE (minimum viable launch).
Status: EXTRACTED FROM MASTER. Phase order and numbering are fixed. Reordering requires a recorded decision.

Dependency chain (Master Sec. 28):
**Brand Foundation → Design System → Core Components → Homepage → Project Data Model → Project Showcase → Case Studies → Services/Work/Process/About → Contact → Responsive QA → Motion → SEO/Accessibility → Performance → Final QA → Launch**

Critical dependency rules (Master Sec. 28):
1. Real screenshots before final project polish · 2. Content before animation · 3. Responsive before motion · 4. Accuracy before launch · 5. Reusability · 6. No hidden blockers.

Phase completeness rule: **Deliverables complete + Dependencies satisfied + Gate criteria pass.** Coding finished ≠ phase complete.
Minimum viable launch (Master): PHASE 1 CORE = Homepage + Navigation + Featured Work + 4 Case Studies + Contact CTA.

---

## PHASE 01 — FOUNDATION & DESIGN SYSTEM
**Objective:** Establish visual and technical foundation before building pages.
**Inputs:** brand assets, positioning, project list. **Upstream:** none (global inputs: logo, brand identity, project URLs, verified business info — see 11 §3).
**Deliverables:** brand system (logo implementation, colors, accents, typography, heading/body styles, buttons, links, borders, cards, icons, spacing, containers, grid, radius) · core components (Navbar, mobile nav, Footer, Primary/Secondary Button, Section Heading, Container, Card, Badge, Browser Mockup, Image Frame, CTA block) · responsive foundation (desktop/tablet/mobile breakpoints, responsive typography/spacing/containers).
**DoD:** design system consistent enough that new sections use existing components rather than custom styling per section.
**Gate:** 01 — see 11_PHASE_GATES_DEPENDENCIES.md.

## PHASE 02 — HOMEPAGE
**Objective:** Complete conversion-focused homepage.
**Upstream:** Phase 01 approved; core components; initial project information; screenshots or temporary placeholders.
**Deliverables:** desktop+mobile navigation (sticky, CTA) · hero (headline, copy, 2 CTAs, product composition) · capability strip (7 tags) · Featured Work (4 projects with real image, name, category, short description, CTA, external link) · services preview (6) · business/value sequence (8 steps) · process preview (6 stages) · Why Permetheon (6 cards) · final CTA · footer.
**DoD:** visitor learns what Permetheon does/builds/has built, how it works, how to start — without leaving the homepage.
**Gate:** 02.

## PHASE 03 — REAL PROJECT SHOWCASE SYSTEM
**Objective:** Reusable system powering all project presentations.
**Upstream:** Phases 01–02; verified project information.
**Deliverables:** ProjectCard, FeaturedProject, BrowserMockup, ProjectMetadata, ScreenshotGallery, ProjectCategory, ExternalProjectButton, CaseStudyButton · structured project data for PCT, TBMS, EstateHub, TravelNest (fields per 06_PROJECT_DATA_MODEL.md).
**DoD:** new project added via data + images without rebuilding components.
**Gate:** 03 (includes fictional fifth-project test).

## PHASE 04 — WORK / PORTFOLIO PAGE
**Route:** `/work`. **Upstream:** project data model, project cards, project assets.
**Deliverables:** page hero, project grid, category filters (All/Websites/Web Applications/Business Systems/Booking Systems/Portals/Internal Platforms), project cards, featured projects, case-study links, external project links.
**DoD:** users can browse and filter the full portfolio; architecture supports future projects.
**Gate:** 04.

## PHASE 05 — PCT CASE STUDY · PHASE 06 — TBMS CASE STUDY · PHASE 07 — ESTATEHUB CASE STUDY · PHASE 08 — TRAVELNEST CASE STUDY
**Routes:** `/case-studies/{pct,tbms,estatehub,travelnest}`. **Upstream:** project data model, real screenshots, verified info, browser-mockup/gallery components.
**Deliverables & DoD per project:** see 08_CASE_STUDY_SPECIFICATION.md §3 (section lists are fixed there).
**Gate:** 05–08 (collective — includes accuracy gate).

## PHASE 09 — SERVICES PAGE
**Route:** `/services`. **Upstream:** case studies completed; project categories established.
**Deliverables:** six detailed service sections; each with description, capabilities, typical use cases, related Permetheon projects, CTA.
**DoD:** prospective client identifies their service and immediately sees related real work.
**Gate:** 09.

## PHASE 10 — PROCESS PAGE
**Route:** `/process`. **Deliverables:** six-stage process (01 Discover … 06 Deploy); each stage with objective, activities, deliverables, expected client involvement; interactive timeline (desktop) / vertical (mobile).
**DoD:** visitor understands what happens after contacting Permetheon and the development journey.
**Gate:** 10–11 (with About).

## PHASE 11 — ABOUT PAGE
**Route:** `/about`. **Deliverables:** introduction, philosophy, what we build, how we think, core principles (Business First · Purposeful Design · Solid Engineering · Continuous Improvement), design+engineering positioning, CTA.
**DoD:** credibility without invented history/statistics/achievements.
**Gate:** 10–11.

## PHASE 12 — CONTACT / LEAD SYSTEM
**Route:** `/contact`. **Upstream:** CTA strategy, contact details, form handling method.
**Deliverables:** inquiry form (Name, Company, Email, project type [8 options], Budget, Project description, Submit CTA "Start the Conversation"); states: default, focus, validation error, submission, success, failure.
**DoD:** visitor submits an inquiry without confusion or unnecessary steps.
**Gate:** 12.

## PHASE 13 — RESPONSIVE IMPLEMENTATION
**Upstream:** all primary pages implemented.
**Deliverables:** intentional responsiveness for large desktop, standard desktop, tablet, mobile across navigation, typography, cards, screenshots, galleries, forms, CTAs, case-study layouts; no horizontal scrolling; appropriate touch targets.
**DoD:** no horizontal overflow, broken layouts or unreadable screenshots at supported sizes.
**Gate:** 13.

## PHASE 14 — MOTION & MICRO-INTERACTIONS
**Upstream:** responsive implementation approved (Critical Rule 03). Begin only after all core pages work.
**Deliverables:** global page transitions, scroll reveals, hover states · hero text reveal + product movement · project image hover/scale/transition · case-study image reveal, sticky metadata, gallery transitions · process stage transition · button hover/press/focus.
**DoD:** motion feels premium and intentional without slowing navigation or distracting from content.
**Gate:** 14.

## PHASE 15 — SEO & ACCESSIBILITY
**Deliverables:** unique titles, meta descriptions, OG image + metadata, sitemap, robots.txt, canonical URLs, structured data, per-case-study metadata · semantic HTML, keyboard navigation, focus states, accessible forms, alt text, heading hierarchy, contrast, reduced-motion.
**DoD:** primary pages accessible, crawlable, properly represented in search/social.
**Gate:** 15.

## PHASE 16 — PERFORMANCE OPTIMIZATION
**Upstream:** final images, fonts, animations, content.
**Deliverables:** optimize screenshots/images/fonts/JS/CSS/animation; lazy loading; code splitting; WebP/AVIF where appropriate; no immediate loading of all case-study screenshots; below-fold lazy loading.
**DoD:** fast on desktop and mobile without sacrificing project imagery or animation quality.
**Gate:** 16.

## PHASE 17 — FINAL CONTENT & FACTUAL QA
**Deliverables:** full review for typos, grammar, terminology, broken links, incorrect project/feature/technology claims, missing images/alt text, fake claims, placeholder content. Remove: lorem ipsum, fake testimonials/statistics/clients/results/awards.
**DoD:** every public factual claim verified or intentionally non-numerical positioning copy.
**Gate:** 17.

## PHASE 18 — FINAL CONVERSION QA
**Deliverables:** complete journey test (Homepage → Featured Work → Project → Case Study → Services → Start a Project) on desktop and mobile; verify every CTA (Start a Project, Explore Our Work, View Case Study, Visit Project, Contact).
**DoD:** visitor moves from discovery to understanding to inquiry without friction.
**Gate:** 18 → Release Candidate → Launch Gate (see 15_LAUNCH_CHECKLIST.md).

---

## CROSS-PHASE NOTES

- Master Sec. 27-P02 note: the homepage required-sections list includes Services; Technology section content is data-dependent (Blocker 04) — build the component, populate only verified technologies.
- Phase 12 blocked on form-handling decision (Blocker 09 / D-005-equivalent D0 at launch) and contact destination (Blocker 08): build UI, keep submission configuration environment-based.
- Phase 13 required pages: Homepage, Work, PCT, TBMS, EstateHub, TravelNest, Services, Process, About, Contact.
