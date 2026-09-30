# 06 — PROJECT DATA MODEL

Source: Master Project Document — Phase 03 (Real Project Showcase System), Phase 03 Gate, Sections 06, 07, 27-P03.
Status: EXTRACTED FROM MASTER. Only verified fields are included; additions require a decision.

---

## 1. PURPOSE

One shared, structured project data source powers: Homepage Featured Work, Work page (grid + filters), Case-study index cards, Case-study pages, and future projects — without modifying components (Master Phase 03: "Use structured data rather than hardcoding each project into individual components"; Phase 03 DoD: "A new project can be added by adding project data and images without rebuilding the project components").

## 2. PROJECT OBJECT — VERIFIED FIELDS (Master Phase 03)

```text
Project
├── name                (e.g. "PCT")
├── description         (verified positioning text)
├── category            (e.g. "Internal Business Platform")
├── industry            (metadata, e.g. "Internal Platform")
├── projectUrl          (external, e.g. https://pct.permetheon.com/)
├── caseStudyUrl        (e.g. /case-studies/pct)
├── screenshots         (real captures only — never fabricated)
├── capabilities        (verified feature list)
├── technologies        (VERIFIED ONLY — see §4)
├── featuredImage       (homepage card image)
└── metadata            (tags, e.g. ["Internal Platform","Project Management","Team Operations","Custom Software"])
```

Field rules:
- Every field must carry verified content or be explicitly left configurable-empty (Master Sec. 24: "leave the data field configurable").
- `technologies` **MUST NOT** be populated from assumption. If the stack is unverified, leave empty/generic ("Custom Web Application") per Blocker 04.
- No additional fields (metrics, testimonials, client names, awards) may be added — prohibited by Master Sec. 24.

## 3. WORK-PAGE FILTER TAXONOMY (Master Sec. 07 / Phase 04)

All · Websites · Web Applications · Business Systems · Booking Systems · Portals · Internal Platforms

Each project's data must map to at least one filter category so filtering works from data alone. Project categories verified in Master: PCT = Internal Business Platform; TBMS = Restaurant Reservation & Table Management; EstateHub = Real Estate Platform; TravelNest = Travel Platform. Exact filter mapping per project: **TBD — DECISION REQUIRED** (Design/Development; not explicitly defined in Master).

## 4. VERIFICATION PROVENANCE (per Master Sec. 24 + decision governance)

Every populated field must be classifiable as:
- VERIFIED — Master Document
- VERIFIED — Project Source (live product / repository / screenshots)
- VERIFIED — Existing Documentation / Approved Decision
- UNVERIFIED — Requires Confirmation (must not ship publicly)

Verified project content per project: see `07_PROJECT_CONTENT.md`.

## 5. ACCEPTANCE

- Phase 03 Gate requires each project to support: name, description, category, project URL, case-study URL, image support, metadata, capability list.
- Gate test: temporary fictional fifth project renders correctly everywhere without component changes, then is removed.
