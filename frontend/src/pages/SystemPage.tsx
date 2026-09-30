import { useEffect } from "react";
import { Badge } from "@/components/Badge";
import { BrowserMockup } from "@/components/BrowserMockup";
import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { Container } from "@/components/Container";
import { ImageFrame } from "@/components/ImageFrame";
import { SectionHeading } from "@/components/SectionHeading";

const COLORS = [
  { token: "--color-ink", value: "#0B0E14", use: "Dark surfaces (hero, services, CTA)" },
  { token: "--color-ink-soft", value: "#12161F", use: "Raised dark cards" },
  { token: "--color-paper", value: "#FFFFFF", use: "Light surfaces" },
  { token: "--color-paper-soft", value: "#F7F8FA", use: "Raised light cards" },
  { token: "--color-line / line-dark", value: "#E5E7EB / #1F2530", use: "Thin borders (Master Sec. 02)" },
  { token: "--color-fog / fog-dark", value: "#6B7280 / #9AA3B2", use: "Secondary text" },
  { token: "--color-accent", value: "#C2410C", use: "Ember accent — PLACEHOLDER, replace with brand" },
] as const;

const TYPE_STEPS = [
  { name: "Hero / H1", value: "44 → 72px (fluid)", className: "text-(length:--text-hero)" },
  { name: "Section / H2", value: "30 → 44px (fluid)", className: "text-(length:--text-section)" },
  { name: "Lead", value: "17 → 20px (fluid)", className: "text-(length:--text-lead)" },
] as const;

const SPACING = [
  { scale: "1", value: "4px" },
  { scale: "2", value: "8px" },
  { scale: "4", value: "16px" },
  { scale: "6", value: "24px" },
  { scale: "8", value: "32px" },
  { scale: "12", value: "48px" },
  { scale: "16", value: "64px" },
  { scale: "18", value: "72px" },
  { scale: "22", value: "88px" },
  { scale: "30", value: "120px" },
] as const;

const RADII = [
  { token: "rounded-card", value: "12px", use: "Cards, mockups, frames" },
  { token: "rounded-pill", value: "999px", use: "Buttons, badges" },
] as const;

const BREAKPOINTS = [
  { name: "Mobile", range: "default (single column)", note: "Deliberately designed, not shrunk (Master Sec. 20)" },
  { name: "sm", range: "≥ 640px", note: "Large phones" },
  { name: "md", range: "≥ 768px", note: "Tablet — desktop navigation appears" },
  { name: "lg / xl", range: "≥ 1024 / 1280px", note: "Desktop compositions" },
] as const;

