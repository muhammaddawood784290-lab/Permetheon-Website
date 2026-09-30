# 13 — DECISION GOVERNANCE

Source: Master Project Document — Sections 30 (Decision Deadlines & Escalation), 31 (Decision Record Template & Log).
Status: EXTRACTED FROM MASTER. Priorities, deadlines, escalation schedules and the template are fixed by the Master.

---

## 1. PRIORITY MODEL (fixed — non-overlapping timing)

| Priority | Meaning | Decision Window | Escalation Model | Blocks |
|---|---|---|---|---|
| **D0** | Launch / production critical | **4 hours** | Hour-level (T+1h reminder, T+2h escalation, T+4h deadline) | Launch |
| **D1** | Current phase blocking | **1 business day** | Business-day (T+4 business hours reminder, T+1 bd primary deadline, T+2 bd final) | Current phase |
| **D2** | Non-blocking | **2 business days** | Daily (T+1 bd reminder; at T+2 bd use most reasonable reversible option + document assumption) | No |
| **D3** | Polish / optional | **3 business days** | Backlog-first (T+2 bd reminder; T+3 bd → polish backlog) | No |

**D0 and D1 must never share an escalation schedule.** Hierarchy fixed: **D0 = hours · D1 = business days · D2 = days · D3 = backlog.**

Classification rule — ask: "Can the website safely continue toward production without this decision?"
- NO, launch/safety/production immediately affected → **D0**
- NO, current phase can't complete but production not immediately at risk → **D1**
- YES, independent work continues → **D2 or D3**

D0 is ONLY for production/launch-critical issues (launch approval, domain, critical production config, critical contact destination, security, critical legal/business info, critical pre-launch factual correction). NEVER for design feedback, copy preference, optional features, minor UI, general preferences.

Time rule: business-day clocks use the team's agreed working timezone/hours; weekends excluded. D0 clock starts immediately once designated a production/launch emergency.

## 2. ESCALATION OWNERS (fixed)

- **D0:** Decision Owner → (T+2h) Permetheon Business Owner → Final Project Authority. (Same person ⇒ go straight to Final Project Authority.)
- **D1:** Decision Owner → Designated Backup/Supporting Owner → Permetheon Business Owner (does NOT auto-escalate to final authority).
- **D2:** Decision Owner → Supporting Owner → reversible implementation decision + documented assumption.
- **D3:** none — backlog after deadline.

Status flow: **OPEN → APPROACHING DEADLINE → ESCALATED → RESOLVED → VERIFIED** (or OPEN → DEFERRED → BACKLOG). RESOLVED ≠ VERIFIED — verification confirms implementation matches the decision.
Change rule: never overwrite a resolved decision; open a new record that supersedes it. Dependency rule: resolve upstream decisions first; don't penalize dependent decisions' owners for upstream delay.

## 3. DECISION RECORD TEMPLATE (fixed — Master Sec. 31)

```text
DECISION ID: D-XXX
DATE: YYYY-MM-DD
PHASE: Phase XX — [name]
CATEGORY: [Brand / Design / Content / Technical / Scope / Business / Launch]
PRIORITY: [D0 / D1 / D2 / D3]
DECISION OWNER: [name/role]
REQUESTED BY: [name/role]
DECISION REQUIRED: [the exact question]
CONTEXT: [why required]
OPTIONS: A / B / (C)
RECOMMENDED IMPLEMENTATION: [path, not treated as approved until confirmed]
DECISION: [final approved option]
RATIONALE: [why]
IMPACT: Scope / Design / Development / Timeline / Dependencies
BLOCKERS CREATED: [none / IDs]
BLOCKERS RESOLVED: [none / IDs]
AFFECTED PHASE GATE: [none / gate]
DEADLINE: [date + time]
ESCALATION PATH: [primary → backup → final]
STATUS: [OPEN / APPROACHING DEADLINE / ESCALATED / RESOLVED / VERIFIED / DEFERRED]
DECISION DATE: [date + time]
IMPLEMENTATION OWNER: [name/role]
VERIFICATION REQUIRED: [what to check post-implementation]
VERIFICATION RESULT: [PASS / FAIL / NOT YET VERIFIED]
LINKS / REFERENCES: [Figma, URL, document, blocker ID…]
```

