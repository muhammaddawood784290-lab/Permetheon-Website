import { Badge } from "@/components/Badge";
import { BrowserMockup } from "@/components/BrowserMockup";
import { Button } from "@/components/Button";
import { Container } from "@/components/Container";
import { ImageFrame } from "@/components/ImageFrame";
import { SectionHeading } from "@/components/SectionHeading";
import { CASE_STUDIES } from "@/data/case-studies";

// Verified copy (docs/09 — Master Sec. 08)
export function CaseStudiesPage() {
  return (
    <>
      <section className="bg-ink py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="Case Studies"
            title="Behind the Build."
            description="Explore how Permetheon turns business requirements into working digital products."
            dark
          />
        </Container>
      </section>

      <section className="py-20 md:py-24">
        <Container size="wide">
          <div className="grid gap-10 md:grid-cols-2">
            {CASE_STUDIES.map((study) => (
              <article key={study.slug} className="flex flex-col gap-5">
                <BrowserMockup url={study.projectUrl.replace(/^https?:\/\//, "").replace(/\/$/, "")}>
                  {study.showcase ? (
                    <img
                      src={study.showcase.images[0].src}
                      alt={study.showcase.images[0].alt}
                      loading="lazy"
                      className="aspect-video w-full object-cover object-top"
                    />
                  ) : (
                    <ImageFrame label={`${study.name} — hero screenshot pending (B-002)`} aspect="video" />
                  )}
                </BrowserMockup>
                <div className="flex flex-col items-start gap-3">
                  <Badge>{study.fullPositioning}</Badge>
                  <h2 className="font-display text-2xl font-bold tracking-tight text-ink">
                    {study.name}
                  </h2>
                  <p className="text-pretty leading-relaxed text-fog">{study.headline}</p>
                  <div className="mt-1">
                    <Button href={`/case-studies/${study.slug}`} variant="primary">
                      Read Case Study →
                    </Button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </Container>
      </section>
    </>
  );
}
