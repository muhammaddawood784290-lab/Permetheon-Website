# 18 — BUSINESS OWNER INPUT REQUEST (CONSOLIDATED)

**To:** Permetheon / Business Owner (OWNER A)
**From:** Documentation / Implementation team
**Date issued:** 2026-09-21
**Scope:** All OPEN blockers (B-001…B-012) and OPEN decisions (D-001…D-010). *(B-011 — TBMS availability — is already RESOLVED; no action needed.)*
**Registers of record:** `12_BLOCKER_MANAGEMENT.md` §6 · `13_DECISION_GOVERNANCE.md` §4

---

## HOW TO RESPOND

Reply per item using this one-line form:

```text
D-001: OPTION A — note (optional)
B-001: ATTACHED — note
```

Everything below is also answerable item-by-item; a partial reply is better than none. Urgent items are marked **⏱** — reply to those first (1 business day window under the D1 model).

---

## PART 1 — URGENT (D1 window: 1 business day)

### ⏱ 1. D-001 — Approve the visual foundation (unblocks Gate 01 → Phases 01–02)
Provide (or attach existing brand references):
- [ ] Logo (current files + variations)
- [ ] Brand colors (hex values or references to the existing Permetheon identity)
- [ ] Typography preference (if already defined)
- [ ] Approval to lock: typography / colors / spacing / grid / buttons / cards / breakpoints

**OR** reply: **B-001: TEMPORARY OK** — authorizing the Design Owner to build a temporary design system from the existing Permetheon website identity, marked TEMPORARY — REQUIRES BRAND APPROVAL (Blocker 01 rule). Phase 01 can start immediately under this path.

### ⏱ 2. D-002 — Approve homepage positioning (unblocks Gate 02)
- [ ] Approve homepage section order and copy baseline as documented in `09_PAGE_CONTENT.md` (Hero → Capability strip → Featured Work → Services → The Difference → Process preview → Why Permetheon → CTA → Footer)
- [ ] Approve CTA strategy: primary **Start a Project** / secondary **Explore Our Work**

### ⏱ 3. D-004 — Verify case-study features against the live products (unblocks Gate 05–08 accuracy)
Confirm the capability lists in `07_PROJECT_CONTENT.md` exist in the actual systems (reply per project: CONFIRMED / list corrections / REMOVE unconfirmed items):
- [ ] **PCT** — projects, teams, tasks, reviews, activity, reports, notifications, user management
- [ ] **TBMS** — table management, reservations, customer booking portal, admin portal, availability *(note: see D-010 first)*
- [ ] **EstateHub** — listings, discovery, details, scheduling *(scheduling partially verified — confirm dedicated meeting UI)*
- [ ] **TravelNest** — search, date/guest/budget selection (categories, destinations, packages, stories, gallery already verified live)

### ⏱ 4. D-010 — What does tbms.permetheon.com present? (gates TBMS CTA + case study)
The URL is live but currently shows "TableReserve", a single-restaurant site. Please clarify:
- [ ] **OPTION A:** It's a themed demo deployment of the TBMS platform → we document it as such and target screenshots accordingly
- [ ] **OPTION B:** It's unrelated/placeholder content → we remove the "Visit Project" CTA and source TBMS visuals elsewhere
- [ ] Correct external link target for TBMS (if any)

### ⏱ 5. D-005 — Production contact flow (D0 at launch — needed before Phase 12 finalizes)
- [ ] Verified contact destination (email and/or other) for inquiries
- [ ] Preferred submission mechanism (existing backend / email service / form service / CRM / serverless)
- [ ] Contact details to publish, if any

---

## PART 2 — SECOND PRIORITY (D2 window: 2 business days)

### 6. D-009 — Verified technologies per project (else case studies say "Custom Web Application")
Confirm per project (or authorize the generic fallback):
- [ ] PCT [ ] TBMS [ ] EstateHub [ ] TravelNest — stack lists from repositories/deploy configs
*(Team-observable notes so far: TravelNest homepage shows an Elementor/WordPress artifact; TBMS shows Hostinger hosting. Neither is publishable without your confirmation.)*

### 7. D-007 — Privacy & Terms pages (footer links must not 404 at launch)
- [ ] Provide legal page content · **or** [ ] authorize removing the footer links · **or** [ ] defer with a plan

### 8. D-008 — Analytics + error monitoring (Launch Gate: "configured if required")
- [ ] Required? [ ] Provider if yes [ ] not required

### 9. D-003 — Project data model approval (can proceed in parallel with Phase 01)
- [ ] Approve the shared project data model in `06_PROJECT_DATA_MODEL.md`
- [ ] Approve the proposed Work-page filter mapping (PCT→Internal Platforms · TBMS→Booking Systems · EstateHub→Websites/Booking Systems · TravelNest→Websites) — or correct it

### 10. D-006 — Implementation stack (Development Owner decision; Business Owner visibility)
- [ ] Framework/hosting preferences or constraints for the website itself

---

## PART 3 — INPUTS WITHOUT DECISION NUMBERS (blockers B-002, B-006, B-007, B-008, B-011-closure)

### 11. B-002 ⏱ — Real project screenshots (longest-lead item; P1 for Gate 05–08)
For each project: dashboard/homepage, primary workflow, key features, secondary screens, responsive/mobile views.
- [ ] Provide existing captures/assets/Figma exports, **or**
- [ ] Authorize the team to capture from the live products (developer may assist; TBMS capture is gated on D-010)

### 12. B-006 — Deployment, domain & access (P0 before launch)
- [ ] Domain/hosting/DNS access or credentials path for permetheon.com production
- [ ] Confirm production environment target

### 13. B-007 — Expanded Process page copy (per-stage activities, deliverables, client involvement)
**A draft is ready for your review: `20_PROCESS_PAGE_COPY_DRAFT.md`** — derived strictly from the six verified stage definitions, containing no factual claims.
- [ ] Approve the draft as-is · [ ] approve with edits (mark changes) · [ ] replace with your own copy

### 14. B-008 — Privacy/Terms ownership → covered by D-007 above (single reply suffices)

### 15. Content-accuracy approvals from live checks (Blocker 20 rule — record only today)
- [ ] **TravelNest** live stats ("12+ Years", "50K+ Trips", "4.9★", "50,000+ travelers") — approve as factually grounded, or confirm they stay off the Permetheon site
- [ ] **EstateHub** live counters (12 listings / 5 agents / 7 categories / 6 cities) — same approval or exclusion

---

## DEADLINE MODEL (Master Sec. 30 — non-overlapping)

| Priority | Items | Window |
|---|---|---|
| D1 (1 business day) | D-001, D-002, D-004, D-010, D-005 | Reply first |
| D2 (2 business days) | D-009, D-007, D-008, D-003, D-006 | Second pass |
| P0 at launch | B-006 deployment access | Must exist before Launch Gate |
| P1 for phases | B-001, B-002, B-003, B-005, B-012, B-008 | Before dependent gates |

Unresolved D1 items pause only their dependent phases — independent work continues (Master Sec. 30). Nothing is invented in the meantime; gaps are held open with TBD/BLOCKED markers.

---

## WHAT HAPPENS AFTER YOUR REPLY

Each answer is recorded as a Decision Record (`13_DECISION_GOVERNANCE.md` template), the affected blocker rows are updated (RESOLVED/VERIFIED), affected documents are revised the same day, and unblocked phases proceed per `10_IMPLEMENTATION_PLAN.md`. Partial replies unblock exactly what they cover.
