# 12 — BLOCKER MANAGEMENT

Source: Master Project Document — Section 29 (Explicit Blocker Owners & Resolution Rules).
Status: EXTRACTED FROM MASTER. The severity model, owner roles and resolution chains below are fixed by the Master.

---

## 1. SEVERITY MODEL (fixed)

| Priority | Meaning | Action |
|---|---|---|
| **P0** | Launch blocker — site cannot safely launch | STOP RELEASE; resolve before launch |
| **P1** | Phase blocker — current phase cannot pass its gate | STOP CURRENT PHASE; no dependent work; escalate immediately |
| **P2** | Non-critical — specific feature incomplete | Continue independent work; resolve before final QA |
| **P3** | Polish | Backlog; never delay core implementation |

## 2. OWNERS (fixed roles — Master Sec. 29)

- **OWNER A — Permetheon / Business Owner:** brand direction, positioning, service definitions, contact info, company info, project context, client/project claims, final copy/project/website approval.
- **OWNER B — Design / Creative:** typography, colors, layout, hierarchy, component styling, project presentation, case-study visual direction, motion direction, responsive design decisions.
- **OWNER C — Development / Implementation:** frontend, components, routing, project data architecture, responsive implementation, forms, animations, performance, SEO/a11y implementation, deployment configuration.
- **OWNER D — Content / Case-Study:** case-study copy, project descriptions, feature/technology verification, screenshots, metadata, content QA. *(Defaults to Business Owner + developer support if no dedicated content person.)*

## 3. BLOCKER RECORD FORMAT (fixed — Master Sec. 29)

```text
BLOCKER ID: B-XXX
PHASE: Phase XX — [name]
SEVERITY: P0/P1/P2/P3
DESCRIPTION: [...]
OWNER: [role]
DEPENDENCY: [what is blocked]
ACTION REQUIRED: [...]
EXPECTED OUTPUT: [...]
STATUS: OPEN / IN PROGRESS / WAITING ON INPUT / RESOLVED / VERIFIED / WONT FIX
RESOLUTION: [...]
GATE IMPACT: [which gate]
ESCALATION: [path]
```

Chain: **BLOCKER → OWNER → ACTION → OUTPUT → RESOLUTION → VERIFICATION.**
Statuses allowed: OPEN · IN PROGRESS · WAITING ON INPUT · RESOLVED · VERIFIED · WONT FIX.
Escalation rule: P3 → backlog · P2 → continue + revisit before final QA · P1 → escalate to owner immediately, no dependent work · P0 → stop release activity.

## 4. BLOCKER PLAYBOOK (Master-defined blockers 01–20 — resolution rules summary)

| ID | Blocker | Owner | Severity/Gate impact | Resolution rule (summary) |
|---|---|---|---|---|
| 01 | Missing brand assets | Business Owner | P1 — Phase 01 | Provide logo/colors/fonts; else Design creates TEMPORARY system marked "REQUIRES BRAND APPROVAL" |
| 02 | Project screenshots unavailable | Content Owner | P1 for case-study approval | Capture from live product; temporary marked captures OK; never launch fake UI |
| 03 | Project features uncertain | Business + Content Owner | P1 — factual QA | Verify via live product/docs/Figma/source/owner; if still uncertain → REMOVE the claim |
| 04 | Technology stack unknown | Development Owner | P2 | Verify from repo/package files/deploy config/docs; if unverifiable → don't publish; say "Custom Web Application" |
| 05 | Project link unavailable | Development Owner | P1 if presented live; P2 if case study stands alone | Verify URL/DNS/HTTPS/deploy; if intentionally offline → remove Visit Project CTA |
| 06 | Incomplete case-study content | Content Owner | P1 | Use standard framework; qualitative outcome if no metrics; never invent metrics |
| 07 | No verified business results | Business Owner | P2 | Never fabricate revenue/users/conversion/growth/ROI; use factual product outcomes |
| 08 | Contact information missing | Business Owner | P1 before launch | Provide final contact destination; until then build form UI, env-based config; never invent details |
| 09 | Contact form backend unavailable | Development Owner | P1 | Use approved integration; never claim the form works if submissions aren't delivered |
| 10 | Design approval delay | Design + Business Owner | P1 design system / P2 detail | Limit to concrete alternatives; obtain one final decision |
| 11 | Content vs design conflict | Content + Design Owner | P2 — before final visual QA | Priority: factual accuracy → clarity → adjust layout → edit copy |
| 12 | Responsive layout failure | Development Owner | P1 — responsive gate | Fix at component source; check container/typography/grid/images/overflow/nav/touch targets |
| 13 | Performance regression | Development Owner | P1 severe / P2 minor | Optimize images → lazy-load → reduce animation → split code → remove deps → fonts → simplify |
| 14 | Accessibility failure | Development Owner | P1 critical / P2 minor | Fix semantics, keyboard, focus, labels, contrast, alt text, reduced motion |
| 15 | SEO configuration missing | Development Owner | P1 launch-critical | Implement metadata, canonicals, sitemap, robots, OG, case-study metadata, structured data |
| 16 | External service/API dependency | Development Owner | Depends on criticality | Determine if required; configure/test/error-handling/fallback; never fake an integration |
| 17 | Deployment/domain issue | Development Owner (+ Business provides access) | **P0** | Business provides domain/hosting/DNS/credentials; developer builds/deploys/validates |
| 18 | Conflicting stakeholder feedback | Business Owner | Variable | Owner decides once; record; update spec; implement one direction |
| 19 | Scope creep | Business Owner | Variable | Classify: Critical (add now) / Important (post-launch backlog) / Nice-to-have (future); nothing silently enters current phase |
| 20 | Unverified claims | Business Owner | P1 — final content QA | Provide evidence or remove; replace with factual product description |

