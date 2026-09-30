
import { useCallback, useEffect, useMemo, useState } from "react";
import { apiUrl } from "@/lib/api";
import { Button } from "../Button";
import { ErrorBanner } from "./ErrorBanner";
import {
  INQUIRY_PRIORITIES,
  INQUIRY_STATUSES,
  type InquiryPriority,
  type InquiryStatus,
} from "@/lib/inquiries";
import { csrfHeaders, formatLongDate, formatTime12h, splitSlot, type MeetingStatus } from "@/lib/meetings";

/**
 * ADMIN DASHBOARD — real database data only (spec §39/§40).
 * Every number and record comes from the authenticated admin API; when there are
 * no inquiries the dashboard says exactly that — genuine zero-state, never
 * fabricated statistics or sample records.
 *
 * Permissions gate UI affordances; the server remains the authority (spec §10).
 */

type AdminInfo = {
  adminId: string;
  email: string;
  name: string;
  role: "SUPER_ADMIN" | "MANAGER" | "ADMIN";
};

type InquiryMeeting = {
  id: string;
  status: MeetingStatus;
  startsAt: string | null;
  durationMinutes?: number;
  cancelledReason?: string;
};

type Inquiry = {
  id: string;
  name: string;
  company: string | null;
  email: string;
  contactNumber: string;
  projectType: string;
  budget: string | null;
  timeline: string | null;
  message: string;
  status: InquiryStatus;
  priority: InquiryPriority;
  createdAt: string;
  updatedAt: string;
  meeting: InquiryMeeting | null;
};

type Stats = { total: number; byStatus: Record<InquiryStatus, number>; last30Days: number };
type Note = { id: string; adminName: string; body: string; createdAt: string };

type ListPayload = {
  inquiries: Inquiry[];
  pagination: { page: number; pageSize: number; total: number; totalPages: number };
};

const selectClasses =
  "rounded-card border border-line bg-paper-soft px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30";

/**
 * CONTACT ACTIONS (spec §29/§30) — derived from the single canonical E.164 value.
 * Stored stays `+12025550147`; only the wa.me URL drops the `+`. Never a second
 * stored representation, never an assumption the number is WhatsApp-registered.
 * Legacy rows (submitted before the 2026-09-22 amendment) carry '' — they show an
 * honest placeholder and NO actions, because no number exists to derive one from (§44).
 */
function hasContactNumber(e164: string): boolean {
  return e164.length > 0;
}

function telHref(e164: string): string {
  return `tel:${e164}`;
}

function whatsappHref(e164: string): string {
  return `https://wa.me/${e164.replace(/^\+/, "")}`;
}

const STATUS_STYLES: Record<InquiryStatus, string> = {
  NEW: "border-accent/40 bg-accent/10 text-ink",
  REVIEWING: "border-line bg-paper-soft text-ink",
  CONTACTED: "border-line bg-paper-soft text-ink",
  QUALIFIED: "border-line bg-paper-soft text-ink",
  PROPOSAL: "border-line bg-paper-soft text-ink",
  WON: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700",
  LOST: "border-line bg-paper-soft text-fog",
};

const PRIORITY_STYLES: Record<InquiryPriority, string> = {
  LOW: "border-line bg-paper-soft text-fog",
  MEDIUM: "border-line bg-paper-soft text-ink",
  HIGH: "border-accent/40 bg-accent/10 text-ink",
  URGENT: "border-red-500/40 bg-red-500/10 text-red-700",
};

function Badge({ label, style }: { label: string; style: string }) {
  return (
    <span className={`inline-block rounded-pill border px-2.5 py-0.5 text-xs font-medium ${style}`}>
      {label}
    </span>
  );
}

