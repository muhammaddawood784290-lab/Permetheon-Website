import { Link } from "react-router-dom";
import { Container } from "./Container";
// Privacy/Terms footer links REMOVED per D-007 (2026-09-21) — no 404s at launch.
// Re-add when real legal content exists.

const FOOTER_NAV = [
  { label: "Work", href: "/work" },
  { label: "Services", href: "/services" },
  { label: "Process", href: "/process" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
] as const;

const FEATURED_WORK = [
  { label: "PCT", href: "/case-studies/pct" },
  { label: "TBMS", href: "/case-studies/tbms" },
  { label: "EstateHub", href: "/case-studies/estatehub" },
  { label: "TravelNest", href: "/case-studies/travelnest" },
] as const;

export function Footer() {
  return (
    <footer className="border-t border-line bg-paper">
      <Container size="wide">
        <div className="grid gap-10 py-14 md:grid-cols-[2fr_1fr_1fr]">
          <div>
            <p className="font-display text-lg font-bold tracking-tight text-ink">PERMETHEON</p>
            <p className="mt-3 max-w-sm text-sm leading-relaxed text-fog">
              Digital Products. Business Systems. Built to Work.
            </p>
          </div>

          <nav aria-label="Footer navigation">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-fog">Navigation</p>
            <ul className="mt-4 space-y-2.5">
              {FOOTER_NAV.map((item) => (
                <li key={item.href}>
                  <Link to={item.href} className="text-sm text-fog transition-colors hover:text-ink">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          <nav aria-label="Featured work">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-fog">Featured Work</p>
            <ul className="mt-4 space-y-2.5">
              {FEATURED_WORK.map((item) => (
                <li key={item.href}>
                  <Link to={item.href} className="text-sm text-fog transition-colors hover:text-ink">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        </div>

        <div className="border-t border-line py-6">
          <p className="text-xs text-fog">
            © Permetheon. All rights reserved.
          </p>
        </div>
      </Container>
    </footer>
  );
}
