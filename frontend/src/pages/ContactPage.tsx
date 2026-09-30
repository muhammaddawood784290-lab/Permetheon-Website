import { InquiryForm } from "@/components/InquiryForm";
import { Container } from "@/components/Container";

// Verified copy (docs/09 — Master Sec. 16). Canonical business inquiry form (spec §11):
// validated submissions persist to the database and are managed in the admin portal.
// SEO metadata for this route lives in lib/pageSeo.ts (baked at prerender).
export function ContactPage() {
  return (
    <>
      <section className="bg-ink py-20 md:py-28">
        <Container size="wide">
          <div className="flex max-w-3xl flex-col items-start gap-5">
            <span className="text-xs font-semibold uppercase tracking-[0.2em] text-fog-dark">
              Contact
            </span>
            <h1 className="text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) text-paper">
              Let&apos;s build something useful.
            </h1>
            <p className="text-pretty text-(length:--text-lead) leading-relaxed text-fog-dark">
              Start the Conversation — tell us about the business problem, and we&apos;ll take it
              from there.
            </p>
          </div>
        </Container>
      </section>

      <section className="py-24 md:py-30">
        <Container size="default">
          <InquiryForm />
        </Container>
      </section>
    </>
  );
}
