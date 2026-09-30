type ProductFlowProps = {
  steps: string[];
  dark?: boolean;
};

/** Renders the Master-verified product-structure flow (e.g., Customer → Booking Portal → Reservation → …). */
export function ProductFlow({ steps, dark = false }: ProductFlowProps) {
  return (
    <ol className="flex flex-wrap items-center gap-2" aria-label="Product flow">
      {steps.map((step, index) => (
        <li key={step} className="flex items-center gap-2">
          {index > 0 ? (
            <span aria-hidden="true" className={dark ? "text-fog-dark" : "text-fog"}>
              →
            </span>
          ) : null}
          <span
            className={`rounded-pill border px-3.5 py-1.5 text-sm font-medium ${
              dark ? "border-line-dark bg-ink-soft text-paper" : "border-line bg-paper-soft text-ink"
            }`}
          >
            {step}
          </span>
        </li>
      ))}
    </ol>
  );
}
