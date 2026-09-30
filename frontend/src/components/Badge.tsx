import type { ReactNode } from "react";

type BadgeProps = {
  children: ReactNode;
  dark?: boolean;
};

export function Badge({ children, dark = false }: BadgeProps) {
  return (
    <span
      className={`inline-flex items-center rounded-pill border px-3 py-1 text-xs font-medium tracking-wide ${
        dark ? "border-line-dark text-fog-dark" : "border-line text-fog"
      }`}
    >
      {children}
    </span>
  );
}
