# 07 — PROJECT CONTENT (VERIFIED)

Source: Master Project Document — Sections 04, 06 (Projects 01–04), 08 (case studies), 25, 28 (project sources), Phase 03.
Status: EXTRACTED FROM MASTER. Cross-project assumptions prohibited. No unverified feature may be added.

---

## PROJECT 01 — PCT

| Field | Verified value (Master Sec. 06) |
|---|---|
| Name | **PCT** |
| Full positioning | **Permetheon Command Terminal** |
| Category | Internal Business Platform |
| Short description | A centralized internal command terminal built to manage projects, teams, tasks and day-to-day digital operations across Permetheon's developers, designers and other teams. |
| Positioning rule | PCT is NOT primarily presented as a generic CRM. Present it as: **An internal project and team operations command platform.** |
| Project URL | https://pct.permetheon.com/ |
| Case-study URL | /case-studies/pct |
| Metadata tags | Internal Platform · Project Management · Team Operations · Custom Software |
| CTA | **Explore PCT** |

Capabilities to showcase (Master Sec. 06 — only if they exist in the actual system):
Project management · Team management · Developer workflows · Designer workflows · Tasks · Reviews · Activity · Notifications · Reports · User management · Operational visibility.

Case-study modules (Master Phase 05 — "Only display modules that actually exist"): Dashboard · Projects · Teams · Tasks · Reviews · Activity · Reports (+ Settings/Notifications/Users where verified).

**Asset status (screenshots):** RECEIVED + INTEGRATED (2026-09-22, B-002 partial) — owner provided 6 verified screens: dashboard, projects, tasks, reviews, notifications, activity (`public/screenshots/pct/`). No duplicates found; captures unaltered. Integrated as the PCT showcase carousel (story order: main experience → projects → tasks → reviews → notifications → activity); captions name only what each screen shows. Responsive-view captures remain optional polish.

**Feature verification:** **VERIFIED — D-004 RESOLVED (2026-09-21):** Business Owner confirms all documented capabilities exist in the live system (Blocker 03 closed). Publish as documented.

**LIVE VERIFICATION — 2026-09-21 (read-only fetch):** https://pct.permetheon.com/ responds HTTP 200. Page title verified: "PCT — Permetheon Command Terminal" (matches Master positioning). Application content is client-rendered — no capability text extractable via static fetch. Capability list was UNVERIFIED via static fetch — now **owner-confirmed (D-004, 2026-09-21)**.

---

## PROJECT 02 — TBMS

| Field | Verified value (Master Sec. 06) |
|---|---|
| Name | **TBMS** |
| Category | Restaurant Reservation & Table Management |
| Positioning | A restaurant reservation and table management platform with dedicated booking and administrative experiences. |
| Description | TBMS brings restaurant reservations, table management and operational control into one connected digital system. |
| Core product structure | Customer / Booking Portal + Restaurant Admin Portal |
| Project URL | https://tbms.permetheon.com/ |
| Case-study URL | /case-studies/tbms |
| Metadata tags | Restaurant · Reservation System · Booking Portal · Admin Platform |
| CTA | **Explore TBMS** |

Capabilities: Restaurant table management · Reservation/booking · Customer booking portal · Admin portal · Reservation management · Table availability · Operational management. (Only include features verified in the actual product.)

Product flow (verified): Customer → Booking Portal → Reservation → Restaurant Admin → Table Management → Operational control.

**Asset status (screenshots):** RECEIVED + INTEGRATED (2026-09-22, B-002 partial) — owner provided 8 verified screens forming the documented two-experience story: customer booking site (home, restaurant profile, featured dining, hours/location with staff sign-in) + restaurant admin (dashboard, reservation management, tables, customers) (`public/screenshots/tbms/`). No duplicates; captures unaltered. Integrated as the TBMS showcase carousel (customer experience first, restaurant admin second); captions name only what each screen shows. **B-012 note:** the set presents the product under the live "TableReserve" brand with the two-experience structure — this materially narrows the B-012 mismatch question; D-010 (what the URL presents / "Visit Project" target) remains OPEN and unaffected.
**Feature verification:** **VERIFIED — D-004 RESOLVED (2026-09-21)** (owner confirms capabilities as documented; live-site presentation question remains separately tracked as B-012/D-010). **Technology:** **GENERIC WORDING AUTHORIZED — D-009 RESOLVED (2026-09-21):** case study publishes "Custom Web Application"; stacks revisitable if later verified (Blocker 04 closed).

**LIVE VERIFICATION — 2026-09-21 (read-only fetch, updated after proxy re-check):**

1. Direct fetch: no readable text/title extracted (shell or non-extractable markup).
2. Lenient-TLS proxy fetch: **HTTP 200 — URL is LIVE and serving content.**

**CONTENT MISMATCH FOUND:** the live page presents **"TableReserve — Seasonal Dining in Hayes Valley, San Francisco"** — a single-restaurant marketing/reservation site (candlelit dining room story, seasonal menu, cellar list, opening hours, "Book a window table" → `/reserve`), with images hosted on images.hostinger.com. This does **not** visibly present the Master-documented TBMS product structure (Customer/Booking Portal + Restaurant Admin Portal for reservation & table management).