## 5. OWNERSHIP MATRIX (Master — summary)

Brand assets → Business/Design (P1) · Visual direction → Design/Business (P1) · Screenshots → Content/Business+Dev (P1) · Features → Business/Content+Dev (P1) · Tech stack → Development/Content (P2) · Project URL → Development/Business (P1/P2) · Case-study copy → Content/Business (P1) · Business results → Business/Content (P2) · Contact details → Business/Development (P1) · Contact backend → Development/Business (P1) · Responsive → Development/Design (P1) · Animation → Development/Design (P2) · Performance → Development/Design (P1/P2) · Accessibility → Development/Design (P1/P2) · SEO → Development/Content (P1) · Deployment → Development/Business (P0) · Scope changes → Business (variable) · Conflicting feedback → Business (variable) · Unverified claims → Business/Content (P1).

## 6. ACTIVE BLOCKER REGISTER

| ID | Phase | Severity | Description | Owner | Action required | Expected output | Status | Gate impact | Escalation |
|---|---|---|---|---|---|---|---|---|---|
| B-001 | Phase 01 | P1 | Brand assets (logo, colors, fonts, identity references) not yet provided/verified | Business Owner | Provide current logo + variations, brand colors, font preferences, existing assets — or approve the temporary design system now implemented (reviewable at `/system`) | Approved brand foundation, or TEMPORARY system marked "REQUIRES BRAND APPROVAL" | RESOLVED 2026-09-21 — Business Owner approved REV 2 as working brand foundation (D-001); official assets may still replace tokens later | Gate 01 — PASSED | Business Owner |
| B-002 | Phases 05–08 | P1 | Screenshot evidence for all four project case studies (PCT, TBMS, EstateHub, TravelNest) | Content / Case-Study Owner | Capture dashboard/homepage, primary workflow, key features, secondary screens per project | Verified screenshot sets for all four projects | **RESOLVED 2026-09-22** — all four owner-provided sets received + integrated: PCT (6 screens), TBMS (8 screens: 4 customer booking site + 4 restaurant admin), EstateHub (6 unique screens after dedup), TravelNest (7 screens). Carousels live on all four case studies; 2 EstateHub screens (property detail, scheduling) remain outstanding as optional polish only | Gate 05–08 (visual evidence in place — owner sign-off remains) | Business Owner |
| B-003 | Phases 05–08 | P1 | Feature existence not yet confirmed against live products for all listed capabilities | Business Owner + Content Owner | Verify each capability via live product/docs/Figma/source/owner | Verified capability lists per project (remove anything unconfirmed) | **RESOLVED 2026-09-21** — owner confirms all documented capabilities exist (D-004); 07 marked VERIFIED | Gate 05–08 accuracy | Business Owner |
| B-004 | Phases 05–08 / homepage Technology | P2 | Technology stacks for all four projects unverified | Development Owner | Verify from repositories/package files/deploy config/project docs | Verified per-project technology lists (or generic "Custom Web Application") | **RESOLVED 2026-09-21** — generic "Custom Web Application" authorized (D-009) | Gate 05–08 (does not block design) | Business Owner |
| B-005 | Phase 12 / Launch | P1 | Contact form backend/submission mechanism not selected; contact details unknown | Development Owner (backend) + Business Owner (contact destination) | Select approved submission method; provide verified contact destination | Working, verified lead-generation flow | **RESOLVED 2026-09-22 (run 026)** — the D-005-approved approach was built: canonical inquiry form → POST /api/inquiries → server-side validation → real database → authenticated /admin dashboard (full lifecycle E2E-verified, 25/25 HTTP checks + browser pass). Public channel is businessinquiry@permetheon.com (§03 contract; no phone). At deployment the owner sets the initial SUPER_ADMIN credentials via ADMIN_BOOTSTRAP_* env vars (first login consumes them) | Gate 12 / Launch | Business Owner |
| B-006 | Launch | P0 (conditional) | Deployment/domain/HTTPS configuration not yet arranged | Development Owner (+ Business Owner provides access) | Provide domain/hosting/DNS access; configure production | Production deployment validated | OPEN | Launch Gate | Final Project Authority. **Note (2026-09-22, run 027):** at MySQL provisioning, apply `database/mysql/001_business_inquiry_system.sql` (includes the `contact_number VARCHAR(16)` column, canonical E.164) |
| B-007 | Phase 10 | P2 | Expanded per-stage Process copy (activities/deliverables/client involvement) not defined in Master | Business Owner / Content Owner | Provide or approve per-stage copy — **DRAFT READY: `20_PROCESS_PAGE_COPY_DRAFT.md` awaits owner review/approval (or replacement copy)** | Process page content complete | OPEN — draft prepared, approval pending | Gate 10–11 | Business Owner |
| B-008 | Launch | P1 (conditional) | Privacy & Terms page content not defined (footer links exist) | Business Owner | Provide legal pages or decision to defer/remove links | Legal links resolved | **RESOLVED 2026-09-21** — links removed from footer (D-007) | Launch content approval | Business Owner |
| B-009 | Launch | P2 | Analytics + error monitoring requirement undecided ("if required" in Master) | Business Owner + Development Owner | Decide whether required; if yes select provider | Configured or explicitly not required | **RESOLVED 2026-09-21** — not required (D-008) | Launch Gate | Business Owner |
| B-010 | Phase 04 / Launch | P1 | EstateHub URL (estatehub.permetheon.com) fails strict TLS certificate verification externally — site IS live (HTTP 200 via lenient-TLS proxy, 2026-09-21) but secure-loading validity unconfirmed | Development Owner | Check certificate validity from a real browser / SSL Labs; if genuinely invalid/expired/mis-chained → reissue; if valid from team environment → record evidence and close | EstateHub URL loads securely (valid HTTPS); external-link check passes | RESOLVED 2026-09-21 — direct openssl certificate inspection + strict-TLS curl both succeeded: **valid Let's Encrypt certificate** (CN=estatehub.permetheon.com, valid 2026-08-25 → 2026-11-23), strict HTTPS returns 200. Exit criterion met with recorded evidence. | Gate 04 external links + Launch Gate "All project links work" | Business Owner (if hosting access required) |
| B-011 | Phase 04 / Launch | P2 → RESOLVED | TBMS URL (tbms.permetheon.com) returned no readable text/title on static fetch — availability unconfirmed (client-rendered shell suspected) | Development Owner | Confirm reachability via browser-level inspection; record HTTP status and title | TBMS availability confirmed (or downtime addressed) | RESOLVED 2026-09-21 — URL confirmed LIVE (HTTP 200 via lenient-TLS proxy); presentation mismatch found is tracked separately as B-012 | Gate 04 external links + Launch Gate | Business Owner |
| B-012 | Phases 05–08 / Work page | P1 | Content mismatch: tbms.permetheon.com serves a single-restaurant site ("TableReserve", Hayes Valley SF, Hostinger-hosted) — does not visibly present the Master-documented TBMS product (Customer/Booking Portal + Restaurant Admin Portal). **2026-09-22 evidence update:** owner-provided TBMS screenshot set presents the product under the "TableReserve" brand with the matching two-experience structure (customer booking site + restaurant admin) — materially narrows the mismatch; the URL-target question itself remains | Business Owner + Development Owner | Business Owner clarifies what the URL is intended to present (demo deployment of TBMS platform vs different/placeholder content); Development confirms deployment relationship to TBMS | Verified explanation of the live content and the correct "Visit Project" target for TBMS | OPEN | Gate 05–08 accuracy + Gate 04 external links | Business Owner |

Register rule: new blockers are appended with next ID; resolved blockers are marked and retained for history. Never delete a blocker record.

Verification provenance for B-010/B-011: results of the 2026-09-21 read-only live checks (direct + proxy re-check) recorded in `07_PROJECT_CONTENT.md` (LIVE VERIFICATION notes per project).
