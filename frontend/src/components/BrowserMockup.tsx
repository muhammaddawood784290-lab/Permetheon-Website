import type { ReactNode } from "react";

type BrowserMockupProps = {
  children: ReactNode;
  url?: string;
  className?: string;
  dark?: boolean;
};

/**
 * Premium browser frame for REAL project screenshots (Master Sec. 19).
 * Content passed as children must be an actual screenshot/image —
 * never a fabricated UI. Placeholder content is clearly marked.
 */
export function BrowserMockup({ children, url, className = "", dark = false }: BrowserMockupProps) {
  return (
    <figure
      className={`overflow-hidden rounded-card border shadow-xl ${
        dark ? "border-line-dark shadow-black/40" : "border-line shadow-black/10"
      } ${className}`}
    >
      <div
        className={`flex items-center gap-3 border-b px-4 py-3 ${
          dark ? "border-line-dark bg-ink-soft" : "border-line bg-paper-soft"
        }`}
      >
        <div className="flex gap-1.5" aria-hidden="true">
          <span className="h-2.5 w-2.5 rounded-pill bg-fog/30" />
          <span className="h-2.5 w-2.5 rounded-pill bg-fog/30" />
          <span className="h-2.5 w-2.5 rounded-pill bg-fog/30" />
        </div>
        {url ? (
          <span
            className={`truncate rounded-pill px-3 py-1 text-xs ${
              dark ? "bg-white/5 text-fog-dark" : "bg-paper text-fog"
            }`}
          >
            {url}
          </span>
        ) : null}
      </div>
      <div className={dark ? "bg-ink" : "bg-paper"}>{children}</div>
    </figure>
  );
}
