# 02 — INFORMATION ARCHITECTURE

Source: Master Project Document — Sections 03, 05, 14, 15, 16, 17, 27-P14, Phase 18.
Status: EXTRACTED FROM MASTER.

---

## 1. SITE MAP

```text
PERMETHEON
├── /                     Homepage
├── /work                 Work (portfolio)
├── /case-studies         Case-study index ("Behind the Build.")
│   ├── /case-studies/pct
│   ├── /case-studies/tbms
│   ├── /case-studies/estatehub
│   └── /case-studies/travelnest
├── /services             Services ("What We Build")
├── /process              Process ("How We Build")
├── /about                About
├── /contact              Contact ("Let's build something useful.")
└── footer                Privacy · Terms (content TBD — DECISION REQUIRED)
```

No additional pages are defined by the Master. Adding pages (blog, pricing, team, careers…) requires a recorded decision.

---

## 2. NAVIGATION

### Desktop (Master Sec. 03)

| Element | Detail |
|---|---|
| Logo | PERMETHEON (links to `/`) |
| Items | Work · Services · Process · About |
| CTA | **Start a Project** |
| Behavior | Sticky |

### Mobile (Master Sec. 03)

| Element | Detail |
|---|---|
| Visible | Logo + Menu |
| Menu items | Work · Services · Process · About · Contact |

---

## 3. FOOTER (Master Sec. 17)

- **Brand:** PERMETHEON — **Digital Products. Business Systems. Built to Work.**
- **Navigation:** Work · Services · Process · About · Contact
- **Featured Work:** PCT · TBMS · EstateHub · TravelNest (links per 03_ROUTES.md external links + case studies)
- **Legal:** Privacy · Terms
- **Copyright:** © Permetheon

---

## 4. CTA SYSTEM

| CTA | Where defined | Destination |
|---|---|---|
| **Start a Project** (primary, site-wide) | Sec. 03 (nav), Sec. 04 (hero), Sec. 15 (CTA band), Sec. 27-P08 | `/contact` |
| **Explore Our Work** (secondary) | Sec. 04 (hero), Sec. 15 (CTA band) | `/work` |
| **Explore PCT / TBMS / EstateHub / TravelNest** (project CTAs) | Secs. 04, 06 | Case-study routes; external project links listed in 03_ROUTES.md |
| **View Case Study** | Sec. 07 (Work cards) | `/case-studies/{slug}` |
| **Start the Conversation** | Sec. 16 (contact form submit) | Contact form submission |
| **Visit Project** | Phase 18 CTA list | External project URL (only when project is live & link verified) |

Primary conversion path (must be friction-free, Master Sec. 27-P14):
**Discover Permetheon → See Real Work → Understand Capability → Read Case Study → Start a Project**

Every major page must have a clear path toward contacting Permetheon (Master Sec. 27-P08).

---

## 5. HOMEPAGE SECTION ORDER (Master Sec. 27-P02 — required order)

1. Navigation
2. Hero
3. Capability strip
4. Featured Work
5. Services
6. Business-focused value proposition ("The Difference")
7. Process
8. Why Permetheon
9. CTA
10. Footer

The homepage must immediately communicate: what Permetheon does + builds + has built + how it works + how to start a project — without visiting another page.

---

## 6. VISUAL SECTION RHYTHM (Master Sec. 02)

Dark premium hero → Light project sections → Dark services → Light case studies → Dark CTA.
Existing Permetheon visual identity is the foundation; do not redesign the brand identity unless necessary.
