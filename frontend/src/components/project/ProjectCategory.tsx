type ProjectCategoryProps = {
  category: string;
  dark?: boolean;
};

/** Small category/industry label used on project cards (Master Phase 03 component list). */
export function ProjectCategory({ category, dark = false }: ProjectCategoryProps) {
  return (
    <span
      className={`text-xs font-semibold uppercase tracking-[0.2em] ${
        dark ? "text-fog-dark" : "text-accent"
      }`}
    >
      {category}
    </span>
  );
}
