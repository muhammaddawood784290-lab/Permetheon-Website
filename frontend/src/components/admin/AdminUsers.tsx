import { useCallback, useEffect, useState } from "react";
import { apiUrl } from "@/lib/api";
import { csrfHeaders } from "@/lib/meetings";
import { Button } from "../Button";
import { ErrorBanner } from "./ErrorBanner";

/**
 * ADMIN USER MANAGEMENT — /admin/users.
 * Gated by the admins.* permissions: the session payload's `permissions`
 * decide which affordances render; the server remains the authority (every
 * write re-checked by `can:` middleware — a MANAGER gets a clean 403 message,
 * never a broken screen). SUPER_ADMIN-only actions:
 *   create account · change role · delete account.
 * Reads (admins.read) are available to every admin role.
 */

type AdminRole = "SUPER_ADMIN" | "MANAGER" | "ADMIN";

type AdminAccount = {
  id: string;
  email: string;
  name: string;
  role: AdminRole;
  isActive: boolean;
  liveSessions: number;
  createdAt: string;
};

type SessionPayload = {
  admin: { adminId?: string; email: string; name: string; role: AdminRole };
  permissions: string[];
};

const ROLES: AdminRole[] = ["SUPER_ADMIN", "MANAGER", "ADMIN"];

const ROLE_DESCRIPTION: Record<AdminRole, string> = {
  SUPER_ADMIN: "Everything, including user administration.",
  MANAGER: "Everything except user administration.",
  ADMIN: "Read-mostly: inquiries, notes, calendar view.",
};

const selectClasses =
  "rounded-card border border-line bg-paper-soft px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30";

function formatDate(iso: string): string {
  const [date = ""] = iso.split("T");
  const [y = "", m = "", d = ""] = date.split("-");
  const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  return `${Number(d)} ${months[Number(m) - 1] ?? m} ${y}`;
}

function RoleBadge({ role }: { role: AdminRole }) {
  const styles: Record<AdminRole, string> = {
    SUPER_ADMIN: "border-accent/40 bg-accent/10 text-ink",
    MANAGER: "border-line bg-paper-soft text-ink",
    ADMIN: "border-line bg-paper-soft text-fog",
  };
  return (
    <span className={`inline-block rounded-pill border px-2.5 py-0.5 text-xs font-medium ${styles[role]}`}>
      {role}
    </span>
  );
}

