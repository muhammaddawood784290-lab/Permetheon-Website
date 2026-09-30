import { Link } from "react-router-dom";
import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { PROJECTS, SERVICES } from "@/data/projects";

// Verified copy (docs/09 — Master Sec. 09)
export function ServicesPage() {
  return (
    <>
      {/* HERO — Master Sec. 09 (verified heading + subheading) */}
      <section className="bg-ink py-20 md:py-28">
        <Container size="wide">
          <div className="flex max-w-3xl flex-col items-start gap-5">
            <span className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark">
              Services
            </span>
            <h1 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-paper">
              What We Build
            </h1>
            <p className="text-pretty text-(length:--text-lead) leading-relaxed text-fog-dark">
              Digital products and systems designed around real business needs.
            </p>
          </div>
        </Container>
      </section>

      {/* SIX SERVICES — verified capability lists + proof links (Master Sec. 09) */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <div className="grid gap-4 md:grid-cols-2">
            {SERVICES.map((service) => (
              <Card key={service.id} className="flex h-full flex-col gap-4 p-8">
                <h2 className="font-display text-xl font-semibold text-ink">{service.name}</h2>
                <ul className="flex-1 space-y-2">
                  {service.capabilities.map((capability) => (
                    <li
                      key={capability}
                      className="flex items-start gap-2.5 text-sm leading-relaxed text-fog"
                    >
                      <span
                        className="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-accent"
                        aria-hidden="true"
                      />
                      {capability}
                    </li>
                  ))}
                </ul>
                {service.relatedProjects.length > 0 ? (
                  <div className="flex flex-wrap gap-x-5 gap-y-2 border-t border-line pt-4">
                    {service.relatedProjects.map((id) => {
                      const project = PROJECTS.find((p) => p.id === id);
                      return project ? (
                        <Link
                          key={id}
                          to={project.caseStudyUrl}
                          className="text-xs font-semibold text-accent transition-colors hover:text-accent-strong"
                        >
                          Proof: {project.name} →
                        </Link>
                      ) : null;
                    })}
                  </div>
                ) : null}
              </Card>
            ))}
          </div>
          <div className="mt-14 flex justify-center">
            <Button href="/contact" variant="primary">
              Start a Project
            </Button>
          </div>
        </Container>
      </section>

      {/* CTA — Master Sec. 15 */}
      <CTABlock />
    </>
  );
}
