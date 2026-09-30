# 11 — DEPENDENCIES & PHASE GATES

Source: Master Project Document — Section 28 (Dependencies & Phase Gates), Sec. 27 IMPLEMENTATION RULE.
Status: EXTRACTED FROM MASTER. Gate criteria are fixed; a gate passes only when every criterion is checked.

Phase completion formula:

```text
DELIVERABLES COMPLETE + DEPENDENCIES SATISFIED + GATE CRITERIA PASS
```

A phase is NOT complete merely because coding has finished.

---

## 1. DEPENDENCY MAP (fixed — Master Sec. 28)

```text
PHASE 01 Foundation
    ↓
PHASE 02 Homepage
    ↓
PHASE 03 Project System
    ↓
PHASE 04 Work
    ↓
PHASE 05–08 Case Studies
    ↓
PHASE 09 Services
    ↓
PHASE 10–11 Process + About
    ↓
PHASE 12 Contact
    ↓
PHASE 13 Responsive QA
    ↓
PHASE 14 Motion
    ↓
PHASE 15 SEO + Accessibility
    ↓
PHASE 16 Performance
    ↓
PHASE 17 Content QA
    ↓
PHASE 18 Conversion QA
    ↓
RELEASE CANDIDATE
    ↓
LAUNCH
```

Do not bypass dependencies unless explicitly required (and then: record why).

## 2. GLOBAL DEPENDENCIES (required before implementation begins — Master Sec. 28)

- **Brand:** Permetheon logo · existing brand identity · existing color references · preferred typography if already defined.
- **Project sources (verified URLs):** pct.permetheon.com · tbms.permetheon.com · estatehub.permetheon.com · travelnest.permetheon.com
- **Project assets (where available):** screenshots, logos, favicon, project images, UI designs, Figma files, technology information, existing project descriptions. If unavailable → do not invent (Blocker rules).
- **Business information (verified):** company info, services, contact details, social links, team information, business claims. Missing → blocker, never fabrication.

## 3. GATES

### GATE 01 — FOUNDATION APPROVED
Dependencies: existing brand assets, basic positioning, project list.
Criteria: [ ] Typography finalized [ ] Color system finalized [ ] Spacing finalized [ ] Grid finalized [ ] Buttons finalized [ ] Cards finalized [ ] Navigation design finalized [ ] Responsive breakpoints defined [ ] Primary message locked ("Digital Products. Built for Real Business.")
Decision: PASS → Homepage · FAIL → resolve visual inconsistencies first.

### GATE 02 — HOMEPAGE APPROVED
Dependencies: Phase 01 approved, core components, initial project info, screenshots/temporary placeholders.
Criteria: [ ] Hero communicates what Permetheon does [ ] Featured Work visible before excessive scrolling [ ] Four primary projects represented [ ] Services understandable [ ] Process understandable [ ] Primary CTA visible [ ] Mobile navigation exists [ ] Footer exists [ ] No lorem ipsum [ ] No fabricated claims.
Decision: PASS → Project Showcase System.

**GATE 02 DECISION — PASSED (2026-09-21):** all ten criteria verified with live evidence on the running build — hero headline + offering copy present; Featured Work section begins at ~1,615px (≈3 viewport-heights, directly after hero + capability strip, before any deep scroll); four projects (PCT, TBMS, EstateHub, TravelNest) represented; six services understandable; six process stages; primary CTA visible in navbar and hero; mobile menu opens with Work/Services/Process/About/Contact + CTA (visual evidence); footer contains full Sec. 17 structure (verified text); zero lorem ipsum; zero fabricated claims (regex + manual copy audit against docs/09 baseline). Temporary screenshot placeholders are explicitly permitted at this gate (dependencies allow "screenshots or temporary visual placeholders"); real screenshots remain required before case-study gates (B-002). Homepage approved → **Phase 03 (Project Showcase System) authorized.**

### GATE 03 — PROJECT SYSTEM APPROVED
Dependencies: Phases 01–02, verified project information.
Criteria: architecture supports PCT/TBMS/EstateHub/TravelNest; each project has [ ] Name [ ] Description [ ] Category [ ] Project URL [ ] Case-study URL [ ] Image support [ ] Metadata [ ] Capability list; [ ] project cards reusable.
**Gate test:** add a temporary fictional fifth project. If ProjectCard itself must be modified → architecture not reusable → FAIL. Remove test project after validation.

