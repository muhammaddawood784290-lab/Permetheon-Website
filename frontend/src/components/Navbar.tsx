import { Link, useLocation } from "react-router-dom";
import { useEffect, useState } from "react";
import { Button } from "./Button";
import { Container } from "./Container";

const NAV_ITEMS = [
  { label: "Work", href: "/work" },
  { label: "Services", href: "/services" },
  { label: "Process", href: "/process" },
  { label: "About", href: "/about" },
] as const;

const MOBILE_ITEMS = [...NAV_ITEMS, { label: "Contact", href: "/contact" }] as const;

export function Navbar() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const pathname = useLocation().pathname;

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    setOpen(false);
  }, [pathname]);

  return (
    <header
      className={`sticky top-0 z-50 border-b transition-colors duration-300 ${
        scrolled ? "border-line bg-paper/90 backdrop-blur" : "border-transparent bg-paper"
      }`}
    >
      <Container size="wide">
        <nav className="flex h-16 items-center justify-between" aria-label="Main navigation">
          <Link
            to="/"
            aria-label="Permetheon — Home"
            className="flex items-center"
          >
            <picture>
              <source
                type="image/svg+xml"
                srcSet="/brand/permetheon-logo.svg"
              />
              <img
                src="/brand/permetheon-logo.png"
                alt="Permetheon"
                width={1823}
                height={467}
                className="h-6 w-auto object-contain sm:h-7"
                decoding="async"
                fetchPriority="high"
              />
            </picture>
          </Link>

          {/* Desktop nav (Master Sec. 03) */}
          <div className="hidden items-center gap-8 md:flex">
            {NAV_ITEMS.map((item) => (
              <Link
                key={item.href}
                to={item.href}
                className={`text-sm font-medium transition-colors ${
                  pathname === item.href ? "text-accent" : "text-fog hover:text-ink"
                }`}
              >
                {item.label}
              </Link>
            ))}
            <Button href="/contact" variant="primary">
              Start a Project
            </Button>
          </div>

          {/* Mobile: logo + menu (Master Sec. 03) */}
          <button
            type="button"
            className="flex h-10 w-10 flex-col items-center justify-center gap-1.5 md:hidden"
            aria-expanded={open}
            aria-controls="mobile-menu"
            aria-label={open ? "Close menu" : "Open menu"}
            onClick={() => setOpen((v) => !v)}
          >
            <span
              className={`h-0.5 w-5 bg-ink transition-transform duration-200 ${open ? "translate-y-1 rotate-45" : ""}`}
            />
            <span
              className={`h-0.5 w-5 bg-ink transition-transform duration-200 ${open ? "-translate-y-1 -rotate-45" : ""}`}
            />
          </button>
        </nav>
      </Container>

      {/* Mobile menu */}
      {open ? (
        <div id="mobile-menu" className="border-t border-line bg-paper md:hidden">
          <Container>
            <ul className="flex flex-col py-4">
              {MOBILE_ITEMS.map((item) => (
                <li key={item.href}>
                  <Link
                    to={item.href}
                    className={`block py-3 text-base font-medium ${
                      pathname === item.href ? "text-accent" : "text-ink"
                    }`}
                  >
                    {item.label}
                  </Link>
                </li>
              ))}
              <li className="pt-2">
                <Button href="/contact" variant="primary" className="w-full">
                  Start a Project
                </Button>
              </li>
            </ul>
          </Container>
        </div>
      ) : null}
    </header>
  );
}
