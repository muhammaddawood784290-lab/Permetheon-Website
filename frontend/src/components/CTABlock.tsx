import { Button } from "./Button";
import { Container } from "./Container";

export function CTABlock() {
  return (
    <section className="relative overflow-hidden bg-ink py-28 md:py-36">
      {/* Static subtle grid — motion polish is a Phase 14 deliverable (Master Sec. 18: do not over-animate) */}
      <div className="grid-bg absolute inset-0 opacity-60" aria-hidden="true" />
      <Container className="relative">
        <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
          <h2 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-paper">
            Have a business problem worth solving?
          </h2>
          <p className="text-pretty text-(length:--text-lead) text-fog-dark">Let&apos;s turn it into a digital product.</p>
          <div className="mt-2 flex flex-col gap-3 sm:flex-row">
            <Button href="/contact" variant="primary" dark>
              Start a Project
            </Button>
            <Button href="/work" variant="secondary" dark>
              Explore Our Work
            </Button>
          </div>
        </div>
      </Container>
    </section>
  );
}
