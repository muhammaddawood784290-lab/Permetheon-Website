import type { Project } from "@/data/projects";
import { ImageFrame } from "@/components/ImageFrame";
import { Card } from "@/components/Card";
import { CaseStudyButton } from "./CaseStudyButton";
import { ExternalProjectButton } from "./ExternalProjectButton";
import { ProjectCategory } from "./ProjectCategory";
import { ProjectMetadata } from "./ProjectMetadata";

type ProjectCardProps = {
  project: Project;
  /** Work-page compact variant: hides description beyond short text. */
  compact?: boolean;
  dark?: boolean;
};

/**
 * Reusable project card (Master Phase 03). Consumes ONLY the Project data
 * structure — adding a project never requires touching this component
 * (Gate 03 reusability test).
 */
export function ProjectCard({ project, compact = false, dark = false }: ProjectCardProps) {
  return (
    <Card dark={dark} className="flex h-full flex-col gap-4">
      <ImageFrame
        src={project.previewImage?.src}
        alt={project.previewImage?.alt}
        label={project.featuredImageLabel}
        aspect="video"
        dark={dark}
      />
      <div className="flex flex-1 flex-col gap-3">
        <ProjectCategory category={project.industry} dark={dark} />
        <h3 className={`font-display text-xl font-bold tracking-tight ${dark ? "text-paper" : "text-ink"}`}>
          {project.name}
        </h3>
        <p className={`text-xs font-medium ${dark ? "text-fog-dark" : "text-fog"}`}>
          {project.category}
        </p>
        {!compact ? (
          <p className={`text-sm leading-relaxed ${dark ? "text-fog-dark" : "text-fog"}`}>
            {project.shortDescription}
          </p>
        ) : null}
        <ProjectMetadata tags={project.metadata} dark={dark} max={compact ? 2 : undefined} />
      </div>
      <div className="flex flex-wrap items-center gap-3 border-t border-line pt-4 dark:border-line-dark">
        <CaseStudyButton href={project.caseStudyUrl} projectName={project.name} dark={dark} />
        <ExternalProjectButton href={project.projectUrl} projectName={project.name} dark={dark} />
      </div>
    </Card>
  );
}
