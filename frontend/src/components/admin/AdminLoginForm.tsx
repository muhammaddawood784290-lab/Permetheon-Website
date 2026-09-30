
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { apiUrl } from "@/lib/api";
import { Button } from "../Button";
import { ErrorBanner } from "./ErrorBanner";

/**
 * ADMIN LOGIN FORM — posts to /api/admin/auth/login (same-origin).
 * Errors are generic by policy (no account enumeration). On success the
 * session + CSRF cookies are set server-side and the admin lands on /admin.
 */
const inputClasses =
  "w-full rounded-card border border-line bg-paper-soft px-4 py-3 text-sm text-ink placeholder:text-fog focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30";

export function AdminLoginForm() {
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    setSubmitting(true);
    setError(null);
    try {
      const response = await fetch(apiUrl("/api/admin/auth/login"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: String(data.get("email") ?? ""),
          password: String(data.get("password") ?? ""),
        }),
      });
      if (response.ok) {
        navigate("/admin", { replace: true });
        return;
      }
      const body = (await response.json().catch(() => null)) as {
        error?: { code?: string; message?: string; fields?: Record<string, string> };
      } | null;
      if (body?.error?.code === "VALIDATION_ERROR" && body.error.fields) {
        setError(Object.values(body.error.fields)[0]);
      } else if (body?.error?.code === "RATE_LIMITED") {
        setError("Too many attempts. Please wait a few minutes and try again.");
      } else {
        setError(body?.error?.message ?? "Login failed.");
      }
    } catch {
      setError("Login failed. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="mt-6 flex flex-col gap-4" aria-busy={submitting}>
      {error ? <ErrorBanner message={error} onDismiss={() => setError(null)} /> : null}
      <div className="flex flex-col gap-2">
        <label htmlFor="admin-email" className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
          Email
        </label>
        <input
          id="admin-email"
          name="email"
          type="email"
          autoComplete="username"
          required
          className={inputClasses}
        />
      </div>
      <div className="flex flex-col gap-2">
        <label htmlFor="admin-password" className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
          Password
        </label>
        <input
          id="admin-password"
          name="password"
          type="password"
          autoComplete="current-password"
          required
          className={inputClasses}
        />
      </div>
      <Button type="submit" variant="primary" disabled={submitting}>
        {submitting ? "Signing in…" : "Sign In →"}
      </Button>
    </form>
  );
}