When a record is required: any decision affecting scope, brand, design system, navigation, project presentation, case-study content, technology, architecture, integrations, SEO, a11y, performance, launch readiness, positioning or any phase gate. Fully-specified implementation details do not need records.

## 4. DECISION LOG

| ID | Date | Phase | Category | Priority | Decision Owner | Decision Required | Deadline | Status |
|---|---|---|---|---|---|---|---|---|
| D-001 | 2026-09-21 | Phase 01 | Brand | D1 | Business Owner | Approve visual foundation (typography, colors, spacing, grid, buttons, cards, breakpoints) per Gate 01 | +1 business day | RESOLVED 2026-09-21 — REV 2 approved as working brand foundation (ember accent, Space Grotesk display, fluid scale); official assets may replace tokens later |
| D-002 | 2026-09-21 | Phase 02 | Content | D1 | Business Owner | Approve homepage positioning (hero, section order, CTA strategy) per Gate 02 | +1 business day | RESOLVED 2026-09-21 — approved as documented |
| D-003 | 2026-09-21 | Phase 03 | Architecture | D1 | Development Owner | Approve shared project data model (06_PROJECT_DATA_MODEL.md) incl. per-project filter mapping | +1 business day | OPEN |
| D-004 | 2026-09-21 | Phase 05–08 | Content | D1 | Business Owner | Verify case-study features against live products (capability lists, 07_PROJECT_CONTENT.md) | +1 business day | RESOLVED — owner confirms **all four projects' capability lists CONFIRMED** (PCT, TBMS, EstateHub, TravelNest as documented); B-003 closed; 07 marked VERIFIED |
| D-005 | 2026-09-21 | Phase 12 / Launch | Launch | **D0 at launch** | Business Owner | Approve production contact flow (submission destination + verified contact details) | Before launch; D0 window once launch-critical | RESOLVED 2026-09-21 (approach) — email/form service with env-based config; provider selection + verified contact email still required at implementation (Blocker 08) |
| D-006 | 2026-09-21 | Phase 16 | Technical | D2 | Development Owner | Confirm website technology stack/framework for implementation | +2 business days | OPEN |
| D-007 | 2026-09-21 | Launch | Content | D2 | Business Owner | Privacy & Terms pages: provide content, defer, or remove footer links | +2 business days | RESOLVED — **footer legal links removed** (no 404s at launch); re-add when real content exists |
| D-008 | 2026-09-21 | Phase 16 | Launch | D2 | Business Owner + Development | Analytics + error monitoring: required or not; provider selection if yes | +2 business days | RESOLVED — **not required** for launch; revisitable anytime, no code added |
| D-009 | 2026-09-21 | Phase 05–08 | Content | D2 | Business Owner + Development | Verified technology lists per project (else publish generic "Custom Web Application") | +2 business days | RESOLVED — **generic wording authorized**; case-study Technology sections publish "Custom Web Application" as final; stacks revisitable per project if later verified |
| D-010 | 2026-09-21 | Phases 05–08 / Work page | Content / Business | D1 | Business Owner | Clarify what tbms.permetheon.com presents (demo deployment of the TBMS platform vs unrelated/placeholder content) and confirm the correct "Visit Project" target for TBMS | +1 business day — gates TBMS case-study content + Work-page CTA | OPEN — owner responded "not sure yet" (2026-09-21); deferred, reminder due per D1 clock |
| D-011 | 2026-09-21 | Phase 15 | SEO / Domain | D2 | Business Owner | **Canonical production domain** — the Master does not define it; `permetheon.com` is used as a placeholder in metadata, sitemap.xml, robots.txt, OG image URLs and canonical tags until confirmed | +2 business days — a wrong domain in metadata would be a launch defect | OPEN — all absolute URLs are generated from ONE constant in `src/lib/seo.ts`; swapping the domain when confirmed is a one-line change
| D-012 | 2026-09-21 | Content (all phases) | Content accuracy | D2 | Business Owner | Live-site numerical claims (TravelNest "12+ Years / 50K+ Trips / 4.9★ / 50,000+"; EstateHub "12 listings / 5 agents / 7 categories / 6 cities") — may they be used on the Permetheon site? | +2 business days | RESOLVED — **never publish on permetheon.com**; accuracy unverified, Blocker-20 rule enforced with owner backing

