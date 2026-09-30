# 03 — ROUTES & PAGE STRUCTURE

Source: Master Project Document — Section 23 (Technical Structure), Sections 07–17, Phases 04–12.
Status: EXTRACTED FROM MASTER.

---

## 1. ROUTE TABLE

| Route | Page | Heading (verified copy) | Master Source |
|---|---|---|---|
| `/` | Homepage | Digital Products. Built for Real Business. | Sec. 04–06, 27-P02 |
| `/work` | Work portfolio | **Work that speaks for itself.** | Sec. 07, Phase 04 |
| `/case-studies` | Case-study index | **Behind the Build.** | Sec. 08 |
| `/case-studies/pct` | PCT case study | **One command center for the teams building Permetheon.** | Sec. 08 CS-01, Phase 05 |
| `/case-studies/tbms` | TBMS case study | **Turning restaurant reservations into a connected digital workflow.** | Sec. 08 CS-02, Phase 06 |
| `/case-studies/estatehub` | EstateHub case study | **Making property discovery more actionable.** | Sec. 08 CS-03, Phase 07 |
| `/case-studies/travelnest` | TravelNest case study | **A digital journey from discovering a destination to planning the trip.** | Sec. 08 CS-04, Phase 08 |
| `/services` | Services | **What We Build** | Sec. 09, Phase 09 |
| `/process` | Process | **How We Build** | Sec. 11, Phase 10 |
| `/about` | About | **We build digital products that have a job to do.** | Sec. 13, Phase 11 |
| `/contact` | Contact | **Let's build something useful.** | Sec. 16, Phase 12 |

Route list is closed. New routes require a recorded decision. Case-study slugs are fixed: `pct`, `tbms`, `estatehub`, `travelnest`.

---

## 2. PAGE-BY-PAGE STRUCTURE

### `/` Homepage
Full section list and order: see `02_INFORMATION_ARCHITECTURE.md` §5. Copy: see `09_PAGE_CONTENT.md`.

### `/work`
- Page hero (heading + supporting text — verified copy in `09_PAGE_CONTENT.md`)
- Project grid — cards: project image, name, industry, product type, short description, "View Case Study"
- Featured: PCT, TBMS, EstateHub, TravelNest
- Filters: All · Websites · Web Applications · Business Systems · Booking Systems · Portals · Internal Platforms
- Expandable for future projects (add data + images, no page redesign)

### `/case-studies` (index)
- Heading + supporting text
- Cards for the four case studies, editorial product-story feel

### `/case-studies/{pct,tbms,estatehub,travelnest}`
Per-project section lists: see `08_CASE_STUDY_SPECIFICATION.md`. Shared architecture + per-project verified content: see `07_PROJECT_CONTENT.md`.

### `/services`
Six services (01 Website Development · 02 Web Applications · 03 Business Systems · 04 Booking & Reservation Systems · 05 UI/UX & Product Design · 06 Custom Digital Products). Each service: description, capabilities, typical use cases, related Permetheon projects, CTA.
Service→proof mapping (verified):
- Booking & Reservation Systems → TBMS
- Internal Business Platforms → PCT
- Real Estate Platforms → EstateHub
- Travel Platforms → TravelNest

### `/process`
Six stages: 01 Discover · 02 Define · 03 Design · 04 Develop · 05 Test · 06 Deploy.
Each stage: objective, activities, deliverables, expected client involvement.
Interactive timeline on desktop; vertical timeline on mobile.

### `/about`
Introduction, philosophy, what we build, how we think, core principles (Business First · Purposeful Design · Solid Engineering · Continuous Improvement), design+engineering positioning, CTA. Keep concise; no invented history/statistics/achievements.

### `/contact`
Form fields: Name · Company · Email · "What are you looking to build?" (Website · Web Application · Business System · Booking System · Portal · E-commerce · Custom Software · Other) · Budget · Project Details.
CTA: **Start the Conversation**. Required form states: default, focus, validation error, submission, success, failure.

---

## 3. EXTERNAL LINKS (verified — Master Sec. 06 project links + Sec. 28 project sources)

| Project | URL |
|---|---|
| PCT | https://pct.permetheon.com/ |
| TBMS | https://tbms.permetheon.com/ |
| EstateHub | https://estatehub.permetheon.com/ |
| TravelNest | https://travelnest.permetheon.com/ |

Verify reachability before launch (Gate: Launch — "All project links work"). If a project is offline, remove its "Visit Project" CTA (Blocker 05 rule).

---

## 4. CONTACT FLOW

Form submission destination: **UNKNOWN — VERIFICATION REQUIRED** (form backend is a Master-recognized blocker: Blocker 09). Keep submission configuration environment-based until resolved. Do not invent contact details (Blocker 08).