/** Meeting-state badge for list rows (spec §8): No Meeting / BOOKED / … */
function MeetingBadge({ meeting }: { meeting: InquiryMeeting | null }) {
  if (!meeting) {
    return <Badge label="No meeting" style="border-line bg-paper-soft text-fog" />;
  }
  const styles: Record<MeetingStatus, string> = {
    BOOKED: "border-accent/40 bg-accent/10 text-ink",
    COMPLETED: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700",
    CANCELLED: "border-line bg-paper-soft text-fog",
    NO_SHOW: "border-red-500/40 bg-red-500/10 text-red-700",
  };
  return <Badge label={meeting.status === "BOOKED" ? "Booked" : meeting.status === "COMPLETED" ? "Completed" : meeting.status === "CANCELLED" ? "Cancelled" : "No show"} style={styles[meeting.status]} />;
}

/** ISO 8601 UTC → textual UTC display — never the browser's timezone. */
function formatDate(iso: string): string {
  const [date = "", rest = ""] = iso.split("T");
  const [y = "", m = "", d = ""] = date.split("-");
  const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  const time = rest.slice(0, 5);
  return `${Number(d)} ${months[Number(m) - 1] ?? m} ${y}, ${time} UTC`;
}

export function AdminDashboard({ admin }: { admin?: AdminInfo }) {
  // The session admin is fetched from the API; the SPA
  // fetches it from /api/admin/auth/session (the gate in App.tsx already
  // guarantees a 200 before rendering, so the fallback only covers first paint).
  const [sessionAdmin, setSessionAdmin] = useState<AdminInfo | null>(admin ?? null);
  useEffect(() => {
    if (sessionAdmin) return;
    let cancelled = false;
    fetch(apiUrl("/api/admin/auth/session"), { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((body: { data?: { admin?: Omit<AdminInfo, "adminId"> & { adminId?: string } } }) => {
        if (cancelled || !body?.data?.admin) return;
        const a = body.data.admin;
        setSessionAdmin({
          adminId: a.adminId ?? a.email,
          email: a.email,
          name: a.name,
          role: a.role,
        });
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [sessionAdmin]);
  const resolvedAdmin: AdminInfo =
    sessionAdmin ?? { adminId: "", email: "", name: "", role: "ADMIN" };
  const [stats, setStats] = useState<Stats | null>(null);
  const [list, setList] = useState<ListPayload | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<InquiryStatus | "">("");
  // Meetings filter (spec §8): All / With Meeting / Without Meeting / Upcoming /
  // Completed / Cancelled — server-side in a later pass; With/Without is
  // resolved client-side from the page payload (honest, no fake pagination).
  const [meetingFilter, setMeetingFilter] = useState<"" | "with" | "without" | "upcoming" | "completed" | "cancelled">("");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Inquiry | null>(null);
  const [notes, setNotes] = useState<Note[]>([]);
  const [noteDraft, setNoteDraft] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const readJson = useCallback(async function <T>(response: Response): Promise<T | null> {
    if (response.status === 401 || response.status === 403) {
      setError("Your session has expired. Please sign in again.");
      return null;
    }
    if (!response.ok) {
      setError("The dashboard could not load data. Please try again.");
      return null;
    }
    const body = (await response.json()) as { data?: T };
    return body.data ?? null;
  }, []);

  const loadStats = useCallback(async () => {
    const data = await readJson<{ stats: Stats }>(
      await fetch(apiUrl("/api/admin/inquiries/stats"), { cache: "no-store" }),
    );
    if (data) setStats(data.stats);
  }, [readJson]);

  const loadList = useCallback(
    async (signal?: AbortSignal) => {
      const params = new URLSearchParams({ page: String(page) });
      if (search.trim()) params.set("search", search.trim());
      if (statusFilter) params.set("status", statusFilter);
      const data = await readJson<ListPayload>(
        await fetch(apiUrl(`/api/admin/inquiries?${params.toString()}`), { cache: "no-store", signal }),
      );
      if (data) setList(data);
    },
    [page, search, statusFilter, readJson],
  );

  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      void loadList(controller.signal).catch(() => undefined);
    }, 250); // debounce search typing
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [loadList]);

  useEffect(() => {
    void loadStats().catch(() => undefined);
  }, [loadStats]);

  async function openInquiry(inquiry: Inquiry) {
    setSelected(inquiry);
    setNotes([]);
    const data = await readJson<{ notes: Note[] }>(
      await fetch(apiUrl(`/api/admin/inquiries/${inquiry.id}/notes`), { cache: "no-store" }),
    );
    if (data) setNotes(data.notes);
  }

  async function patchInquiry(id: string, patch: { status?: InquiryStatus; priority?: InquiryPriority }) {
    setBusy(true);
    try {
      const csrf = document.cookie.match(/(?:^|;\s*)admin_csrf=([^;]+)/)?.[1] ?? "";
      const response = await fetch(apiUrl(`/api/admin/inquiries/${id}`), {
        method: "PATCH",
        headers: { "Content-Type": "application/json", "x-csrf-token": decodeURIComponent(csrf) },
        body: JSON.stringify(patch),
      });
      const data = await readJson<{ inquiry: Inquiry }>(response);
      if (data) {
        setSelected(data.inquiry);
        await loadList();
        await loadStats();
      }
    } finally {
      setBusy(false);
    }
  }

  async function addNote() {
    if (!selected || !noteDraft.trim()) return;
    setBusy(true);
    try {
      const csrf = document.cookie.match(/(?:^|;\s*)admin_csrf=([^;]+)/)?.[1] ?? "";
      const response = await fetch(apiUrl(`/api/admin/inquiries/${selected.id}/notes`), {
        method: "POST",
        headers: { "Content-Type": "application/json", "x-csrf-token": decodeURIComponent(csrf) },
        body: JSON.stringify({ body: noteDraft.trim() }),
      });
      const data = await readJson<{ note: Note }>(response);
      if (data) {
        setNotes((prev) => [...prev, data.note]);
        setNoteDraft("");
      }
    } finally {
      setBusy(false);
    }
  }

  async function deleteInquiry(inquiry: Inquiry) {
    const meetingNote = inquiry.meeting
      ? inquiry.meeting.status === "BOOKED"
        ? "\n\nThis inquiry has a BOOKED meeting — deleting will remove the meeting record and free its time slot."
        : "\n\nThe associated meeting record will be deleted with it."
      : "";
    if (!window.confirm(`Permanently delete the inquiry from ${inquiry.name}?\n\nThe original message cannot be recovered.${meetingNote}`)) {
      return;
    }
    setBusy(true);
    try {
      const response = await fetch(apiUrl(`/api/admin/inquiries/${inquiry.id}`), {
        method: "DELETE",
        headers: csrfHeaders(),
      });
      const data = await readJson<{ deleted: boolean }>(response);
      if (data) {
        setSelected(null);
        await loadList();
        await loadStats();
      }
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    const csrf = document.cookie.match(/(?:^|;\s*)admin_csrf=([^;]+)/)?.[1] ?? "";
    await fetch(apiUrl("/api/admin/auth/logout"), {
      method: "POST",
      headers: { "x-csrf-token": decodeURIComponent(csrf) },
    }).catch(() => undefined);
    window.location.href = "/admin/login";
  }

  const hasZeroInquiries = useMemo(() => (list?.pagination.total ?? 0) === 0 && !search && !statusFilter && !meetingFilter, [list, search, statusFilter, meetingFilter]);

  // Client-side meeting filter over the current page (spec §8). "Upcoming"
  // compares UTC instants lexicographically (both values ISO 8601 UTC).
  const visibleInquiries = useMemo(() => {
    const rows = list?.inquiries ?? [];
    const nowPrefix = new Date().toISOString().slice(0, 16); // 'YYYY-MM-DDTHH:mm' UTC
    switch (meetingFilter) {
      case "with":
        return rows.filter((r) => r.meeting !== null);
      case "without":
        return rows.filter((r) => r.meeting === null);
      case "upcoming":
        return rows.filter((r) => r.meeting !== null && r.meeting.status === "BOOKED" && (r.meeting.startsAt ?? "").slice(0, 16) >= nowPrefix);
      case "completed":
        return rows.filter((r) => r.meeting?.status === "COMPLETED");
      case "cancelled":
        return rows.filter((r) => r.meeting?.status === "CANCELLED" || r.meeting?.status === "NO_SHOW");
      default:
        return rows;
    }
  }, [list, meetingFilter]);

  return (
    <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="font-display text-xl font-bold tracking-tight text-ink">Business Inquiries</p>
          <p className="mt-1 text-sm text-fog">
            {resolvedAdmin.name} · {resolvedAdmin.email} · {resolvedAdmin.role}
          </p>
        </div>
        <Button variant="secondary" onClick={() => void logout()}>
          Sign out
        </Button>
      </header>

      {error ? <ErrorBanner message={error} onDismiss={() => setError(null)} /> : null}

      {/* Overview — real aggregates only (spec §39). */}
      <section aria-label="Overview" className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
        <div className="rounded-card border border-line bg-paper-soft p-4">
          <p className="text-xs uppercase tracking-[0.14em] text-fog">Total</p>
          <p className="mt-1 font-display text-2xl font-bold text-ink">{stats?.total ?? 0}</p>
        </div>
        {INQUIRY_STATUSES.map((status) => (
          <div key={status} className="rounded-card border border-line bg-paper-soft p-4">
            <p className="text-xs uppercase tracking-[0.14em] text-fog">{status}</p>
            <p className="mt-1 font-display text-2xl font-bold text-ink">{stats?.byStatus[status] ?? 0}</p>
          </div>
        ))}
      </section>

      <div className="grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        {/* Inquiry list (spec §39). */}
        <section aria-label="Inquiry list" className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-3">
            <input
              type="search"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder="Search name, company, email, message…"
              aria-label="Search inquiries"
              className={`${selectClasses} min-w-56 flex-1`}
            />
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value as InquiryStatus | "");
                setPage(1);
              }}
              aria-label="Filter by status"
              className={selectClasses}
            >
              <option value="">All statuses</option>
              {INQUIRY_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
            <select
              value={meetingFilter}
              onChange={(e) => setMeetingFilter(e.target.value as typeof meetingFilter)}
              aria-label="Filter by meeting state"
              className={selectClasses}
            >
              <option value="">All meetings</option>
              <option value="with">With meeting</option>
              <option value="without">Without meeting</option>
              <option value="upcoming">Upcoming</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled / No-show</option>
            </select>
            <a href="/admin/meetings" className="text-sm text-fog underline hover:text-ink">
              Calendar →
            </a>
            <a href="/admin/users" className="text-sm text-fog underline hover:text-ink">
              Admin accounts →
            </a>
          </div>

          {hasZeroInquiries ? (
            <div className="rounded-card border border-dashed border-line bg-paper-soft p-10 text-center">
              <p className="font-display text-lg font-semibold text-ink">No business inquiries yet.</p>
              <p className="mt-2 text-sm text-fog">
                Inquiries submitted through the website contact form appear here.
              </p>
            </div>
          ) : (
            <>
              <ul className="flex flex-col gap-3">
                {visibleInquiries.map((inquiry) => (
                  <li key={inquiry.id}>
                    <button
                      type="button"
                      onClick={() => void openInquiry(inquiry)}
                      aria-expanded={selected?.id === inquiry.id}
                      className={`w-full rounded-card border p-4 text-left transition-colors ${
                        selected?.id === inquiry.id
                          ? "border-accent bg-accent/5"
                          : "border-line bg-paper-soft hover:border-accent/50"
                      }`}
                    >
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-semibold text-ink">
                          {inquiry.name}
                          {inquiry.company ? <span className="font-normal text-fog"> · {inquiry.company}</span> : null}
                        </span>
                        <span className="flex items-center gap-2">
                          <MeetingBadge meeting={inquiry.meeting} />
                          <Badge label={inquiry.priority} style={PRIORITY_STYLES[inquiry.priority]} />
                          <Badge label={inquiry.status} style={STATUS_STYLES[inquiry.status]} />
                        </span>
                      </div>
                      <p className="mt-1 text-sm text-fog">
                        {inquiry.email} · {hasContactNumber(inquiry.contactNumber) ? inquiry.contactNumber : "no contact number"} · {inquiry.projectType}
                        {inquiry.budget ? ` · ${inquiry.budget}` : ""}
                        {inquiry.timeline ? ` · ${inquiry.timeline}` : ""}
                      </p>
                      <p className="mt-1 text-xs text-fog">{formatDate(inquiry.createdAt)}</p>
                    </button>
                  </li>
                ))}
              </ul>
              {list && list.pagination.totalPages > 1 ? (
                <nav className="flex items-center justify-between text-sm text-fog" aria-label="Pagination">
                  <Button variant="secondary" disabled={list.pagination.page <= 1} onClick={() => setPage((p) => p - 1)}>
                    ← Previous
                  </Button>
                  <span>
                    Page {list.pagination.page} of {list.pagination.totalPages} · {list.pagination.total} total
                  </span>
                  <Button
                    variant="secondary"
                    disabled={list.pagination.page >= list.pagination.totalPages}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Next →
                  </Button>
                </nav>
              ) : null}
            </>
          )}
        </section>

        {/* Inquiry detail (spec §39) — complete original submission + approved modifications. */}
        <section aria-label="Inquiry detail" className="lg:sticky lg:top-8 lg:self-start">
          {selected ? (
            <div className="flex flex-col gap-5 rounded-card border border-line bg-paper-soft p-6">
              <div>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h2 className="font-display text-lg font-bold text-ink">{selected.name}</h2>
                  <span className="flex gap-2">
                    <Badge label={selected.priority} style={PRIORITY_STYLES[selected.priority]} />
                    <Badge label={selected.status} style={STATUS_STYLES[selected.status]} />
                  </span>
                </div>
                <p className="mt-1 text-sm text-fog">
                  {selected.email}
                  {selected.company ? ` · ${selected.company}` : ""}
                </p>
                <p className="mt-1 text-sm">
                  <span className="text-xs uppercase tracking-[0.14em] text-fog">Contact Number / WhatsApp · </span>
                  {hasContactNumber(selected.contactNumber) ? (
                    <>
                      <span className="font-mono text-ink">{selected.contactNumber}</span>
                      <span className="ml-2 inline-flex gap-2 text-xs">
                        <a href={telHref(selected.contactNumber)} className="underline hover:text-ink">
                          Call
                        </a>
                        <a
                          href={whatsappHref(selected.contactNumber)}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="underline hover:text-ink"
                        >
                          WhatsApp
                        </a>
                      </span>
                    </>
                  ) : (
                    <span className="text-fog">— not provided (submitted before the contact-number field existed)</span>
                  )}
                </p>
                <p className="mt-0.5 text-xs text-fog">
                  Received {formatDate(selected.createdAt)} · Updated {formatDate(selected.updatedAt)}
                </p>
              </div>

              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-xs uppercase tracking-[0.14em] text-fog">Project type</dt>
                  <dd className="mt-0.5 text-ink">{selected.projectType}</dd>
                </div>
                <div>
                  <dt className="text-xs uppercase tracking-[0.14em] text-fog">Budget</dt>
                  <dd className="mt-0.5 text-ink">{selected.budget ?? "—"}</dd>
                </div>
                <div>
                  <dt className="text-xs uppercase tracking-[0.14em] text-fog">Timeline</dt>
                  <dd className="mt-0.5 text-ink">{selected.timeline ?? "—"}</dd>
                </div>
              </dl>

              <div>
                <p className="text-xs uppercase tracking-[0.14em] text-fog">Original message</p>
                <p className="mt-2 whitespace-pre-wrap rounded-card border border-line bg-paper p-4 text-sm leading-relaxed text-ink">
                  {selected.message}
                </p>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                  Status
                  <select
                    value={selected.status}
                    disabled={busy}
                    onChange={(e) => void patchInquiry(selected.id, { status: e.target.value as InquiryStatus })}
                    className={selectClasses}
                  >
                    {INQUIRY_STATUSES.map((s) => (
                      <option key={s} value={s}>
                        {s}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                  Priority
                  <select
                    value={selected.priority}
                    disabled={busy}
                    onChange={(e) => void patchInquiry(selected.id, { priority: e.target.value as InquiryPriority })}
                    className={selectClasses}
                  >
                    {INQUIRY_PRIORITIES.map((p) => (
                      <option key={p} value={p}>
                        {p}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              {/* Meeting panel (meetings spec §4): date, time, status + actions. */}
              <div className="flex flex-col gap-2 border-t border-line pt-4">
                <p className="text-xs uppercase tracking-[0.14em] text-fog">Meeting</p>
                {selected.meeting ? (
                  <div className="flex flex-col gap-2 rounded-card border border-line bg-paper p-4">
                    <p className="text-sm text-ink">
                      <span className="font-semibold">
                        {selected.meeting.startsAt
                          ? `${formatLongDate(splitSlot(selected.meeting.startsAt).date)} at ${formatTime12h(splitSlot(selected.meeting.startsAt).time)}`
                          : "Scheduled"}
                      </span>
                      {selected.meeting.durationMinutes ? ` · ${selected.meeting.durationMinutes} minutes` : ""}
                      <span className="ml-2">
                        <MeetingBadge meeting={selected.meeting} />
                      </span>
                    </p>
                    {selected.meeting.status === "CANCELLED" && selected.meeting.cancelledReason ? (
                      <p className="text-xs text-fog">Cancelled: {selected.meeting.cancelledReason}</p>
                    ) : null}
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                      <a href={`/admin/meetings`} className="underline text-fog hover:text-ink">
                        Open in calendar →
                      </a>
                    </div>
                  </div>
                ) : (
                  <p className="text-sm text-fog">No meeting booked for this inquiry.</p>
                )}
              </div>

              {/* Destructive action (spec §7): explicit confirm dialog, server
                  enforces the meeting policy — never orphans a meeting. */}
              <div className="flex flex-col gap-2 border-t border-line pt-4">
                <p className="text-xs uppercase tracking-[0.14em] text-fog">Danger zone</p>
                <Button
                  variant="secondary"
                  disabled={busy}
                  onClick={() => void deleteInquiry(selected)}
                >
                  Delete inquiry{selected.meeting ? " (and its meeting)" : ""}
                </Button>
              </div>

              <div className="flex flex-col gap-3 border-t border-line pt-4">
                <p className="text-xs uppercase tracking-[0.14em] text-fog">Internal notes</p>
                {notes.length === 0 ? (
                  <p className="text-sm text-fog">No internal notes yet.</p>
                ) : (
                  <ul className="flex flex-col gap-2">
                    {notes.map((note) => (
                      <li key={note.id} className="rounded-card border border-line bg-paper p-3 text-sm">
                        <p className="text-ink">{note.body}</p>
                        <p className="mt-1 text-xs text-fog">
                          {note.adminName} · {formatDate(note.createdAt)}
                        </p>
                      </li>
                    ))}
                  </ul>
                )}
                <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                  Add internal note
                  <textarea
                    value={noteDraft}
                    onChange={(e) => setNoteDraft(e.target.value)}
                    rows={3}
                    maxLength={2000}
                    className={`${selectClasses} w-full`}
                    placeholder="Visible to administrators only — never sent to the inquirer."
                  />
                </label>
                <Button variant="primary" disabled={busy || !noteDraft.trim()} onClick={() => void addNote()}>
                  Save Note
                </Button>
              </div>
            </div>
          ) : (
            <div className="rounded-card border border-dashed border-line bg-paper-soft p-8 text-center text-sm text-fog">
              Select an inquiry to review the full submission.
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
