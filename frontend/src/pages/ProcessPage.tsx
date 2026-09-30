import { Button } from "@/components/Button";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { PROCESS_STAGE_DETAILS, PROCESS_STAGES } from "@/data/projects";

// Verified copy (docs/09 — Master Sec. 11); expanded stage copy per docs/20 draft,
// owner-authorized for this build (formal B-007 approval still pending).
export function ProcessPage() {
  return (
    <>
      {/* HERO — Master Sec. 11 (verified heading) + docs/20 draft intro */}
      <section className="bg-ink py-20 md:py-28">
        <Container size="wide">
          <div className="flex max-w-3xl flex-col items-start gap-5">
            <span className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark">
              Process
            </span>
            <h1 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-paper">
              How We Build
            </h1>
            <p className="text-pretty text-(length:--text-lead) leading-relaxed text-fog-dark">
              Every project moves through six deliberate stages. No shortcuts, no black boxes — you
              see the work, you shape the product, and you know what happens next at every step.
            </p>
            <p className="rounded-card border border-line-dark px-3 py-1.5 text-xs text-fog-dark">
              Expanded stage copy is derived from our six-stage framework and awaits formal owner
              sign-off (B-007) — it contains no factual claims.
            </p>
            <div className="mt-2">
              <Button href="/contact" variant="primary" dark>
                Start a Project
              </Button>
            </div>
          </div>
        </Container>
      </section>

      {/* SIX STAGES — verified definitions (Master Sec. 11) + expanded detail (docs/20) */}
      <section className="py-24 md:py-30">
        <Container size="wide">
          <ol className="flex flex-col gap-16">
            {PROCESS_STAGES.map((stage) => {
              const details = PROCESS_STAGE_DETAILS[stage.name];
              return (
                <li
                  key={stage.number}
                  className="grid gap-8 border-t border-line pt-10 lg:grid-cols-[240px_1fr]"
                >
                  <div className="flex flex-row items-baseline gap-4 lg:flex-col lg:gap-2">
                    <span className="font-display text-4xl font-bold text-accent">
                      {stage.number}
                    </span>
                    <h2 className="font-display text-2xl font-semibold text-ink">{stage.name}</h2>
                  </div>
                  <div className="flex flex-col gap-8">
                    <p className="max-w-2xl text-pretty text-lg leading-relaxed text-ink">
                      {stage.summary}
                    </p>
                    {details ? (
                      <div className="grid gap-8 sm:grid-cols-3">
                        <div className="flex flex-col gap-3">
                          <h3 className="text-xs font-semibold uppercase tracking-[0.18em] text-fog">
                            What happens
                          </h3>
                          <ul className="flex flex-col gap-2">
                            {details.activities.map((item) => (
                              <li
                                key={item}
                                className="text-sm leading-relaxed text-fog"
                              >
                                {item}
                              </li>
                            ))}
                          </ul>
                        </div>
                        <div className="flex flex-col gap-3">
                          <h3 className="text-xs font-semibold uppercase tracking-[0.18em] text-fog">
                            What you get
                          </h3>
                          <ul className="flex flex-col gap-2">
                            {details.deliverables.map((item) => (
                              <li
                                key={item}
                                className="text-sm leading-relaxed text-fog"
                              >
                                {item}
                              </li>
                            ))}
                          </ul>
                        </div>
                        <div className="flex flex-col gap-3">
                          <h3 className="text-xs font-semibold uppercase tracking-[0.18em] text-fog">
                            Your involvement
                          </h3>
                          <ul className="flex flex-col gap-2">
                            {details.involvement.map((item) => (
                              <li
                                key={item}
                                className="text-sm leading-relaxed text-fog"
                              >
                                {item}
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ol>

          {/* Stage connection — docs/20, derived from Master Sec. 12 */}
          <p className="mt-16 max-w-2xl text-pretty text-(length:--text-lead) leading-relaxed text-ink">
            We think beyond launch — the foundations we build are made to evolve with your
            business.
          </p>
        </Container>
      </section>

      {/* CTA — Master Sec. 15 (verified) */}
      <CTABlock />
    </>
  );
}