Log = tracking index only; full records remain the source of truth (create full records using the template when decisions are worked).

Numbering note: the Master's log defines D-001…D-005; this log preserves those IDs and meanings (D-004 broadened from the Master's example "Phase 06" to "Phase 05–08" to match the collective case-study gate; D-005 extended to "Phase 12 / Launch" per the Master's D0 launch classification). The Master's Section 31 uses "D-006" inside a worked **template example** (shared data model — RESOLVED/PASS); it is an illustration, not a project record, so this log continues at D-006 for the first new live decision.

## 6. OWNER-REPLY PROCESSING PROTOCOL (prepared 2026-09-21)

When Business Owner replies arrive (pasted in chat or dropped as a file in the workspace), process each answer through this fixed sequence — no interpretation, no invention:

```text
ANSWER RECEIVED
  → 1. Tracker row (16_PROJECT_STATE.md): ⏳ → ✅ ANSWERED (+ update snapshot count)
  → 2. Decision Record (13 §4 → full record via §3 template): fill DECISION, RATIONALE, DECISION DATE, STATUS=RESOLVED
  → 3. Blocker row (12 §6): update STATUS (RESOLVED / narrowed / VERIFIED when implemented)
  → 4. Affected documents (impact map below): revise the same pass
  → 5. 17_CHANGELOG.md: append run entry
```

**Per-item impact map (prepared in advance):**

| Item | Answer resolves | Documents to revise on receipt |
|---|---|---|
| 1 Brand foundation | D-001, B-001 (+ breakpoint sub-decision) | 04_DESIGN_SYSTEM (fill TBD rows), 16 state, 14 §1 breakpoints |
| 2 Homepage direction | D-002 | 16 state (gate 02 readiness) |
| 3 Product facts | D-004, B-003 | 07_PROJECT_CONTENT capability lists (mark VERIFIED/removed), 08 specs if sections change |
| 4 TBMS URL | D-010, B-012 | 07 TBMS verification note, 03_ROUTES §3 external links, 18/19 request docs |
| 5 Contact flow | D-005, B-005 | 09 contact section, 03_ROUTES §4, 15 launch checklist open-items |
| 6 Technologies | D-009, B-004 | 07 technology notes, 14 §7 note, 09 Technology section field |
| 7 Privacy/Terms | D-007, B-008 | 09 footer/legal, 03_ROUTES sitemap, 15 open-items |
| 8 Analytics/monitoring | D-008, B-009 | 14 §7, 15 launch checklist |
| 9 Data model + filters | D-003 | 06 (filter mapping), 09 filter-mapping flag → approved |
| 10 Stack preference | D-006 | 16 state, 05 §4 note |
| 11 Screenshots | B-002 | 07 asset statuses (MISSING → RECEIVED/VERIFIED), 12 register |
| 12 Domain/production | B-006 | 15 launch checklist, 12 register |
| 13 Process copy | B-007 | 09 Process section (TBD → content) |
| 14 Live-site numbers | Blocker-20 approvals | 07 content-safety warnings → resolved/approved |

**Declined/deferred answers:** tracker ⏭ DECLINED/DEFERRED; decision record STATUS=DEFERRED; D3-class items move to polish backlog; nothing is silently dropped.

Replies cannot currently be processed because **none have arrived** — snapshot remains 0/14 (see 16 tracker). Do not fabricate or pre-fill any answer.
