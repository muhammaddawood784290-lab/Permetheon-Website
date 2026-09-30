# 01 — PRODUCT REQUIREMENTS DOCUMENT

Source: Master Project Document — Sections 01, 02, 24, 25, 26, 27 (priorities).
Status: EXTRACTED FROM MASTER. Nothing here introduces requirements not present in the Master.

---

## 1. PROJECT

Design and build a premium, modern website for **PERMETHEON**.

**Positioning (VERIFIED — Master Sec. 01):**
A **Digital Solutions & Software Development company** that designs and builds:
Websites · Web Applications · Business Platforms · Management Systems · Booking & Reservation Systems · Internal Business Software · Portals · Custom Digital Products.

The website must be built around **real products and real work**, not generic agency claims.

**Primary headline:** **Digital Products. Built for Real Business.**

**Supporting message:** Permetheon designs and develops websites, web applications and custom digital systems that help businesses operate, connect with customers and grow.

**Alternative supporting line:** From idea and design to development and deployment, we build digital experiences around the way your business actually works.

**Brand personality:** Modern · Technical · Professional · Confident · Practical · Premium · Human.

**Perceived identity:** Digital Product Studio + Software Development Company + Business Solutions Partner — NOT a cheap web-design agency.

---

## 2. OBJECTIVES

1. Communicate what Permetheon does within 10 seconds of landing (Master Sec. 26).
2. Show real work (the four flagship products) within 20 seconds.
3. Explain the difference between website / web application / business system.
4. Provide detailed case studies for the four flagship products.
5. Explain how Permetheon works (process).
6. Convert: make starting a project easy.
7. Communicate: *"A website today. A booking platform tomorrow. A complete business operating system next."*

Primary conversion path (Master Sec. 27-P14):
**Discover Permetheon → See Real Work → Understand Capability → Read Case Study → Start a Project**

---

## 3. SCOPE

**In scope (Master-defined):**
Homepage, Work, Case Studies (×4), Services, Process, About, Contact, Footer, legal pages (Privacy, Terms — footer links defined; page content **TBD — DECISION REQUIRED**, see Missing Inputs).

**Explicitly out of scope:** any page, feature or content not defined in the Master Document. No blog, no pricing page, no team page, no testimonials section — none are defined by the Master and none may be added without a recorded decision.

**Phased minimum viable launch (Master Sec. 27 IMPLEMENTATION RULE):**
- **PHASE 1 — CORE:** Homepage + Navigation + Featured Work + 4 Case Studies + Contact CTA
- **PHASE 2 — COMPLETE WEBSITE:** Work, Services, Process, About, Contact, Responsive optimization
- **PHASE 3 — POLISH:** Animations, micro-interactions, advanced galleries, SEO, Performance, Accessibility, Conversion optimization

Rule: **content-complete before animation-complete.**
Order never reversed: Strategy → Content → Real Work → Case Studies → UX → Design Polish → Motion → Optimization.

---

## 4. FUNCTIONAL REQUIREMENTS

