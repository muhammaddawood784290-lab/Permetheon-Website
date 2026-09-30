import type { Project } from "@/data/projects";
import { BrowserMockup } from "@/components/BrowserMockup";
import { ImageFrame } from "@/components/ImageFrame";
import { CaseStudyButton } from "./CaseStudyButton";
import { ExternalProjectButton } from "./ExternalProjectButton";
import { ProjectMetadata } from "./ProjectMetadata";

type FeaturedProjectProps = {
  project: Project;
  index?: number;
};

/**
 * Editorial featured-project presentation for the homepage (Master Sec. 06).
 * Same data source as ProjectCard — presentation differs, source doesn't.
 */
export function FeaturedProject({ project, index = 0 }: FeaturedProjectProps) {
  const alternateDark = index % 3 === 1;
  return (
    <article className="flex flex-col gap-5">
      <BrowserMockup url={project.projectUrl.replace(/^https?:\/\//, "").replace(/\/$/, "")}>
        <ImageFrame
          src={project.previewImage?.src}
          alt={project.previewImage?.alt}
          label={project.featuredImageLabel}
          aspect="video"
          dark={alternateDark}
        />
      </BrowserMockup>
      <div className="flex flex-col items-start gap-3">
        <ProjectMetadata tags={project.metadata} max={2} />
        <h3 className="font-display text-2xl font-bold tracking-tight text-ink">
          {project.name}
          <span className="text-fog"> — {project.fullPositioning}</span>
        </h3>
        <p className="text-pretty leading-relaxed text-fog">{project.shortDescription}</p>
        <div className="mt-1 flex flex-wrap gap-3">
          <CaseStudyButton href={project.caseStudyUrl} projectName={project.name} />
          <ExternalProjectButton href={project.projectUrl} projectName={project.name} />
        </div>
      </div>
    </article>
  );
}
