import { Button } from "@/components/Button";
import { Container } from "@/components/Container";

/** 404 — a real application 404, never a redirect to the homepage. */
export function NotFoundPage() {
  return (
    <main className="grid min-h-screen place-items-center bg-paper">
      <Container size="prose">
        <div className="flex flex-col items-center gap-6 text-center">
          <p className="font-display text-(length:--text-hero) font-bold tracking-tight text-ink">
            404
          </p>
          <p className="text-pretty text-(length:--text-lead) text-fog">
            The page you&apos;re looking for doesn&apos;t exist or has moved.
          </p>
          <Button href="/" variant="primary">
            Back to the homepage
          </Button>
        </div>
      </Container>
    </main>
  );
}
