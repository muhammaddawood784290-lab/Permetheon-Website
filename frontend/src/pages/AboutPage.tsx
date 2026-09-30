import { Card } from "@/components/Card";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { SectionHeading } from "@/components/SectionHeading";
import { CORE_PRINCIPLES, SERVICES } from "@/data/projects";

// Verified copy (docs/09 — Master Sec. 13). No invented history, statistics or achievements.
export function AboutPage() {
  return (
    <>
      {/* HERO — Master Sec. 13 (verified heading + copy) */}
      <section className="bg-ink py-20 md:py-28">
        <Container size="wide">
          <div className="flex max-w-3xl flex-col items-start gap-5">
            <span className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark">
              About
            </span>
            <h1 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-paper">
              We build digital products that have a job to do.
            </h1>
            <p className="text-pretty text-(length:--text-lead) leading-relaxed text-fog-dark">
              Permetheon is a digital solutions and software development company focused on creating
              practical digital products, websites, web applications and business systems. We
              combine product thinking, design and engineering to turn ideas and business processes
              into usable software.
            </p>
          </div>
        </Container>
      </section>

      {/* WHAT WE BUILD — the six verified services, restated for context */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="What we build"
            title="From a single website to a complete business platform."
            description="Every product is built around a real business need — no unnecessary features, no generic templates."
          />
          <ul className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {SERVICES.map((service) => (
              <li
                key={service.id}
                className="rounded-card border border-line bg-paper-soft px-4 py-3 text-sm font-medium text-ink"
              >
                {service.name}
              </li>
            ))}
          </ul>
        </Container>
      </section>

      {/* CORE PRINCIPLES — Master Sec. 13 (verified titles; descriptions not defined → not invented) */}
      <section className="bg-paper-soft py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="How we think"
            title="Four principles, no exceptions."
            description="These principles shape every product decision — from the first conversation to beyond launch."
          />
          <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {CORE_PRINCIPLES.map((principle, index) => (
              <Card key={principle} className="flex h-full flex-col gap-2">
                <span className="font-mono text-xs text-accent">
                  {String(index + 1).padStart(2, "0")}
                </span>
                <h3 className="font-display text-lg font-semibold text-ink">{principle}</h3>
              </Card>
            ))}
          </div>
        </Container>
      </section>

      {/* CTA — Master Sec. 15 (verified) */}
      <CTABlock />
    </>
  );
}
