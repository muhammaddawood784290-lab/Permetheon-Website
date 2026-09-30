# 04 — DESIGN SYSTEM SPECIFICATION

Source: Master Project Document — Sections 02, 18, 20, 27-P01, Phase 01.
Status: EXTRACTED FROM MASTER. Values not defined by the Master are marked TBD — they must not be invented.

---

## 1. VISUAL DIRECTION (Master Sec. 02)

Premium technology/product studio aesthetic.

Design characteristics:
Strong typography · Large editorial headlines · Generous whitespace · Premium project imagery · Real product screenshots · Browser mockups · Subtle grid systems · Thin borders · Sophisticated cards · Restrained animations · Clean spacing · Strong visual hierarchy.

Primary visual direction (section rhythm):

```text
Dark premium hero
+
Light project sections
+
Dark services
+
Light case studies
+
Dark CTA
```

Foundation: the existing Permetheon visual identity. Do not completely redesign the brand identity unless necessary.

**Explicitly avoid (Master Sec. 02):**
Generic SaaS gradients · Excessive glassmorphism · Stock photos · Fake statistics · Fake testimonials · Fake awards · Fake clients · Fake revenue numbers · Fake performance metrics · Generic AI-generated illustrations.

---

## 2. BRAND SYSTEM DELIVERABLES (Master Phase 01 — required deliverables)

| Deliverable | Status |
|---|---|
| Permetheon logo implementation | BLOCKED — INPUT REQUIRED (asset, Business Owner; Blocker 01) |
| Primary color system | TBD — DECISION REQUIRED (from existing brand identity) |
| Secondary/accent colors | TBD — DECISION REQUIRED |
| Typography system | TBD — DECISION REQUIRED |
| Heading hierarchy | TBD — DECISION REQUIRED |
| Body typography | TBD — DECISION REQUIRED |
| Button styles | TBD — DECISION REQUIRED (design decision) |
| Link styles | TBD — DECISION REQUIRED |
| Border styles | TBD — DECISION REQUIRED |
| Card styles | TBD — DECISION REQUIRED |
| Icon style | TBD — DECISION REQUIRED |
| Spacing scale | TBD — DECISION REQUIRED |
| Container widths | TBD — DECISION REQUIRED |
| Grid system | TBD — DECISION REQUIRED |
| Border radius system | TBD — DECISION REQUIRED |

Blocker 01 resolution path: if exact brand assets are unavailable, the Design Owner creates a temporary design system based on the existing Permetheon website identity, marked **TEMPORARY — REQUIRES BRAND APPROVAL**. Phase 01 cannot pass its gate without approval of these items (Gate 01 criteria).

### 2.1 IMPLEMENTATION STATUS (updated 2026-09-21 — D-001 APPROVED)

**The REV 2 direction was APPROVED by the Business Owner (D-001, 2026-09-21) as the working brand foundation: ember #C2410C accent, Space Grotesk display + Inter body, fluid editorial type scale, 80–120px section rhythm. B-001 RESOLVED; Gate 01 PASSED.** Official brand assets may replace individual tokens later without component changes.

The design system is **implemented in code** (originally under the Blocker 01 authorization):
- Tokens: `src/app/globals.css` (Tailwind v4 `@theme`) — color system, typography, spacing scale, containers, radii, motion timing, reduced-motion support, focus states. Header comment records the D-001 approval.
- Reviewable at: `/system` route (design-system showcase: colors, type, buttons, cards/badges, mockup/frames, spacing, radii, breakpoints).
- Accent: `#C2410C` (ember) — replaced initial default-indigo placeholder `#4F46E5` to avoid the generic-SaaS look the Master bans (Sec. 02); approved by D-001.
- Typography: Space Grotesk display face (next/font) + Inter body; fluid editorial scale — hero clamp 44→72px, section 30→44px, lead 17→20px (Master Sec. 02 "large editorial headlines"); approved by D-001.
- Spacing: section rhythm raised to py-20/24–30 (80–120px) for "generous whitespace" (Master Sec. 02); approved by D-001.
- Official brand assets may still replace tokens later (B-001 note) — token-level swap, no component changes.

---

## 3. RESPONSIVE FOUNDATION (Master Phase 01 + Sec. 20)

Define breakpoints for Desktop / Tablet / Mobile (exact values TBD — DECISION REQUIRED), responsive typography, responsive spacing, responsive containers.

Mobile must be deliberately designed, not a shrunken desktop (Master Sec. 20). Mobile priorities: Typography · Project screenshots · CTA visibility · Navigation · Case-study readability · Touch targets.

---

## 4. DARK/LIGHT SECTION STRATEGY

Follow Master Sec. 02 rhythm (§1 above). Dark sections: hero, services, CTA. Light sections: projects, case studies. This strategy is fixed by the Master; deviations require a decision.

---

## 5. MOTION PRINCIPLES (Master Sec. 18 — detailed spec in 14_QUALITY_STANDARDS.md)

Premium but restrained: hero text reveal + subtle product movement; project image parallax + hover scale; case-study scroll reveals, sticky project information, image transitions; interactive process step navigation; button micro-interactions. **Do not over-animate.** The website should feel fast and intentional.

---

## 6. VISUAL HIERARCHY GOAL (Master Sec. 25)

The finished website must communicate: "We understand businesses." · "We understand software." · "We can design it." · "We can build it." · "We can take it live." — with the four primary products as the proof. Clarity outranks visual effects (Master Sec. 26).
