# 08 — CASE STUDY SPECIFICATION

Source: Master Project Document — Section 08 (Case Studies), Phases 05–08, Gate 05–08, Critical Rule 05.
Status: EXTRACTED FROM MASTER.

---

## 1. SYSTEM RULES

- Route base: `/case-studies` (index heading: **"Behind the Build."** — supporting copy in 09_PAGE_CONTENT.md).
- Case studies must feel like **editorial product stories**, not simple portfolio pages (Master Sec. 08).
- All four case studies share **one common content architecture** (Master Critical Rule 05) — not four independent implementations — while each case study has its own visual identity within the Permetheon design system (Master Gate 05–08).
- Visuals: real screenshots only, full-width in case studies, inside premium browser/device frames (Master Sec. 19).

## 2. COMMON CASE-STUDY ARCHITECTURE (Master Sec. 08 + Sec. 27-P04 + Gate 05–08)

Every case study must contain (gate-checked):

```text
Case Study
├── Hero                    (name, full positioning, headline, description)
├── Project snapshot        (metadata tags)
├── Project overview
├── Business context
├── Problem / Challenge
├── Approach / Solution
├── Product                 (module/feature sections — real screenshots)
├── Capabilities            (verified features only)
├── UX/UI
├── Technology              (verified only; else generic "Custom Web Application")
├── Gallery                 (real screenshots)
├── Closing CTA
```

Do NOT fabricate: metrics, revenue, user numbers, conversion rates, client claims, performance claims, technology, features, outcomes. Missing information is documented as a dependency (blocker), never filled (Master Sec. 24, Blocker 06/07 rules).

## 3. PER-PROJECT SPECIFICATIONS

### 3.1 PCT — Phase 05 — `/case-studies/pct`
Hero: **PCT — Permetheon Command Terminal**. Headline: **"One command center for the teams building Permetheon."**
Story sections (verified list): The Challenge · The Business Context · The Approach · Product Architecture · Dashboard · Projects · Teams · Tasks · Reviews · Activity · Reports · UX/UI · Technology · Gallery · Final Summary · CTA.
Product structure visualization: PCT → Projects → Teams → Tasks → Reviews → Activity → Reports.
Design story themes: operational clarity, fast navigation, team visibility, structured workflows, high information density without visual clutter.

**Amendment (2026-09-22, owner showcase directive):** the six real PCT screens received via B-002 are integrated as a single verified screenshot carousel in the hero (multi-image: prev/next, pagination dots, swipe; one primary screenshot at a time; original aspect preserved). The former per-module placeholder sections (Dashboard · Projects · Teams & Tasks · Reviews & Activity · Reports) are consolidated into the carousel — the screens themselves carry the module story. All copy unchanged; captions name only what each screen shows.
Result copy (fixed, non-numerical): **"A centralized operational environment for Permetheon's internal project execution."**
DoD: visitor understands PCT as an internal command center for managing Permetheon's projects and teams.

### 3.2 TBMS — Phase 06 — `/case-studies/tbms`
Hero: **TBMS — Restaurant Reservation & Table Management System**. Headline: **"Turning restaurant reservations into a connected digital workflow."**
Story sections (verified list): Restaurant business context · Reservation challenge · Customer booking experience · Restaurant admin experience · Table management · Reservation management · Operational workflow · UX/UI · Technology · Gallery · CTA.
Product flow (fixed): Customer → Booking Portal → Reservation → Restaurant Admin → Table Management.
DoD: visitor understands TBMS connects the customer-facing booking experience with the internal reservation/table-management workflow.

### 3.3 EstateHub — Phase 07 — `/case-studies/estatehub`
Hero: **EstateHub — Real Estate Listing & Meeting Booking Platform**. Headline: **"Making property discovery more actionable."**
Story sections (verified list): Real-estate business context · Property discovery · Listings · Property details · Meeting scheduling · UX/UI · Technology · Gallery · CTA.
Product flow (fixed): Discover Property → View Listing → Explore Details → Schedule Meeting.
Constraint: no invented property statistics or client outcomes.
DoD: visitor understands the journey from finding a property to scheduling a meeting.

### 3.4 TravelNest — Phase 08 — `/case-studies/travelnest`
Hero: **TravelNest — Travel Discovery & Trip Planning Platform**. Headline: **"A digital journey from discovering a destination to planning the trip."**
Story sections (verified list): Travel discovery · Search experience · Destination discovery · Tour packages · Adventure categories · Featured destinations · Traveler stories · Travel gallery · UX/UI · Technology · Gallery · CTA.
Experience flow (fixed): Discover → Search → Explore → Choose → Plan.
DoD: case study communicates TravelNest as a complete travel discovery and planning experience, not simply a travel website.

## 4. ACCURACY GATE (Master Gate 05–08 — applies to all four)

Before approval, verify: no invented features · no invented metrics · no invented client claims · no invented technology · no fake outcomes.
Gate also requires every case study to contain: hero, project overview, business context, challenge, approach, product explanation, feature/capability sections, UX/UI section, technology section, screenshot gallery, closing CTA.

## 5. OPEN CONTENT DEPENDENCIES

- Real screenshots per project: **RESOLVED (B-002, 2026-09-22)** — all four projects' verified owner-provided sets integrated (see 07_PROJECT_CONTENT.md asset statuses); 2 EstateHub screens (property detail, scheduling) remain optional polish.
- Verified technology per project: UNKNOWN — VERIFICATION REQUIRED (Blocker 04, Development Owner).
- Feature existence confirmation per capability: REQUIRED before publish (Blocker 03).

## 6. IMPLEMENTATION STATUS (2026-09-21 — Phases 05–08 built)

All four case-study pages are **implemented** with the full common architecture (shared template, per-project verified content from `src/data/case-studies.ts`): hero + snapshot ✓ overview/context/challenge/approach ✓ product sections with clearly-marked pending asset slots (B-002) ✓ capability lists ✓ UX/UI ✓ Technology (Blocker-04-safe wording) ✓ Gallery slot ✓ closing CTA ✓. `/case-studies` index rebuilt as the real "Behind the Build." page.

**GATES 05–08 REMAIN OPEN** — structure is complete and every input dependency is resolved: real screenshots (B-002 — **RESOLVED 2026-09-22: all four projects integrated**), feature verification (B-003 — RESOLVED), technology authorization (B-004/D-009 — RESOLVED). Approval now awaits the owner's visual/accuracy sign-off; TBMS content additionally waits on B-012/D-010 (URL-target question). No accuracy-gate claim may pass until ratification. PCT's verified non-numerical Result section is live; no numerical results exist anywhere.