| ID | Requirement | Master Source | Phase |
|----|-------------|---------------|-------|
| FR-01 | Sticky desktop navigation (logo, Work, Services, Process, About, Start a Project CTA) | Sec. 03 | 01–02 |
| FR-02 | Mobile menu (logo, menu; Work/Services/Process/About/Contact) | Sec. 03 | 02 |
| FR-03 | Homepage hero with primary CTA "Start a Project" + secondary CTA "Explore Our Work" + layered product composition (PCT, TBMS, EstateHub, TravelNest) | Sec. 04 | 02 |
| FR-04 | Trust/capability strip: "From Idea → Design → Development → Deployment" + 7 capability tags | Sec. 05 | 02 |
| FR-05 | Featured Work: 4 projects (PCT, TBMS, EstateHub, TravelNest) | Sec. 06 | 02 |
| FR-06 | Work page with category filters (All, Websites, Web Applications, Business Systems, Booking Systems, Portals, Internal Platforms) and expandable project architecture | Sec. 07 | 04 |
| FR-07 | Case-study index `/case-studies` ("Behind the Build.") + 4 case studies, editorial product-story style | Sec. 08 | 05–08 |
| FR-08 | Services: 6 services, each linking to real proof projects | Sec. 09, 27-P05 | 09 |
| FR-09 | "The Difference" statement + visual sequence (Business → … → Deployment) | Sec. 10 | 02 |
| FR-10 | Process: 6 stages with interactive timeline (desktop) / vertical (mobile) | Sec. 11 | 10 |
| FR-11 | Why Permetheon: 6 value cards | Sec. 12 | 02 |
| FR-12 | About page with 4 core principles | Sec. 13 | 11 |
| FR-13 | Technology section grouped by actual usage (Frontend/Backend/Database/Infrastructure/Design), no logo wall | Sec. 14 | 09* |
| FR-14 | Dark full-width CTA section with animated subtle grid | Sec. 15 | 02 |
| FR-15 | Contact form: Name, Company, Email, project-type select (8 options), Budget, Project Details, CTA "Start the Conversation"; states: default/focus/validation/submission/success/failure | Sec. 16, Phase 12 | 12 |
| FR-16 | Footer: brand line, navigation, featured work, legal links, © Permetheon | Sec. 17 | 02 |
| FR-17 | Premium restrained motion (hero reveal, project parallax/hover, scroll reveals, process interaction, button micro-interactions) | Sec. 18, Phase 14 | 14 |
| FR-18 | Real project screenshots in premium browser/device frames — never fake UI | Sec. 19 | 03, 05–08 |
| FR-19 | Unique SEO metadata per page and per case study; sitemap.xml; robots.txt; Open Graph; structured data where appropriate | Sec. 22, Phase 15 | 15 |
| FR-20 | Responsive design deliberately designed for desktop/tablet/mobile | Sec. 20, Phase 13 | 13 |

\* FR-13 appears on the homepage per Master Sec. 27-P02 required-sections list ("Services") and as part of Services content; technology names themselves are **UNKNOWN — VERIFICATION REQUIRED** (see 07_PROJECT_CONTENT.md).

---

## 5. CONTENT SAFETY / ACCURACY RULES (Master Sec. 24 — absolute)

NEVER fabricate:
Clients · Revenue · Number of users · Conversion rates · Business growth · Awards · Testimonials · Team size · Years of experience · Certifications · Unverified technology stacks · Non-existent features.

If information is unknown: design the component but leave the data field configurable.
The website communicates confidence through the quality of actual work, not fabricated claims.

This rule is enforced at Gate 05–08 (accuracy gate), Phase 17 (content QA) and the Launch Gate.

---

## 6. FINAL DESIGN TEST (Master Sec. 26 — acceptance test for the product)

- [ ] Can a visitor understand what Permetheon does within 10 seconds?
- [ ] Can they see real work within 20 seconds?
- [ ] Can they understand the difference between a website, web application and business system?
- [ ] Can they open detailed case studies?
- [ ] Can they understand how Permetheon works?
- [ ] Can they start a project easily?

If yes, the design is successful. Do not sacrifice clarity for visual effects.
Goal: **Premium design + real proof + clear positioning + conversion.**

---

## 7. UNDEFINED IN MASTER (flagged, not invented)

| Item | Status | Owner |
|------|--------|-------|
| Privacy / Terms page content | TBD — DECISION REQUIRED | Business Owner |
| Analytics provider / requirement | TBD — DECISION REQUIRED ("configured if required", Launch Gate) | Business Owner + Development |
| Error monitoring | TBD — DECISION REQUIRED ("if required", Launch Gate) | Development |
| Website technology stack / framework | TBD — DECISION REQUIRED | Development Owner |
| Contact details (email/phone/social) | BLOCKED — INPUT REQUIRED | Business Owner |
