import { Badge } from "@/components/Badge";

type ProjectMetadataProps = {
  tags: string[];
  dark?: boolean;
  max?: number;
};

/** Renders a project's verified metadata tags (Master Phase 03 component list). */
export function ProjectMetadata({ tags, dark = false, max }: ProjectMetadataProps) {
  const visible = typeof max === "number" ? tags.slice(0, max) : tags;
  return (
    <div className="flex flex-wrap gap-2">
      {visible.map((tag) => (
        <Badge key={tag} dark={dark}>
          {tag}
        </Badge>
      ))}
    </div>
  );
}
