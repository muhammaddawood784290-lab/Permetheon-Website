import { Link } from "react-router-dom";
import { Badge } from "@/components/Badge";
import { BrowserMockup } from "@/components/BrowserMockup";
import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { ImageFrame } from "@/components/ImageFrame";
import { SectionHeading } from "@/components/SectionHeading";
import { FeaturedProject } from "@/components/project/FeaturedProject";
import {
  CAPABILITY_TAGS,
  DIFFERENCE_SEQUENCE,
  PROCESS_STAGES,
  PROJECTS,
  SERVICES,
  WHY_PERMETHEON,
} from "@/data/projects";

export function HomePage() {
  return (
    <>
      {/* 1+2. NAVIGATION (layout.tsx) + HERO — Master Sec. 04 */}
      <section className="relative overflow-hidden bg-ink py-24 md:py-36">
        <div className="grid-bg absolute inset-0 opacity-40" aria-hidden="true" />
        <Container size="wide" className="relative">
          <div className="flex max-w-3xl flex-col items-start gap-7">
            <h1 className="text-balance font-display font-bold leading-[1.02] tracking-tight text-(length:--text-hero) text-paper">
              Digital Products.
              <br />
              Built for Real Business.
            </h1>
            <p className="max-w-2xl text-pretty text-(length:--text-lead) leading-relaxed text-fog-dark">
              We design and develop websites, web applications and custom business systems that turn
              ideas and business processes into working digital products.
            </p>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button href="/contact" variant="primary" dark>
                Start a Project
              </Button>
              <Button href="/work" variant="secondary" dark>
                Explore Our Work
              </Button>
            </div>
          </div>

          {/* Hero visual: layered product windows (Master Sec. 04) — real project screenshots (B-002 RESOLVED) */}
          <div className="mt-16 grid gap-6 md:mt-20 lg:grid-cols-[1.2fr_1fr]">
            <BrowserMockup url="pct.permetheon.com" dark>
              <ImageFrame
                src={PROJECTS[0].previewImage?.src}
                alt={PROJECTS[0].previewImage?.alt}
                label={PROJECTS[0].featuredImageLabel}
                aspect="wide"
                dark
              />
            </BrowserMockup>
            <div className="flex flex-col gap-6">
              <BrowserMockup url="tbms.permetheon.com" dark>
                <ImageFrame
                  src={PROJECTS[1].previewImage?.src}
                  alt={PROJECTS[1].previewImage?.alt}
                  label={PROJECTS[1].featuredImageLabel}
                  aspect="video"
                  dark
                />
              </BrowserMockup>
              <p className="text-xs leading-relaxed text-fog-dark">
                Product visuals above are real screenshots from the built products (B-002). We
                don&apos;t just design websites. We build software.
              </p>
            </div>
          </div>
        </Container>
      </section>

      {/* 3. TRUST / CAPABILITY STRIP — Master Sec. 05 */}
      <section className="border-b border-line bg-paper py-12">
        <Container size="wide">
          <p className="text-center font-display text-sm font-semibold uppercase tracking-[0.25em] text-fog">
            From Idea → Design → Development → Deployment
          </p>
          <ul className="mt-6 flex flex-wrap items-center justify-center gap-2.5">
            {CAPABILITY_TAGS.map((tag) => (
              <li key={tag}>
                <Badge>{tag}</Badge>
              </li>
            ))}
          </ul>
        </Container>
      </section>

      {/* 4. FEATURED WORK — Master Sec. 06 */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="Featured Work"
            title="Built by Permetheon"
            description="A selection of digital products and business systems we've designed and developed."
          />
          <div className="mt-12 grid gap-8 md:grid-cols-2">
            {PROJECTS.map((project, index) => (
              <FeaturedProject key={project.id} project={project} index={index} />
            ))}
          </div>
          <div className="mt-14 flex justify-center">
            <Button href="/work" variant="secondary">
              View All Work
            </Button>
          </div>
        </Container>
      </section>

      {/* 5. SERVICES — Master Sec. 09 (homepage preview: all six) */}
      <section className="bg-ink py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="Services"
            title="What We Build"
            description="Digital products and systems designed around real business needs."
            dark
          />
          <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {SERVICES.map((service) => (
              <Card key={service.id} dark className="flex h-full flex-col gap-4">
                <h3 className="font-display text-lg font-semibold text-paper">{service.name}</h3>
                <ul className="flex-1 space-y-1.5">
                  {service.capabilities.map((capability) => (
                    <li key={capability} className="text-sm leading-relaxed text-fog-dark">
                      {capability}
                    </li>
                  ))}
                </ul>
                {service.relatedProjects.length > 0 ? (
                  <div className="flex flex-wrap gap-2 border-t border-line-dark pt-4">
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
        </Container>
      </section>

      {/* 6. THE DIFFERENCE — Master Sec. 10 */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <div className="grid items-start gap-12 lg:grid-cols-[1fr_1.2fr]">
            <SectionHeading
              eyebrow="The Difference"
              title="We don't start with a template."
              description="We start by understanding the business."
            />
            <ol className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {DIFFERENCE_SEQUENCE.map((step, index) => (
                <li key={step} className="flex flex-col gap-2">
                  <span className="font-mono text-xs text-accent">{String(index + 1).padStart(2, "0")}</span>
                  <span className="rounded-card border border-line bg-paper-soft px-3 py-2.5 text-sm font-medium text-ink">
                    {step}
                  </span>
                </li>
              ))}
            </ol>
          </div>
        </Container>
      </section>

      {/* 7. PROCESS PREVIEW — Master Sec. 11 */}
      <section className="bg-paper-soft py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="Process"
            title="How We Build"
            description="Six deliberate stages — you can see what happens at every step."
          />
          <ol className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {PROCESS_STAGES.map((stage) => (
              <li key={stage.number}>
                <Card className="flex h-full flex-col gap-2">
                  <span className="font-display text-sm font-bold text-accent">{stage.number}</span>
                  <span className="font-display text-lg font-semibold text-ink">{stage.name}</span>
                  <span className="text-sm leading-relaxed text-fog">{stage.summary}</span>
                </Card>
              </li>
            ))}
          </ol>
          <div className="mt-10">
            <Button href="/process" variant="ghost">
              See the full process →
            </Button>
          </div>
        </Container>
      </section>

      {/* 8. WHY PERMETHEON — Master Sec. 12 */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <SectionHeading eyebrow="Why Permetheon" title="Built around your business." />
          <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {WHY_PERMETHEON.map((item) => (
              <Card key={item.title} className="flex h-full flex-col gap-2">
                <h3 className="font-display text-lg font-semibold text-ink">{item.title}</h3>
                <p className="text-sm leading-relaxed text-fog">{item.body}</p>
              </Card>
            ))}
          </div>
        </Container>
      </section>

      {/* 9. CTA — Master Sec. 15 (component) */}
      <CTABlock />

      {/* 10. FOOTER — Master Sec. 17 (layout.tsx) */}
    </>
  );
}
