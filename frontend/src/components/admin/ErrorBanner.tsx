import { useEffect, useRef } from "react";

/**
 * ADMIN ERROR BANNER — shared across the admin surfaces (dashboard, users,
 * calendar, login form).
 *
 * Auto-dismisses a few seconds after a failed request so stale errors don't
 * linger over updated content, and offers a manual × for impatient readers.
 * Session-expiry messages ("Please sign in again") opt OUT of auto-dismiss:
 * they describe a persistent state, not a transient failure, and are the
 * only navigation cue back to the login form.
 *
 * Auto-dismiss only counts while the tab is visible — a hidden tab pauses
 * the countdown so the message is never silently gone before it was seen.
 */

const AUTO_DISMISS_MS = 6000;

export function ErrorBanner({ message, onDismiss }: { message: string; onDismiss: () => void }) {
  const persistent = message.includes("sign in again");

  // Keep the latest callback in a ref so the countdown restarts only when
  // the MESSAGE changes — not on every unrelated parent re-render (the
  // inline `() => setError(null)` closure has a new identity each render).
  const dismissRef = useRef(onDismiss);
  useEffect(() => {
    dismissRef.current = onDismiss;
  }, [onDismiss]);

  useEffect(() => {
    if (persistent) return;
    const timer = window.setTimeout(() => dismissRef.current(), AUTO_DISMISS_MS);
    return () => window.clearTimeout(timer);
  }, [persistent, message]);

  return (
    <div
      role="alert"
      className="flex items-start justify-between gap-3 rounded-card border border-accent/40 bg-accent/10 p-4 text-sm text-ink"
    >
      <p className="min-w-0">
        {message}
        {persistent ? (
          <>
            {" "}
            <a href="/admin/login" className="underline">
              Sign in
            </a>
          </>
        ) : null}
      </p>
      <button
        type="button"
        onClick={onDismiss}
        aria-label="Dismiss error"
        className="shrink-0 rounded-pill px-2 py-0.5 leading-none text-fog transition-colors hover:bg-accent/20 hover:text-ink"
      >
        ×
      </button>
    </div>
  );
}