Possible explanations (UNVERIFIED — do not treat any as fact): the TableReserve site may be a themed demo deployment of the TBMS platform, or the URL may currently serve different/placeholder content. Requires Business Owner + Development clarification (→ B-012, D-010).

Documented TBMS capabilities verifiable from this content: none of the platform capabilities (table management, admin portal, reservation management, table availability) are externally verifiable. A booking/reservation flow link is visible (`/reserve`).

**CONTENT-SAFETY WARNING:** restaurant details (Hayes Valley SF, 40 seats, hours, menu/cellar counts) appear to be sample/demo marketing content — NEVER republish on the Permetheon website (Master Sec. 24; Blocker 20).

Technology: Hostinger hosting artifact observed — classification OBSERVED — Project Source; not publishable without repository confirmation (Blocker 04 / D-009).

Blocker trail: availability question (B-011) RESOLVED — URL live. The presentation mismatch is tracked as **B-012** (P1) with decision **D-010**; must be resolved before the Work-page "Visit Project" CTA and TBMS case-study claims are finalized (Gate 05–08 accuracy).

---

## PROJECT 03 — ESTATEHUB

| Field | Verified value (Master Sec. 06) |
|---|---|
| Name | **EstateHub** |
| Category | Real Estate Platform |
| Positioning | A real-estate platform designed around property discovery, listings and meeting scheduling. |
| Description | EstateHub connects property listings with a streamlined experience for discovering properties and scheduling meetings. |
| Core experience | Property Listings + Property Discovery + Meeting Booking |
| Project URL | https://estatehub.permetheon.com/ |
| Case-study URL | /case-studies/estatehub |
| Metadata tags | Real Estate · Property Platform · Listing System · Meeting Booking |
| CTA | **Explore EstateHub** |

Visual flow (verified): Browse Properties → View Property → Explore Details → Schedule Meeting.

**Asset status (screenshots):** RECEIVED + INTEGRATED (2026-09-22, B-002 partial) — owner provided 9 captures → 6 unique screens after duplicate removal (`public/screenshots/estatehub/`): home hero + search (+stats), featured listings, properties grid + filters, admin dashboard (stats + listing approvals), admin inquiries (buyer messages with property context), footer ("Prototype prepared by Permetheon"). Captures unaltered; integrated as the EstateHub showcase carousel in journey order (discover → browse → filter → platform management). **Property detail + meeting-scheduling screens were NOT provided** — those two sections keep marked B-002 placeholders; the D-004-verified scheduling capability therefore still has no published visual. Responsive views remain optional polish.
**Constraints:** Do not invent property statistics or client outcomes (Master Sec. 08 CS-03). **Feature verification:** **VERIFIED — D-004 RESOLVED (2026-09-21)**; scheduling entry point additionally evidence-verified ("schedule a viewing" in app bundle, see above). **Technology:** **GENERIC WORDING AUTHORIZED — D-009 RESOLVED (2026-09-21)** ("Custom Web Application").

**LIVE VERIFICATION — 2026-09-21 (read-only fetch, updated after proxy re-check):**

1. Direct HTTPS fetch: FAILS — TLS certificate verification error (HTTPS and HTTP-redirect both fail strict verification in the verification environment).
2. Lenient-TLS proxy fetch: **HTTP 200 — site is LIVE and serving full server-rendered content.** Title verified: "EstateHub — Find Your Next Property". Hero positioning: "Find a place you'll love to live — or work." (Residential & Commercial).

