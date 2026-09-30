import { Button } from "@/components/Button";

type CaseStudyButtonProps = {
  href: string;
  projectName: string;
  variant?: "primary" | "secondary" | "ghost";
  dark?: boolean;
};

/** CTA to a project's case study (Master Phase 03 component list). */
export function CaseStudyButton({ href, projectName, variant = "primary", dark = false }: CaseStudyButtonProps) {
  return (
    <Button href={href} variant={variant} dark={dark}>
      {variant === "ghost" ? `View Case Study →` : `Explore ${projectName}`}
    </Button>
  );
}