export function SystemPage() {
  // Noindex client-only route — no prerender, sets its own title (§1B SEO row).
  useEffect(() => {
    document.title = "Design System Review | Permetheon";
    const meta = document.createElement("meta");
    meta.name = "robots";
    meta.content = "noindex, nofollow";
    document.head.appendChild(meta);
    return () => {
      meta.remove();
    };
  }, []);

  return (
    <>
      <section className="bg-ink py-24 md:py-30">
        <Container size="wide">
          <div className="flex flex-col items-start gap-5">
            <Badge dark>APPROVED — WORKING BRAND FOUNDATION · REV 2 (D-001)</Badge>
            <h1 className="font-display font-bold leading-[1.02] tracking-tight text-(length:--text-hero) text-paper">
              Design System Review
            </h1>
            <p className="max-w-2xl text-pretty leading-relaxed text-fog-dark">
              Phase 01 foundation approved by the Business Owner (D-001, 2026-09-21): ember accent,
              Space Grotesk display + Inter body, fluid editorial scale, generous spacing. Official
              brand assets may replace individual tokens later without component changes.
            </p>
          </div>
        </Container>
      </section>

      {/* Colors */}
      <section className="py-20 md:py-24">
        <Container size="wide">
          <SectionHeading eyebrow="01 · Color" title="Color system" description="Dark premium sections alternate with light project sections (Master Sec. 02 rhythm)." />
          <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {COLORS.map((c) => (
              <Card key={c.token} className="flex items-center gap-4">
                <span className="h-10 w-10 shrink-0 rounded-card border border-line" style={{ background: c.value.split(" / ")[0] }} />
                <div className="min-w-0">
                  <p className="truncate font-mono text-xs text-ink">{c.token}</p>
                  <p className="truncate text-xs text-fog">{c.value} — {c.use}</p>
                </div>
              </Card>
            ))}
          </div>
        </Container>
      </section>

      {/* Typography */}
      <section className="bg-paper-soft py-20 md:py-24">
        <Container size="wide">
          <SectionHeading eyebrow="02 · Type" title="Typography system" description="Temporary: Space Grotesk display + Inter body. Fluid editorial scale — large editorial headlines per Master Sec. 02." />
          <div className="mt-10 flex flex-col gap-8">
            {TYPE_STEPS.map((step) => (
              <div key={step.name}>
                <p className="text-xs uppercase tracking-[0.2em] text-fog">{step.name} — {step.value}</p>
                <p className={`mt-2 font-display font-bold leading-[1.05] tracking-tight text-ink ${step.className}`}>
                  Digital Products. Built for Real Business.
                </p>
              </div>
            ))}
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-fog">Body — 16px · Lead — fluid 17→20px</p>
              <p className="mt-2 max-w-prose leading-relaxed text-fog">
                We design and develop websites, web applications and custom business systems that turn
                ideas and business processes into working digital products.
              </p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-fog">Eyebrow / label</p>
              <p className="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-accent">Featured Work</p>
            </div>
          </div>
        </Container>
      </section>

      {/* Buttons */}
      <section className="py-20 md:py-24">
        <Container size="wide">
          <SectionHeading eyebrow="03 · Buttons" title="Button styles" description="Primary, secondary and ghost variants on light and dark surfaces, with hover, focus and disabled states." />
          <div className="mt-8 grid gap-6 md:grid-cols-2">
            <Card className="flex flex-col gap-4">
              <p className="text-xs uppercase tracking-[0.2em] text-fog">On light</p>
              <div className="flex flex-wrap items-center gap-3">
                <Button variant="primary">Start a Project</Button>
                <Button variant="secondary">Explore Our Work</Button>
                <Button variant="ghost">Learn more</Button>
              </div>
              <div className="flex flex-wrap items-center gap-3">
                <Button variant="primary" disabled>Disabled</Button>
              </div>
            </Card>
            <Card dark className="flex flex-col gap-4">
              <p className="text-xs uppercase tracking-[0.2em] text-fog-dark">On dark</p>
              <div className="flex flex-wrap items-center gap-3">
                <Button variant="primary" dark>Start a Project</Button>
                <Button variant="secondary" dark>Explore Our Work</Button>
                <Button variant="ghost" dark>Learn more</Button>
              </div>
            </Card>
          </div>
        </Container>
      </section>

      {/* Cards & badges */}
      <section className="bg-paper-soft py-16">
        <Container size="wide">
          <SectionHeading eyebrow="04 · Cards & Badges" title="Cards and metadata" />
          <div className="mt-8 grid gap-4 md:grid-cols-3">
            <Card className="flex flex-col gap-3">
              <Badge>Internal Platform</Badge>
              <p className="font-display text-lg font-semibold text-ink">Card title</p>
              <p className="text-sm leading-relaxed text-fog">Sophisticated cards with thin borders and generous whitespace.</p>
            </Card>
            <Card dark className="flex flex-col gap-3">
              <Badge dark>Booking System</Badge>
              <p className="font-display text-lg font-semibold text-paper">Dark card</p>
              <p className="text-sm leading-relaxed text-fog-dark">Same structure on dark surfaces for dark sections.</p>
            </Card>
            <Card className="flex flex-col gap-3">
              <div className="flex flex-wrap gap-2">
                <Badge>Restaurant</Badge>
                <Badge>Reservation System</Badge>
                <Badge>Admin Platform</Badge>
              </div>
              <p className="font-display text-lg font-semibold text-ink">Metadata tags</p>
              <p className="text-sm leading-relaxed text-fog">Verified project metadata renders as badges.</p>
            </Card>
          </div>
        </Container>
      </section>

      {/* Browser mockup + image frame */}
      <section className="py-20 md:py-24">
        <Container size="wide">
          <SectionHeading eyebrow="05 · Product Imagery" title="Browser mockup & image frame" description="Real screenshots are the trust layer (Master Sec. 19). All four project showcases now carry verified owner-provided captures (B-002 RESOLVED 2026-09-22); frames render them uncropped and unstretched." />
          <div className="mt-8 grid gap-6 lg:grid-cols-2">
            <div className="flex flex-col gap-3">
              <BrowserMockup url="pct.permetheon.com">
                <ImageFrame
                  src="/screenshots/pct/dashboard.png"
                  alt="PCT — dashboard with workspace overview"
                  aspect="video"
                  dark
                />
              </BrowserMockup>
              <p className="text-xs text-fog">BrowserMockup + real screenshot</p>
            </div>
            <div className="flex flex-col gap-3">
              <ImageFrame
                src="/screenshots/estatehub/properties-search.png"
                alt="EstateHub — properties grid with search and filters"
                aspect="wide"
              />
              <p className="text-xs text-fog">ImageFrame standalone</p>
            </div>
          </div>
        </Container>
      </section>

      {/* Spacing, radii, breakpoints */}
      <section className="bg-paper-soft py-16">
        <Container size="wide">
          <SectionHeading eyebrow="06 · Foundations" title="Spacing, radii, breakpoints" />
          <div className="mt-8 grid gap-6 md:grid-cols-3">
            <Card>
              <p className="text-xs uppercase tracking-[0.2em] text-fog">Spacing scale (4px base)</p>
              <ul className="mt-4 space-y-2">
                {SPACING.map((s) => (
                  <li key={s.scale} className="flex items-center gap-3 text-xs text-fog">
                    <span className="w-10 font-mono text-ink">{s.scale}</span>
                    <span className="h-2 rounded-pill bg-accent/30" style={{ width: `${Number(s.scale) * 4}px` }} />
                    <span>{s.value}</span>
                  </li>
                ))}
              </ul>
            </Card>
            <Card>
              <p className="text-xs uppercase tracking-[0.2em] text-fog">Radii</p>
              <ul className="mt-4 space-y-3">
                {RADII.map((r) => (
                  <li key={r.token} className="flex items-center justify-between gap-3 text-xs">
                    <span className="font-mono text-ink">{r.token}</span>
                    <span className="text-fog">{r.value} — {r.use}</span>
                  </li>
                ))}
              </ul>
            </Card>
            <Card>
              <p className="text-xs uppercase tracking-[0.2em] text-fog">Breakpoints</p>
              <ul className="mt-4 space-y-3">
                {BREAKPOINTS.map((b) => (
                  <li key={b.name} className="text-xs">
                    <span className="font-mono text-ink">{b.name}</span>
                    <span className="text-fog"> · {b.range} · {b.note}</span>
                  </li>
                ))}
              </ul>
            </Card>
          </div>
        </Container>
      </section>

      <section className="py-20 md:py-24">
        <Container>
          <Card className="flex flex-col items-start gap-4">
            <p className="font-display text-lg font-semibold text-ink">Approval path</p>
            <p className="text-sm leading-relaxed text-fog">
              Gate 01 requires: typography, colors, spacing, grid, buttons, cards, navigation design and
              breakpoints finalized, with the primary message locked — see
              <span className="font-mono text-ink"> docs/11_PHASE_GATES_DEPENDENCIES.md</span>. Brand
              approval via item 1 of the owner request (docs/19) converts every token on this page from
              TEMPORARY to approved.
            </p>
          </Card>
        </Container>
      </section>
    </>
  );
}