export function AdminUsers() {
  const [session, setSession] = useState<SessionPayload | null>(null);
  const [admins, setAdmins] = useState<AdminAccount[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // create form
  const [formOpen, setFormOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [role, setRole] = useState<AdminRole>("MANAGER");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const loadSession = useCallback(async () => {
    try {
      const r = await fetch(apiUrl("/api/admin/auth/session"), { cache: "no-store" });
      if (!r.ok) throw new Error(String(r.status));
      const body = (await r.json()) as { data: SessionPayload };
      setSession(body.data);
    } catch {
      setError("Your session has expired. Please sign in again.");
    }
  }, []);

  const loadAdmins = useCallback(async () => {
    try {
      const r = await fetch(apiUrl("/api/admin/users"), { cache: "no-store" });
      if (r.status === 401 || r.status === 403) {
        setError("Your session has expired. Please sign in again.");
        return;
      }
      if (!r.ok) throw new Error(String(r.status));
      const body = (await r.json()) as { data: { admins: AdminAccount[] } };
      setAdmins(body.data.admins);
    } catch {
      setError("The account list could not load. Please try again.");
    }
  }, []);

  useEffect(() => {
    void loadSession();
    void loadAdmins();
  }, [loadSession, loadAdmins]);

  const canCreate = !!session?.permissions.includes("admins.create");
  const canDelete = !!session?.permissions.includes("admins.delete");
  const canChangeRole = !!session?.permissions.includes("admins.role.update");
  const selfEmail = session?.admin.email ?? "";

  async function createAccount(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setFieldErrors({});
    setNotice(null);
    try {
      const r = await fetch(apiUrl("/api/admin/users"), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeaders() },
        body: JSON.stringify({ email, name, role, password }),
      });
      const body = (await r.json()) as {
        data?: { admin: AdminAccount };
        error?: { fields?: Record<string, string>; message?: string };
      };
      if (r.status === 201 && body.data) {
        setNotice(`Account created for ${body.data.admin.email}. Share the password through a secure channel.`);
        setEmail("");
        setName("");
        setPassword("");
        setRole("MANAGER");
        setFormOpen(false);
        await loadAdmins();
      } else if (body.error?.fields) {
        setFieldErrors(body.error.fields);
      } else if (r.status === 403) {
        setError("You do not have permission to create accounts.");
      } else {
        setError(body.error?.message ?? "The account could not be created.");
      }
    } catch {
      setError("The account could not be created. Please try again.");
    } finally {
      setBusy(false);
    }
  }

  async function changeRole(target: AdminAccount, nextRole: AdminRole) {
    if (nextRole === target.role) return;
    if (
      !window.confirm(
        `Change ${target.email} from ${target.role} to ${nextRole}?\n\nTheir active sessions will be signed out and their permissions change immediately.`,
      )
    ) {
      await loadAdmins(); // revert the select
      return;
    }
    setBusy(true);
    setNotice(null);
    try {
      const r = await fetch(apiUrl(`/api/admin/users/${target.id}/role`), {
        method: "PATCH",
        headers: { "Content-Type": "application/json", ...csrfHeaders() },
        body: JSON.stringify({ role: nextRole }),
      });
      if (r.ok) {
        setNotice(`${target.email} is now ${nextRole}.`);
        await loadAdmins();
      } else {
        const body = (await r.json().catch(() => null)) as { error?: { message?: string } } | null;
        setError(body?.error?.message ?? "The role could not be changed.");
        await loadAdmins();
      }
    } finally {
      setBusy(false);
    }
  }

  async function deleteAccount(target: AdminAccount) {
    if (!window.confirm(`Permanently delete the account ${target.email}?\n\nTheir active sessions will be signed out. This cannot be undone.`)) {
      return;
    }
    setBusy(true);
    setNotice(null);
    try {
      const r = await fetch(apiUrl(`/api/admin/users/${target.id}`), {
        method: "DELETE",
        headers: csrfHeaders(),
      });
      if (r.ok) {
        setNotice(`${target.email} was deleted.`);
        await loadAdmins();
      } else {
        const body = (await r.json().catch(() => null)) as { error?: { message?: string } } | null;
        setError(body?.error?.message ?? "The account could not be deleted.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      {error ? <ErrorBanner message={error} onDismiss={() => setError(null)} /> : null}
      {notice ? (
        <p role="status" className="rounded-card border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-ink">
          {notice}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-fog">
          Accounts with admin-panel access. Every write is re-validated server-side.
        </p>
        {canCreate ? (
          <Button variant="primary" onClick={() => setFormOpen((v) => !v)}>
            {formOpen ? "Cancel" : "Add account"}
          </Button>
        ) : (
          <span className="text-xs text-fog">
            Your role cannot create accounts — only a SUPER_ADMIN can.
          </span>
        )}
      </div>

      {formOpen && canCreate ? (
        <form
          onSubmit={(e) => void createAccount(e)}
          className="grid gap-4 rounded-card border border-line bg-paper-soft p-6 md:grid-cols-2"
          noValidate
        >
          <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
            Email
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={selectClasses}
              autoComplete="off"
            />
            {fieldErrors.email ? <span className="text-xs normal-case text-red-700">{fieldErrors.email}</span> : null}
          </label>
          <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
            Name
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className={selectClasses}
              maxLength={100}
            />
            {fieldErrors.name ? <span className="text-xs normal-case text-red-700">{fieldErrors.name}</span> : null}
          </label>
          <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
            Role
            <select value={role} onChange={(e) => setRole(e.target.value as AdminRole)} className={selectClasses}>
              {ROLES.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
            <span className="text-xs normal-case text-fog">{ROLE_DESCRIPTION[role]}</span>
            {fieldErrors.role ? <span className="text-xs normal-case text-red-700">{fieldErrors.role}</span> : null}
          </label>
          <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
            Password (min 8 characters)
            <input
              type="text"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className={`${selectClasses} font-mono`}
              autoComplete="off"
            />
            {fieldErrors.password ? (
              <span className="text-xs normal-case text-red-700">{fieldErrors.password}</span>
            ) : null}
          </label>
          <div className="md:col-span-2">
            <Button variant="primary" type="submit" disabled={busy}>
              Create account
            </Button>
          </div>
        </form>
      ) : null}

      {admins === null ? (
        <p className="text-sm text-fog">Loading accounts…</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {admins.map((a) => {
            const isSelf = a.email === selfEmail;
            const lastSuper =
              a.role === "SUPER_ADMIN" && a.isActive && admins.filter((x) => x.role === "SUPER_ADMIN" && x.isActive).length <= 1;
            return (
              <li
                key={a.id}
                className={`flex flex-col gap-3 rounded-card border p-4 md:flex-row md:items-center md:justify-between ${
                  isSelf ? "border-accent/50 bg-accent/5" : "border-line bg-paper-soft"
                }`}
              >
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2 font-semibold text-ink">
                    {a.name}
                    {isSelf ? <span className="text-xs font-normal text-fog">(you)</span> : null}
                    <RoleBadge role={a.role} />
                    {!a.isActive ? (
                      <span className="inline-block rounded-pill border border-red-500/40 bg-red-500/10 px-2.5 py-0.5 text-xs font-medium text-red-700">
                        INACTIVE
                      </span>
                    ) : null}
                  </p>
                  <p className="mt-0.5 truncate text-sm text-fog">
                    {a.email} · added {formatDate(a.createdAt)} · live sessions: {a.liveSessions}
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  {canChangeRole ? (
                    <label className="flex items-center gap-2 text-xs text-fog">
                      <span className="sr-only">Role for {a.email}</span>
                      <select
                        value={a.role}
                        disabled={busy || isSelf || lastSuper}
                        onChange={(e) => void changeRole(a, e.target.value as AdminRole)}
                        className={selectClasses}
                        aria-label={`Role for ${a.email}`}
                      >
                        {ROLES.map((r) => (
                          <option key={r} value={r}>
                            {r}
                          </option>
                        ))}
                      </select>
                    </label>
                  ) : null}
                  {canDelete ? (
                    <Button
                      variant="secondary"
                      disabled={busy || isSelf || lastSuper}
                      onClick={() => void deleteAccount(a)}
                    >
                      Delete
                    </Button>
                  ) : null}
                  {!canChangeRole && !canDelete ? (
                    <span className="text-xs text-fog">Read-only</span>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
