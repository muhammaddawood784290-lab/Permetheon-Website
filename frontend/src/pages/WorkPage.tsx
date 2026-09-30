
import { useMemo, useState } from "react";
import { Container } from "@/components/Container";
import { CTABlock } from "@/components/CTABlock";
import { ProjectCard } from "@/components/project/ProjectCard";
import { SectionHeading } from "@/components/SectionHeading";
import { FILTER_CATEGORIES, PROJECTS } from "@/data/projects";

export function WorkPage() {
  const [activeFilter, setActiveFilter] = useState<(typeof FILTER_CATEGORIES)[number]>("All");

  const visibleProjects = useMemo(
    () =>
      activeFilter === "All"
        ? PROJECTS
        : PROJECTS.filter((p) => p.filters.includes(activeFilter)),
    [activeFilter]
  );

  return (
    <>
      <section className="bg-ink py-24 md:py-30">
        <Container size="wide">
          <SectionHeading
            eyebrow="Work"
            title="Work that speaks for itself."
            description="From customer-facing platforms to internal business systems, every project starts with a real problem to solve."
            dark
          />
        </Container>
      </section>

      <section className="py-20 md:py-24">
        <Container size="wide">
          {/* Category filters — Master Sec. 07/Phase 04 (verified list, fixed order) */}
          <div role="group" aria-label="Filter projects by category" className="flex flex-wrap gap-2.5">
            {FILTER_CATEGORIES.map((category) => {
              const isActive = category === activeFilter;
              const count =
                category === "All" ? PROJECTS.length : PROJECTS.filter((p) => p.filters.includes(category)).length;
              return (
                <button
                  key={category}
                  type="button"
                  aria-pressed={isActive}
                  onClick={() => setActiveFilter(category)}
                  className={`inline-flex items-center gap-2 rounded-pill border px-4 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? "border-accent bg-accent text-white"
                      : "border-line text-fog hover:border-fog hover:text-ink"
                  }`}
                >
                  {category}
                  <span className={`text-xs ${isActive ? "text-white/70" : "text-fog/70"}`}>{count}</span>
                </button>
              );
            })}
          </div>

          {/* Project grid — data-driven; adding a project never changes this page */}
          <div className="mt-12 grid gap-6 md:grid-cols-2">
            {visibleProjects.map((project) => (
              <ProjectCard key={project.id} project={project} compact />
            ))}
          </div>

          {visibleProjects.length === 0 ? (
            <p className="mt-12 text-sm text-fog">
              No projects in this category yet — the architecture already supports adding future
              Permetheon projects with data only.
            </p>
          ) : null}
        </Container>
      </section>

      <CTABlock />
    </>
  );
}
