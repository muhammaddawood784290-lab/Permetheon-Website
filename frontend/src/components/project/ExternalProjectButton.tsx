import { Button } from "@/components/Button";

type ExternalProjectButtonProps = {
  href?: string;
  projectName: string;
  dark?: boolean;
};

/**
 * "Visit Project" CTA (Master Phase 03 component list).
 * Renders nothing when no project URL exists — per Blocker 05,
 * a project presented without a working link loses its Visit CTA.
 */
export function ExternalProjectButton({ href, projectName, dark = false }: ExternalProjectButtonProps) {
  if (!href) return null;
  return (
    <Button href={href} variant="ghost" dark={dark}>
      Visit {projectName} ↗
    </Button>
  );
}
