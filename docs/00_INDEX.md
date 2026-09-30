# PERMETHEON WEBSITE — DOCUMENTATION INDEX

Source of truth: **`docs/DESIGN.md` — PERMETHEON Official Website Revamp (Master Project Document)**

New here? Start with the one-page **`../README.md`** at the repo root, then return to this index for the full inventory and traceability map.

All supporting documents in this directory are **derived** from the Master Document.
The Master Document is READ-ONLY for documentation work; it is not superseded by any file here.

---

## 1. AUTHORITY HIERARCHY

```text
1. docs/DESIGN.md              MASTER PROJECT DOCUMENT (highest authority)
2. 01_PRD.md                   Requirements
3. 02_INFORMATION_ARCHITECTURE / 03_ROUTES
4. 04_DESIGN_SYSTEM / 05_COMPONENTS
5. 06_PROJECT_DATA_MODEL / 07_PROJECT_CONTENT / 08_CASE_STUDY_SPECIFICATION
6. 09_PAGE_CONTENT
7. 10_IMPLEMENTATION_PLAN / 11_PHASE_GATES_DEPENDENCIES
8. 12_BLOCKER_MANAGEMENT / 13_DECISION_GOVERNANCE
9. 14_QUALITY_STANDARDS / 15_LAUNCH_CHECKLIST
10. 16_PROJECT_STATE / 17_CHANGELOG
```

If any supporting document conflicts with the Master Document, the Master Document wins and the conflict must be reported and reconciled.

---

## 2. DOCUMENT INVENTORY

| # | Document | Purpose | Master Source Sections | Status | Action |
|---|----------|---------|------------------------|--------|--------|
| — | `DESIGN.md` | Master Project Document — source of truth | — | EXISTS — AUTHORITATIVE | KEEP (protected) |
| 00 | `00_INDEX.md` | Documentation map, inventory, traceability | All | CREATED | KEEP |
| 01 | `01_PRD.md` | Positioning, goals, requirements, content-safety rules | 01, 02, 24, 25, 26 | CREATED | KEEP |
| 02 | `02_INFORMATION_ARCHITECTURE.md` | Pages, navigation, footer, CTA paths, conversion path | 03, 05, 14, 15, 16, 17, 27-P14 | CREATED | KEEP |
| 03 | `03_ROUTES.md` | Complete route table and per-page structure | 07, 08, 23, Phases 04–12 | CREATED | KEEP |
| 04 | `04_DESIGN_SYSTEM.md` | Visual direction, brand system, layout foundations | 02, 27-P01, Phase 01 | CREATED | KEEP |
| 05 | `05_COMPONENTS.md` | Reusable component registry | 23, Phase 01, Phase 03 | CREATED | KEEP |
| 06 | `06_PROJECT_DATA_MODEL.md` | Reusable project data structure | Phase 03, Phase 03 Gate | CREATED | KEEP |
| 07 | `07_PROJECT_CONTENT.md` | Verified content for PCT, TBMS, EstateHub, TravelNest | 04, 06, 25, 27-P03 | CREATED | KEEP |
| 08 | `08_CASE_STUDY_SPECIFICATION.md` | Shared case-study architecture + per-project specs | 08, Phases 05–08, Gate 05–08 | CREATED | KEEP |
| 09 | `09_PAGE_CONTENT.md` | Verified homepage/services/process/about/contact copy | 04–17, Phases 09–12 | CREATED | KEEP |
| 10 | `10_IMPLEMENTATION_PLAN.md` | 18-phase implementation plan with deliverables & DoD | 27 (priorities + phases), dependency map | CREATED | KEEP |
| 11 | `11_PHASE_GATES_DEPENDENCIES.md` | Dependency model, all gates, launch gate | 28 (dependencies & gates) | CREATED | KEEP |
| 12 | `12_BLOCKER_MANAGEMENT.md` | Blocker system: priorities, owners, register | 29 | CREATED | KEEP |
| 13 | `13_DECISION_GOVERNANCE.md` | Decision priorities, deadlines, template, log | 30, 31 | CREATED | KEEP |
| 14 | `14_QUALITY_STANDARDS.md` | Responsive, motion, SEO, a11y, performance, QA | 18, 20, 21, 22, 27-P09–13, Phases 13–17 | CREATED | KEEP |
| 15 | `15_LAUNCH_CHECKLIST.md` | Release-candidate and launch gates | FINAL LAUNCH CHECKLIST, RELEASE/LAUNCH GATES | CREATED | KEEP |
| 16 | `16_PROJECT_STATE.md` | Current project readiness, open gaps, next actions | Derived state (no master source) | CREATED | KEEP — update frequently |
| 17 | `17_CHANGELOG.md` | Documentation change log | Derived (governance requirement) | CREATED | KEEP — update on every change |
| 18 | `18_BUSINESS_OWNER_INPUT_REQUEST.md` | Consolidated owner input request (all open blockers/decisions) | Derived from 12 §6 + 13 §4 + live verification runs | CREATED | KEEP until all items resolved, then mark CLOSED |
| 19 | `19_OWNER_INPUT_REQUEST_PLAIN.md` | External, plain-language variant of doc 18 (no internal IDs/jargon) | Derived from doc 18 | CREATED | SEND OUTSIDE TEAM — internal mapping: items 1–5→D1 decisions; 6–10→D2; 11–12→B-002/B-006; 13→B-007; 14→Blocker-20 approvals |
| 20 | `20_PROCESS_PAGE_COPY_DRAFT.md` | Process page expanded per-stage copy — DRAFT, pending owner approval (B-007) | Master Sec. 11/10/12/15 + Phase 10 (derivation rules stated in-file) | CREATED — DRAFT NOT APPROVED | APPROVE → merge into 09, resolve B-007; never publish without approval |

