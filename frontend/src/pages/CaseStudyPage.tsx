import { Link, useParams } from "react-router-dom";
import { Badge } from "@/components/Badge";
import { BrowserMockup } from "@/components/BrowserMockup";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { ImageFrame } from "@/components/ImageFrame";
import { ProductFlow } from "@/components/case-study/ProductFlow";
import { ScreenshotCarousel } from "@/components/showcase/ScreenshotCarousel";
import { CASE_STUDIES } from "@/data/case-studies";

export function CaseStudyPage() {
  const { slug } = useParams();
  const study = CASE_STUDIES.find((c) => c.slug === slug);
  if (!study) {
    return <NotFoundish />;
  }

  return (
    <>
      {/* HERO — name, positioning, verified headline, snapshot metadata */}
      <section className="bg-ink py-24 md:py-30">
        <Container size="wide">
          <div className="flex max-w-3xl flex-col items-start gap-6">
            <Link
              to="/case-studies"
              className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark transition-colors hover:text-paper"
            >
              ← Behind the Build
            </Link>
            <h1 className="text-balance font-display font-bold leading-[1.02] tracking-tight text-(length:--text-hero) text-paper">
              {study.name}
            </h1>
            <p className="font-display text-lg font-semibold text-fog-dark">{study.fullPositioning}</p>
            <p className="text-pretty text-(length:--text-lead) leading-relaxed text-paper">
              {study.headline}
            </p>
            <p className="max-w-2xl text-pretty leading-relaxed text-fog-dark">{study.description}</p>
            <div className="flex flex-wrap gap-2">
              {study.metadata.map((tag) => (
                <Badge key={tag} dark>
                  {tag}
                </Badge>
              ))}
            </div>
          </div>
          {study.showcase ? (
            <div className="mt-14">
              <ScreenshotCarousel images={study.showcase.images} label={`${study.name} product screenshots`} />
            </div>
          ) : (
            <div className="mt-14">
              <BrowserMockup url={study.projectUrl.replace(/^https?:\/\//, "").replace(/\/$/, "")} dark>
                <ImageFrame label={`${study.name} — hero screenshot pending (B-002)`} aspect="wide" dark />
              </BrowserMockup>
            </div>
          )}
        </Container>
      </section>

      {/* PRODUCT STRUCTURE — Master-verified flow */}
      <section className="border-b border-line bg-paper py-12">
        <Container size="wide">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-fog">Product Structure</p>
          <div className="mt-4">
            <ProductFlow steps={study.flow} />
          </div>
        </Container>
      </section>

      {/* STORY SECTIONS — verified copy + marked asset slots */}
      <div>
        {study.sections.map((section, index) => (
          <section
            key={section.heading}
            className={`py-16 md:py-20 ${index % 2 === 1 ? "bg-paper-soft" : "bg-paper"}`}
          >
            <Container size="wide">
              <div className="grid gap-8 lg:grid-cols-[1fr_1.3fr] lg:items-start">
                <h2 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-ink">
                  {section.heading}
                </h2>
                <div className="flex flex-col gap-5">
                  {section.body ? (
                    <p className="text-pretty leading-relaxed text-fog">{section.body}</p>
                  ) : null}
                  {section.bullets ? (
                    <ul className="flex flex-col gap-2">
                      {section.bullets.map((bullet) => (
                        <li key={bullet} className="flex items-start gap-2.5 text-fog">
                          <span aria-hidden="true" className="mt-2 h-1 w-1 shrink-0 rounded-pill bg-accent" />
                          <span className="leading-relaxed">{bullet}</span>
                        </li>
                      ))}
                    </ul>
                  ) : null}
                  {section.assetLabel ? (
                    <BrowserMockup url={study.projectUrl.replace(/^https?:\/\//, "").replace(/\/$/, "")}>
                      <ImageFrame label={section.assetLabel} aspect="video" dark={index % 3 === 1} />
                    </BrowserMockup>
                  ) : null}
                </div>
              </div>
            </Container>
          </section>
        ))}
      </div>

      {/* RESULT — verified non-numerical copy (PCT only) */}
      {study.result ? (
        <section className="bg-ink py-20">
          <Container>
            <div className="flex flex-col items-start gap-4">
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark">The Result</p>
              <p className="text-balance font-display font-bold leading-tight tracking-tight text-(length:--text-section) text-paper">
                {study.result}
              </p>
            </div>
          </Container>
        </section>
      ) : null}

      {/* CLOSING CTA — project visit + site conversion path */}
      <CTABlock />
    </>
  );
}

/** Unknown slug — the prerender only emits known slugs; unknown ones get this 404 view. */
function NotFoundish() {
  return (
    <main className="grid min-h-[60vh] place-items-center bg-paper">
      <div className="text-center">
        <p className="font-display text-(length:--text-section) font-bold text-ink">404</p>
        <p className="mt-2 text-fog">This case study doesn&apos;t exist.</p>
      </div>
    </main>
  );
}