Verified visible (VERIFIED — Project Source):
- Property Listings ✓ (featured listings with price, beds/baths/sqft, location, amenities)
- Property Discovery ✓ ("Browse by category": Apartment · Condo · House · Office · Retail Space · Villa · Warehouse)
- Property Details ✓ (per-property pages: /properties/{id} with "View Property")
- Agent portal ✓ ("Agent Sign In" + agent dashboard: "Manage your listings, track inquiries")
- Contact/Inquiry flow ✓ ("Contact Agent" → #inquiry anchor on property pages)

Partially verified → **VERIFIED 2026-09-21 (evidence upgrade):** Master flow step "Schedule Meeting" — the application bundle (client-rendered SPA) contains a user-facing **"schedule a viewing"** flow string, confirming a scheduling entry point exists in the product. Consistent with the Master's meeting-scheduling positioning; full UI confirmation on the property detail page during screenshot capture (B-002) is no longer accuracy-critical.

**LIVE RE-VERIFICATION 2026-09-21 (evening):** the site has changed presentation — homepage is now a client-rendered Vite SPA shell (only `/assets/…` hrefs in static markup). The run-002/003 server-rendered observations (listings, categories, agent portal) remain the recorded evidence for those features; the **"schedule a viewing"** string was verified present inside the live application bundle (`/assets/index-hbJS5wRW.js`). TLS: valid Let's Encrypt cert (see B-010 resolution).

Technology: no page-builder artifacts observed in extracted markup — technology remains UNKNOWN — VERIFICATION REQUIRED (Blocker 04 / D-009). Do not publish technology claims.

CONTENT-SAFETY WARNING: live homepage displays counters ("12 Curated listings", "5 Expert agents", "7 Categories", "6 Cities covered") and sample property data (prices, addresses). **D-012 (2026-09-21): these counters are NEVER published on permetheon.com**; sample property data remains the live product's own demo content — do not reproduce.

Blocker status: **B-010 remains OPEN** — the site is live, but the blocker's exit criterion ("EstateHub URL loads securely, valid HTTPS") is unmet because strict TLS verification still fails externally. Determination needed from Development Owner: if the certificate is genuinely invalid/expired/mis-chained for real browsers → reissue; if it validates from the team environment (browser check / SSL Labs) and the failure is specific to the verification sandbox → record that evidence and close B-010 with VERIFIED status. Gate impact unchanged: Gate 04 external-link check + Launch Gate "All project links work".

---

## PROJECT 04 — TRAVELNEST

| Field | Verified value (Master Sec. 06) |
|---|---|
| Name | **TravelNest** |
| Category | Travel Platform |
| Positioning | A travel discovery and trip-planning experience built around destinations, packages and traveler needs. |
| Description | TravelNest brings destination discovery, tour packages and trip planning into one engaging travel experience. |
| Project URL | https://travelnest.permetheon.com/ |
| Case-study URL | /case-studies/travelnest |
| Metadata tags | Travel · Digital Experience · Tour Platform · Trip Planning |
| CTA | **Explore TravelNest** |

Current visible experience (verified list — Master Sec. 06):
Destination discovery · Travel search · Destination selection · Date selection · Guest selection · Budget selection · Adventure categories · Featured destinations · Tour packages · Traveler stories · Travel gallery.

Experience flow (verified): Discover → Search → Explore → Choose → Plan.

**Asset status (screenshots): RESOLVED — B-002 CLOSED (2026-09-22).** Owner-provided set received + integrated: 7 unique screens (home hero + search with destination/date/guests/budget, Adventure Categories, Popular Destinations, Featured Tour Packages, Why TravelNest trust panels, Traveler Stories, footer) → `public/screenshots/travelnest/`, journey carousel live on /case-studies/travelnest + preview on project cards. No duplicates found; captures unaltered (the "Elementor #9" artifact visible in the hero capture is part of the owner's original screenshot). Per D-012, the live site's numerical claims (12+/50K+/4.9★) are NOT referenced in any caption or claim.
**Feature verification:** REQUIRED → **VERIFIED — D-004 RESOLVED (2026-09-21):** owner confirms search + date/guest/budget selection exist as documented. **Technology:** **GENERIC WORDING AUTHORIZED — D-009 RESOLVED (2026-09-21)** ("Custom Web Application").

**LIVE VERIFICATION — 2026-09-21 (read-only fetch, HTTP 200):**

Visible on live homepage (VERIFIED — Project Source):
- Adventure Categories ✓ (matches Master capability list)
- Popular/Featured Destinations ✓
- Featured Tour Packages ✓
- Traveler Stories ✓ ("What Our Travelers Are Saying" section present)
- Travel Gallery ✓
- Hero: "Discover Your Next Adventure" positioning ✓ consistent with Master
- Newsletter signup (not in Master capability list — do not add to website copy without decision)

Still UNVERIFIED via static fetch (likely client-side widgets — confirm during screenshot capture): Travel search · Destination selection · Date selection · Guest selection · Budget selection.

**Technology observation:** page markup contains an Elementor page-builder artifact ("Elementor #9"), indicating the TravelNest homepage is built with WordPress + Elementor. Classification: OBSERVED — Project Source artifact; confirm from project repository before publishing any technology claim (Blocker 04 / D-009). Do not publish without that confirmation.

**CONTENT-SAFETY WARNING → RESOLVED AS NEVER-PUBLISH (D-012, 2026-09-21):** the live site displays numerical claims ("12+ Years Experience", "50K+ Trips Completed", "4.9★ Average Rating", "Join 50,000+ travelers"). Business Owner ruling: these figures are **never published on permetheon.com** — their factual basis remains unverified (Master Sec. 24; Blocker 20 rule, now owner-backed).

---

## GLOBAL PROJECT CONTENT RULES

1. Only display features that exist in the actual system (Master Secs. 06, 08, 24; Blocker 03).
2. Cross-project assumptions prohibited — a TBMS feature does not prove a PCT feature; a TravelNest technology does not prove an EstateHub technology.
3. Technologies are published only when verified (Blocker 04); otherwise the case study says "Custom Web Application" — **D-009 (2026-09-21): the generic wording is AUTHORIZED as final**; stacks revisitable per project if later verified from repositories.
4. No numerical results may be invented for any project (Master Sec. 08 CS-01 RESULT rule; Blocker 07). PCT result copy (verified): "A centralized operational environment for Permetheon's internal project execution."
5. All four projects are FEATURED on homepage and Work page (Master Secs. 06, 07).