Deliberately NOT created (would duplicate the Master without adding value):
Per-project case-study files (PCT/TBMS/EstateHub/TravelNest specs live in `08_CASE_STUDY_SPECIFICATION.md`), separate SEO/A11y/Performance/Testing files (consolidated in `14_QUALITY_STANDARDS.md`), separate analytics/security/environment docs (Master defines no verified requirements for them — see Missing Inputs below).

---

## 3. GAP ANALYSIS (as of 2026-09-21)

**Missing before this run:** all supporting documentation. Only `DESIGN.md` existed.
**Now closed:** requirements, IA, routes, design system, components, project data model, project content, case-study spec, page content, implementation plan, gates/dependencies, blocker management, decision governance, quality standards, launch checklist, project state, changelog.

**Remaining gaps that cannot be closed by documentation alone** → see `16_PROJECT_STATE.md` (Missing Inputs register).

---

## 4. TRACEABILITY MAP (examples of the required chain)

```text
MASTER REQUIREMENT
      ↓ DOCUMENT        ↓ PHASE          ↓ IMPLEMENTATION         ↓ VERIFICATION
"Reusable project      06_PROJECT_DATA  Phase 03                  Homepage + Work +
system" (Sec. 27-P03,  MODEL.md         → project schema +        case studies consume
Phase 03, Gate 03)     08_CASE_STUDY…   data source               same data source → Gate 03
                                       ↓
"Fictional 5th project test" (Gate 03)  → add test project w/o touching components → PASS

"Primary conversion path" (Sec. 27-P14, Phase 18)
      ↓ 02_INFORMATION_ARCHITECTURE.md
      ↓ Phase 18 → CTA journey test desktop + mobile → Gate 18 PASS → Release Candidate

"Real screenshots before final polish" (Critical Rule 01)
      ↓ 07_PROJECT_CONTENT.md (asset status per project)
      ↓ Phases 05–08 → case-study galleries → Gate 05–08 accuracy checks

"No fabricated claims" (Sec. 24, Gate 05–08, Phase 17)
      ↓ 01_PRD.md (content safety rules) + 09_PAGE_CONTENT.md
      ↓ Phase 17 factual QA → verified-only copy audit → Gate 17 PASS
```

Full chain rule: every material implementation requirement must be traceable
**Master Section → Document → Phase → Task → Verification → Gate**.
Documents above cite their Master source sections for this purpose.

---

## 5. MAINTENANCE RULES

1. Master Document changes → check every document's "Master Source Sections" and update affected docs in the same pass (see `17_CHANGELOG.md`).
2. New material decision → `13_DECISION_GOVERNANCE.md` (never chat-only).
3. New blocker → `12_BLOCKER_MANAGEMENT.md` register.
4. Status of the whole system → `16_PROJECT_STATE.md`.