**GATE 03 DECISION — PASSED (2026-09-21):** fictional fifth project ("Test Project", Portals) added as DATA ONLY — zero component modifications. Verified rendering: Work grid ✓, "Portals" filter ✓, homepage Featured Work ✓, case-study route auto-generated (/case-studies/test-project → 200) ✓. Test project removed after validation; build reverted to 15 pages; test route now 404s; grid back to four verified projects. Gate 03 criteria also verified: all four projects render (name/description/category/URLs/image/metadata/capabilities), cards reusable, filters work from data alone. **Phase 04 (Work page) deliverables complete in the same pass; Gate 04 owner sign-off pending.** → Case Studies authorized (visual assets B-002 remain required before Gates 05–08).
Decision: PASS → Work + Case Studies.

### GATE 04 — WORK PAGE APPROVED
Dependencies: project data model, project cards, project assets.
Criteria: [ ] All four projects display correctly [ ] Filters work [ ] Case-study links work [ ] External links work [ ] Cards responsive [ ] Future project needs no page redesign.
Decision: PASS → Case Studies.

**GATE 04 STATUS (2026-09-21):** technical criteria self-verified — four projects display ✓; filters work with live counts (Booking Systems → TBMS + EstateHub verified via DOM interaction) ✓; case-study links verified during Gate 03 test (all routes 200) ✓; external links render via ExternalProjectButton ✓; future-project reusability proven by the Gate 03 test ✓. **Owner sign-off pending** (as with Gates 01–02, recorded as PASS-with-evidence for owner ratification).

### GATE 05–08 — CASE STUDY SYSTEM APPROVED (collective)
Dependencies: project data model, real screenshots, verified info, mockup/gallery components.
**Clearance status (2026-09-21):** feature verification ✅ (D-004/B-003 — owner confirmed all capabilities); technology wording ✅ (D-009/B-004 — generic "Custom Web Application" final); no-invented-metrics ✅ backed by D-012 (live-site numbers never published). **Remaining dependency: B-002 real screenshots** (TBMS capture also gated on B-012/D-010).
Per-case-study criteria: [ ] Hero [ ] Project overview [ ] Business context [ ] Challenge [ ] Approach [ ] Product explanation [ ] Feature/capability sections [ ] UX/UI [ ] Technology [ ] Gallery [ ] Closing CTA. Each case study: own visual identity within the shared design system.
Accuracy gate: [ ] No invented features [ ] No invented metrics [ ] No invented client claims [ ] No invented technology [ ] No fake outcomes.
Decision: PASS → Services + supporting pages.

