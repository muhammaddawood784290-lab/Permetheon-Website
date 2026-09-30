import type { ReactNode } from "react";

type CardProps = {
  children: ReactNode;
  dark?: boolean;
  className?: string;
};

export function Card({ children, dark = false, className = "" }: CardProps) {
  return (
    <div
      className={`rounded-card border p-6 transition-colors duration-200 ${
        dark
          ? "border-line-dark bg-ink-soft hover:border-fog-dark/40"
          : "border-line bg-paper hover:border-fog/40"
      } ${className}`}
    >
      {children}
    </div>
  );
}
