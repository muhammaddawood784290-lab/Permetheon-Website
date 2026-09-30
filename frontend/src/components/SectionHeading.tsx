type SectionHeadingProps = {
  eyebrow?: string;
  title: string;
  description?: string;
  align?: "left" | "center";
  dark?: boolean;
};

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = "left",
  dark = false,
}: SectionHeadingProps) {
  const alignment = align === "center" ? "text-center mx-auto items-center" : "text-left items-start";
  return (
    <div className={`flex max-w-2xl flex-col gap-4 ${alignment}`}>
      {eyebrow ? (
        <span
          className={`text-xs font-semibold uppercase tracking-[0.2em] ${
            dark ? "text-fog-dark" : "text-accent"
          }`}
        >
          {eyebrow}
        </span>
      ) : null}
      <h2
        className={`text-balance font-display font-bold leading-[1.05] tracking-tight text-(length:--text-section) ${
          dark ? "text-paper" : "text-ink"
        }`}
      >
        {title}
      </h2>
      {description ? (
        <p className={`text-pretty text-base leading-relaxed ${dark ? "text-fog-dark" : "text-fog"}`}>
          {description}
        </p>
      ) : null}
    </div>
  );
}