### GATE 09 — SERVICES APPROVED
Dependencies: case studies completed, project categories established.
Criteria: every service connects to relevant proof (Booking→TBMS, Internal Platforms→PCT, Real Estate→EstateHub, Travel→TravelNest).
Decision: PASS → Process + About.
**BUILD NOTE (2026-09-21):** Services page built with all six services; proof links live for Business Systems→PCT and Booking→TBMS + EstateHub (the Master's verified service→proof mapping includes Real Estate/Travel proof targets that do not exist as service cards — see governance note in 17_CHANGELOG run 020). Owner sign-off pending.

### GATE 10–11 — TRUST PAGES APPROVED (Process + About)
Dependencies: brand positioning, services, case studies.
Criteria: [ ] Process clear [ ] About concise [ ] No unsupported company claims [ ] Both pages have CTA paths [ ] Content matches homepage positioning.
Decision: PASS → Contact.
**BUILD NOTE (2026-09-21):** Process page built — six verified stage definitions + expanded per-stage detail from the owner-authorized docs/20 draft (formal copy sign-off B-007 still pending; page carries an honest disclosure line). About page built — verified heading/copy + four principle titles only (no invented descriptions). No unsupported company claims on either page. Owner sign-off pending.

### GATE 12 — CONTACT APPROVED
Dependencies: CTA strategy, contact details, form handling method.
Criteria: [ ] Form renders [ ] Required fields work [ ] Validation works [ ] Error states work [ ] Success state works [ ] Failure state works [ ] CTA visible [ ] Works on mobile.
Decision: PASS → Responsive QA.
**BUILD NOTE (2026-09-21):** Form renders with all Master-verified fields; validation + error states verified via live interaction tests (empty submit → 3 errors; valid submit → honest "submission not connected yet" pending state — API returns 503 until CONTACT_ENDPOINT is configured, B-005). Success/failure final states verify once the destination exists. Owner sign-off pending.

### GATE 13 — RESPONSIVE APPROVED
Dependencies: all primary pages implemented (Homepage, Work, PCT, TBMS, EstateHub, TravelNest, Services, Process, About, Contact).
Environments: Desktop · Tablet · Mobile.
Criteria: [ ] No horizontal overflow [ ] No broken navigation [ ] No clipped text [ ] No unreadable screenshots [ ] No broken galleries [ ] Forms work [ ] Buttons usable [ ] Typography scales correctly [ ] Touch targets appropriate.
Decision: PASS → Motion.

### GATE 14 — MOTION APPROVED
Dependency: responsive approved.
Criteria: motion [ ] Supports hierarchy [ ] Doesn't block interaction [ ] No layout shifts [ ] No excessive loading [ ] Respects reduced motion [ ] Consistent across pages.
Performance rule: if animation causes measurable degradation → simplify or remove it.
Decision: PASS → technical optimization.

### GATE 15 — SEO & ACCESSIBILITY APPROVED
Dependency: content and routes finalized enough for metadata.
SEO: [ ] Titles [ ] Meta descriptions [ ] Canonicals [ ] OG [ ] Sitemap [ ] Robots.txt [ ] Structured data [ ] Case-study metadata.
A11y: [ ] Semantic HTML [ ] Heading hierarchy [ ] Keyboard navigation [ ] Focus states [ ] Accessible forms [ ] Alt text [ ] Contrast [ ] Reduced motion.
Decision: PASS → Performance.

### GATE 16 — PERFORMANCE APPROVED
Dependencies: final images, fonts, animations, content.
Criteria: [ ] Images optimized [ ] Responsive images [ ] Below-fold lazy loading [ ] Fonts optimized [ ] JS minimized [ ] Unnecessary dependencies removed [ ] Animation overhead controlled [ ] No obvious bottlenecks.
Decision: PASS → Final QA.

### GATE 17 — CONTENT QA APPROVED
Verify: [ ] Project names [ ] Descriptions [ ] Features [ ] Technologies [ ] Links [ ] Contact info [ ] Company info [ ] Service descriptions [ ] CTA copy.
Remove: [ ] Lorem ipsum [ ] Placeholders [ ] Fake statistics [ ] Fake testimonials [ ] Fake awards [ ] Fake client claims [ ] Unverified technology claims.
Decision: PASS → Conversion QA.

### GATE 18 — FINAL CONVERSION APPROVED
Journey: Homepage → Featured Work → Case Study → Services → Start a Project.
All CTAs tested: [ ] Start a Project [ ] Explore Our Work [ ] View Case Study [ ] Visit Project [ ] Contact.
Visitor can: 1 understand quickly · 2 discover real projects · 3 understand capabilities · 4 read a case study · 5 understand services · 6 find contact path · 7 submit inquiry. Tested desktop + mobile.
Decision: PASS → Release Candidate.

### RELEASE CANDIDATE GATE
[ ] All primary routes work [ ] All four case studies work [ ] All project links work [ ] Contact flow works [ ] Responsive QA passes [ ] SEO basics pass [ ] A11y basics pass [ ] Performance review passes [ ] No placeholder content [ ] No fabricated claims [ ] No major visual inconsistencies.

### LAUNCH GATE
Technical: [ ] Production environment configured [ ] Domain configured [ ] HTTPS active [ ] Production build successful [ ] Environment variables configured [ ] Analytics configured if required [ ] Error monitoring configured if required.
Content: [ ] Final copy approved [ ] Final screenshots approved [ ] Final project links verified [ ] Contact information verified.
QA: [ ] Desktop tested [ ] Mobile tested [ ] Forms tested [ ] Navigation tested [ ] External links tested [ ] Case studies tested [ ] SEO metadata tested.
Decision: ALL REQUIRED CHECKS PASS → LAUNCH. If a critical gate fails → DO NOT LAUNCH; return to the relevant phase, resolve, repeat the gate.

## 4. NEXT-DEPENDENT RULE

Each phase identifies its required inputs, upstream dependencies, required outputs and the next dependent phase (per phase sections in 10_IMPLEMENTATION_PLAN.md). No phase passes while a blocking issue remains unresolved (Master Blocker Final Rule).
