import { useCallback, useEffect, useMemo, useState } from "react";
import { apiUrl } from "@/lib/api";
import {
  csrfHeaders,
  dayName,
  formatLongDate,
  formatTime12h,
  splitSlot,
  type MeetingBlock,
  type MeetingSettings,
  type MeetingStatus,
} from "@/lib/meetings";
import { Button } from "../Button";
import { ErrorBanner } from "./ErrorBanner";

/**
 * ADMIN MEETINGS CALENDAR (meetings spec §3) — month / week / day views over
 * /api/admin/meetings, plus the availability settings + block management.
 * Same design tokens as the dashboard (rounded-card, line, paper-soft).
 * The server is the authority: status changes go through PATCH; the calendar
 * refetches after every action.
 */

type CalendarMeeting = {
  id: string;
  inquiryId: string;
  startsAt: string; // ISO 8601 UTC: "2026-09-30T10:00:00Z"
  durationMinutes: number;
  status: MeetingStatus;
  cancelledReason: string;
  customer: { name: string; company: string | null; email: string; contactNumber: string };
  projectType: string;
};

const selectClasses =
  "rounded-card border border-line bg-paper-soft px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30";

const STATUS_STYLES: Record<MeetingStatus, string> = {
  BOOKED: "border-accent/40 bg-accent/10 text-ink",
  COMPLETED: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700",
  CANCELLED: "border-line bg-paper-soft text-fog",
  NO_SHOW: "border-red-500/40 bg-red-500/10 text-red-700",
};

function Badge({ label, style }: { label: string; style: string }) {
  return (
    <span className={`inline-block rounded-pill border px-2.5 py-0.5 text-xs font-medium ${style}`}>
      {label}
    </span>
  );
}

type ViewMode = "month" | "week" | "day";

/** Today on the UTC calendar — never the browser's local timezone. */
function utcToday(): string {
  return new Date().toISOString().slice(0, 10);
}

/** UTC calendar arithmetic on date strings — no local-time component anywhere. */
function addDays(date: string, n: number): string {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

/** Monday-first week containing `date` (UTC). */
function weekStart(date: string): string {
  const d = new Date(`${date}T00:00:00Z`);
  const shift = (d.getUTCDay() + 6) % 7;
  return addDays(date, -shift);
}

/**
 * First cell of the Mon-first month grid containing the 1st of `anchor`'s
 * month — may fall in the PREVIOUS month (e.g. a month starting on Sunday
 * leads with six spillover days).
 */
function monthGridStart(anchor: string): string {
  return weekStart(`${anchor.slice(0, 7)}-01`);
}

export function MeetingsCalendar() {
  const [view, setView] = useState<ViewMode>("month");
  const [anchor, setAnchor] = useState(() => utcToday());
  const [meetings, setMeetings] = useState<CalendarMeeting[]>([]);
  const [timezone, setTimezone] = useState("UTC");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Availability config + blocks.
  const [settings, setSettings] = useState<MeetingSettings | null>(null);
  const [blocks, setBlocks] = useState<MeetingBlock[]>([]);
  const [blockDate, setBlockDate] = useState("");
  const [blockTime, setBlockTime] = useState("");
  const [blockReason, setBlockReason] = useState("");
  const [showSettings, setShowSettings] = useState(false);

  const [selected, setSelected] = useState<CalendarMeeting | null>(null);

  const [from, to] = useMemo(() => {
    if (view === "day") return [anchor, anchor];
    if (view === "week") return [weekStart(anchor), addDays(weekStart(anchor), 6)];
    // Month view: cover the FULL Mon-first 42-cell grid, not just the month —
    // the leading/trailing cells are spillover days from adjacent months and
    // must show their meetings too.
    const start = monthGridStart(anchor);
    return [start, addDays(start, 41)];
  }, [view, anchor]);

  const load = useCallback(async () => {
    setError(null);
    try {
      const res = await fetch(apiUrl(`/api/admin/meetings?from=${from}&to=${to}`), { cache: "no-store" });
      if (res.status === 401 || res.status === 403) {
        setError("Your session has expired. Please sign in again.");
        return;
      }
      const body = (await res.json()) as { data?: { meetings?: CalendarMeeting[]; timezone?: string } };
      setMeetings(body?.data?.meetings ?? []);
      if (body?.data?.timezone) setTimezone(body.data.timezone);
    } catch {
      setError("Could not load the calendar. Please try again.");
    }
  }, [from, to]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadConfig = useCallback(async () => {
    const [s, b] = await Promise.all([
      fetch(apiUrl("/api/admin/availability"), { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)).catch(() => null),
      fetch(apiUrl("/api/admin/blocks"), { cache: "no-store" }).then((r) => (r.ok ? r.json() : null)).catch(() => null),
    ]);
    if (s?.data?.settings) setSettings(s.data.settings);
    if (b?.data?.blocks) setBlocks(b.data.blocks);
  }, []);

  useEffect(() => {
    void loadConfig();
  }, [loadConfig]);

  async function mutate(action: () => Promise<Response>): Promise<boolean> {
    setBusy(true);
    try {
      const res = await action();
      if (!res.ok) {
        const body = (await res.json().catch(() => null)) as { error?: { message?: string } } | null;
        setError(body?.error?.message ?? "The action failed. Please try again.");
        return false;
      }
      setError(null);
      await Promise.all([load(), loadConfig()]);
      return true;
    } finally {
      setBusy(false);
    }
  }

  async function setStatus(meeting: CalendarMeeting, status: MeetingStatus) {
    await mutate(() =>
      fetch(apiUrl(`/api/admin/meetings/${meeting.id}`), {
        method: "PATCH",
        headers: { "Content-Type": "application/json", ...csrfHeaders() },
        body: JSON.stringify({ status }),
      }),
    );
  }

  async function deleteMeeting(meeting: CalendarMeeting) {
    const warning = `Permanently delete this meeting record? This cannot be undone.${
      meeting.status === "BOOKED" ? " The time slot will be freed." : ""
    }`;
    if (!window.confirm(warning)) return;
    await mutate(() =>
      fetch(apiUrl(`/api/admin/meetings/${meeting.id}`), { method: "DELETE", headers: csrfHeaders() }),
    );
    setSelected(null);
  }

  async function createBlock() {
    if (!blockDate && !blockTime) return;
    // Slot blocks are sent as ISO 8601 UTC instants (e.g. 2026-09-30T10:00:00Z).
    const payload = blockTime
      ? { startsAt: `${blockDate}T${blockTime}:00Z`, reason: blockReason }
      : { blockedDate: blockDate, reason: blockReason };
    const ok = await mutate(() =>
      fetch(apiUrl("/api/admin/blocks"), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeaders() },
        body: JSON.stringify(payload),
      }),
    );
    if (ok) {
      setBlockDate("");
      setBlockTime("");
      setBlockReason("");
    }
  }

  async function removeBlock(id: string) {
    await mutate(() => fetch(apiUrl(`/api/admin/blocks/${id}`), { method: "DELETE", headers: csrfHeaders() }));
  }

  async function saveSettings(next: MeetingSettings) {
    await mutate(() =>
      fetch(apiUrl("/api/admin/availability"), {
        method: "PUT",
        headers: { "Content-Type": "application/json", ...csrfHeaders() },
        body: JSON.stringify(next),
      }),
    );
  }

  // Month grid (Mon-first) — 6 rows × 7 cells covering the anchor month.
  const monthCells = useMemo(() => {
    const start = monthGridStart(anchor);
    const cells: { date: string; inMonth: boolean }[] = [];
    for (let i = 0; i < 42; i += 1) {
      const date = addDays(start, i);
      cells.push({ date, inMonth: date.slice(0, 7) === anchor.slice(0, 7) });
    }
    return cells;
  }, [anchor]);

  const byDate = useMemo(() => {
    const map = new Map<string, CalendarMeeting[]>();
    for (const m of meetings) {
      const date = splitSlot(m.startsAt).date;
      const list = map.get(date) ?? [];
      list.push(m);
      map.set(date, list);
    }
    return map;
  }, [meetings]);

  const dayList = useMemo<{ date: string; meetings: CalendarMeeting[] }[]>(
    () =>
      (view === "month"
        ? monthCells.map((c) => c.date)
        : view === "week"
          ? Array.from({ length: 7 }, (_, i) => addDays(weekStart(anchor), i))
          : [anchor]
      ).map((date) => ({
        date,
        meetings: (byDate.get(date) ?? []).slice().sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
      })),
    [view, monthCells, anchor, byDate],
  );

  return (
    <div className="flex flex-col gap-6">
      {error ? <ErrorBanner message={error} onDismiss={() => setError(null)} /> : null}

      <div className="flex flex-wrap items-center gap-2">
        <div className="flex overflow-hidden rounded-pill border border-line" role="group" aria-label="Calendar view">
          {(["month", "week", "day"] as ViewMode[]).map((mode) => (
            <button
              key={mode}
              type="button"
              className={`px-4 py-1.5 text-sm font-medium capitalize ${view === mode ? "bg-accent/10 text-ink" : "bg-paper-soft text-fog hover:text-ink"}`}
              aria-pressed={view === mode}
              onClick={() => setView(mode)}
            >
              {mode}
            </button>
          ))}
        </div>
        <Button variant="secondary" onClick={() => setAnchor(addDays(anchor, view === "month" ? -30 : view === "week" ? -7 : -1))}>
          ←
        </Button>
        <Button variant="secondary" onClick={() => setAnchor(utcToday())}>
          Today
        </Button>
        <Button variant="secondary" onClick={() => setAnchor(addDays(anchor, view === "month" ? 30 : view === "week" ? 7 : 1))}>
          →
        </Button>
        <p className="ml-2 text-sm font-semibold text-ink">
          {view === "month" ? anchor.slice(0, 7) : formatLongDate(anchor)}
          <span className="ml-2 text-xs font-normal text-fog">times in {timezone}</span>
        </p>

        <div className="ml-auto">
          <Button variant="secondary" onClick={() => setShowSettings((v) => !v)}>
            {showSettings ? "Hide availability setup" : "Availability & blocks"}
          </Button>
        </div>
      </div>

      {showSettings && settings ? (
        <div className="flex flex-col gap-6 rounded-card border border-line bg-paper-soft p-6">
          <AvailabilitySettingsEditor settings={settings} onSave={saveSettings} busy={busy} />

          <div className="flex flex-col gap-3 border-t border-line pt-5">
            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">Blocked dates & slots</p>
            <div className="flex flex-wrap items-end gap-2">
              <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                Date
                <input type="date" value={blockDate} onChange={(e) => setBlockDate(e.target.value)} className={selectClasses} />
              </label>
              <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                Time (optional — one slot)
                <input type="time" step={1800} value={blockTime} onChange={(e) => setBlockTime(e.target.value)} className={selectClasses} />
              </label>
              <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
                Reason (internal)
                <input type="text" value={blockReason} maxLength={255} onChange={(e) => setBlockReason(e.target.value)} placeholder="e.g. public holiday" className={selectClasses} />
              </label>
              <Button variant="primary" disabled={busy || (!blockDate && !blockTime)} onClick={() => void createBlock()}>
                Add block
              </Button>
            </div>
            {blocks.length === 0 ? (
              <p className="text-sm text-fog">No blocks. All configured slots are bookable.</p>
            ) : (
              <ul className="flex flex-col gap-2">
                {blocks.map((b) => (
                  <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 rounded-card border border-line bg-paper p-3 text-sm">
                    <span className="text-ink">
                      {b.blockedDate ? `Whole day · ${formatLongDate(b.blockedDate)}` : `Slot · ${b.startsAt ? formatLongDate(splitSlot(b.startsAt).date) + " " + formatTime12h(splitSlot(b.startsAt).time) : ""}`}
                      {b.reason ? <span className="text-fog"> — {b.reason}</span> : null}
                    </span>
                    <Button variant="secondary" disabled={busy} onClick={() => void removeBlock(b.id)}>
                      Unblock
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      ) : null}

      {/* Calendar body */}
      <div className="flex flex-col gap-2">
        {dayList.map(({ date, meetings: dayMeetings }) => {
          const inMonth = date.slice(0, 7) === anchor.slice(0, 7);
          return (
            <div
              key={date}
              className={`rounded-card border p-3 ${
                view !== "month" || inMonth ? "border-line bg-paper-soft" : "border-line/60 bg-paper opacity-50"
              }`}
            >
              <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                {dayName(new Date(`${date}T00:00:00Z`).getUTCDay())} · {formatLongDate(date)}
                {date === utcToday() ? " · today" : ""}
              </p>
              {dayMeetings.length === 0 ? (
                <p className="mt-1 text-sm text-fog">No meetings.</p>
              ) : (
                <ul className="mt-2 flex flex-col gap-2">
                  {dayMeetings.map((m) => {
                    const isSelected = selected?.id === m.id;
                    return (
                      <li key={m.id}>
                        <button
                          type="button"
                          onClick={() => setSelected(isSelected ? null : m)}
                          aria-expanded={isSelected}
                          className={`w-full rounded-card border p-3 text-left transition-colors ${
                            isSelected ? "border-accent bg-accent/5" : "border-line bg-paper hover:border-accent/50"
                          }`}
                        >
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-semibold text-ink">
                              {formatTime12h(splitSlot(m.startsAt).time)} –{" "}
                              {formatTime12h(minutesToTime(splitSlot(m.startsAt).time, m.durationMinutes))} ·{" "}
                              {m.customer.name}
                              {m.customer.company ? ` (${m.customer.company})` : ""}
                            </span>
                            <Badge label={m.status} style={STATUS_STYLES[m.status]} />
                          </div>
                          <p className="mt-1 text-xs text-fog">
                            {m.projectType} · {m.customer.email} · {m.customer.contactNumber}
                          </p>
                        </button>
                        {isSelected ? (
                          <div className="mt-2 flex flex-col gap-3 rounded-card border border-line bg-paper p-4">
                            <dl className="grid grid-cols-2 gap-2 text-sm">
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Customer</dt>
                                <dd className="text-ink">{m.customer.name}</dd>
                              </div>
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Company</dt>
                                <dd className="text-ink">{m.customer.company || "—"}</dd>
                              </div>
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Email</dt>
                                <dd>
                                  <a href={`mailto:${m.customer.email}`} className="underline hover:text-ink">
                                    {m.customer.email}
                                  </a>
                                </dd>
                              </div>
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Contact</dt>
                                <dd>
                                  <span className="font-mono text-ink">{m.customer.contactNumber}</span>{" "}
                                  <a href={`tel:${m.customer.contactNumber}`} className="text-xs underline">
                                    Call
                                  </a>{" "}
                                  <a
                                    href={`https://wa.me/${m.customer.contactNumber.replace(/^\+/, "")}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-xs underline"
                                  >
                                    WhatsApp
                                  </a>
                                </dd>
                              </div>
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Project</dt>
                                <dd className="text-ink">{m.projectType}</dd>
                              </div>
                              <div>
                                <dt className="text-xs uppercase tracking-[0.14em] text-fog">Inquiry</dt>
                                <dd className="text-ink font-mono text-xs">#{m.inquiryId.slice(0, 8)}</dd>
                              </div>
                            </dl>
                            {m.status === "CANCELLED" && m.cancelledReason ? (
                              <p className="text-xs text-fog">Cancelled: {m.cancelledReason}</p>
                            ) : null}
                            <div className="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                              <label className="flex items-center gap-2 text-xs uppercase tracking-[0.14em] text-fog">
                                Status
                                <select
                                  value={m.status}
                                  disabled={busy}
                                  onChange={(e) => void setStatus(m, e.target.value as MeetingStatus)}
                                  className={selectClasses}
                                >
                                  {(["BOOKED", "COMPLETED", "CANCELLED", "NO_SHOW"] as MeetingStatus[]).map((s) => (
                                    <option key={s} value={s} disabled={s !== "BOOKED" && m.status === "BOOKED" ? false : m.status !== "BOOKED"}>
                                      {s}
                                    </option>
                                  ))}
                                </select>
                              </label>
                              <a href={`/admin?inquiry=${m.inquiryId}`} className="text-xs underline text-fog hover:text-ink">
                                Open inquiry
                              </a>
                              <Button
                                variant="secondary"
                                disabled={busy}
                                onClick={() => void deleteMeeting(m)}
                                className="ml-auto"
                              >
                                Delete meeting
                              </Button>
                            </div>
                          </div>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/** "15:00" + 30 → "15:30" */
function minutesToTime(hhmm: string, addMinutes: number): string {
  const [h = "0", m = "0"] = hhmm.split(":").map(Number);
  const total = (h as number) * 60 + (m as number) + addMinutes;
  const hh = String(Math.floor(total / 60) % 24).padStart(2, "0");
  const mm = String(total % 60).padStart(2, "0");
  return `${hh}:${mm}`;
}

/** Availability settings editor (working days, hours, slot length, window, lead time). */
function AvailabilitySettingsEditor({
  settings,
  onSave,
  busy,
}: {
  settings: MeetingSettings;
  onSave: (next: MeetingSettings) => Promise<void>;
  busy: boolean;
}) {
  const [draft, setDraft] = useState<MeetingSettings>(settings);
  useEffect(() => setDraft(settings), [settings]);

  const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

  return (
    <div className="flex flex-col gap-4">
      <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">Availability settings</p>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Working days">
        {DAY_LABELS.map((label, index) => {
          const active = draft.workingDays.includes(index);
          return (
            <button
              key={label}
              type="button"
              aria-pressed={active}
              className={`rounded-pill border px-3.5 py-1.5 text-sm font-medium transition-colors ${
                active ? "border-accent bg-accent/10 text-ink" : "border-line bg-paper text-fog hover:text-ink"
              }`}
              onClick={() =>
                setDraft((d) => ({
                  ...d,
                  workingDays: active
                    ? d.workingDays.filter((x) => x !== index)
                    : [...d.workingDays, index].sort((a, b) => a - b),
                }))
              }
            >
              {label}
            </button>
          );
        })}
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
          Day starts
          <input
            type="time"
            step={1800}
            value={minutesToTime("00:00", draft.dayStartMinutes)}
            onChange={(e) => {
              const [h = "0", m = "0"] = e.target.value.split(":").map(Number);
              setDraft((d) => ({ ...d, dayStartMinutes: (h as number) * 60 + (m as number) }));
            }}
            className={selectClasses}
          />
        </label>
        <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
          Day ends
          <input
            type="time"
            step={1800}
            value={minutesToTime("00:00", draft.dayEndMinutes)}
            onChange={(e) => {
              const [h = "0", m = "0"] = e.target.value.split(":").map(Number);
              setDraft((d) => ({ ...d, dayEndMinutes: (h as number) * 60 + (m as number) }));
            }}
            className={selectClasses}
          />
        </label>
        <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
          Slot length (min)
          <input
            type="number"
            min={10}
            max={240}
            step={5}
            value={draft.slotDurationMinutes}
            onChange={(e) => setDraft((d) => ({ ...d, slotDurationMinutes: Number(e.target.value) }))}
            className={selectClasses}
          />
        </label>
        <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
          Booking window (days)
          <input
            type="number"
            min={1}
            max={365}
            value={draft.bookingWindowDays}
            onChange={(e) => setDraft((d) => ({ ...d, bookingWindowDays: Number(e.target.value) }))}
            className={selectClasses}
          />
        </label>
        <label className="flex flex-col gap-1 text-xs uppercase tracking-[0.14em] text-fog">
          Min lead time (min)
          <input
            type="number"
            min={0}
            max={10080}
            step={15}
            value={draft.minLeadTimeMinutes}
            onChange={(e) => setDraft((d) => ({ ...d, minLeadTimeMinutes: Number(e.target.value) }))}
            className={selectClasses}
          />
        </label>
        <label className="flex items-center gap-2 self-end text-sm text-ink">
          <input
            type="checkbox"
            checked={draft.enabled}
            onChange={(e) => setDraft((d) => ({ ...d, enabled: e.target.checked }))}
          />
          Booking enabled
        </label>
      </div>

      <div>
        <Button variant="primary" disabled={busy} onClick={() => void onSave(draft)}>
          Save availability
        </Button>
        <p className="mt-2 text-xs text-fog">
          All times are <span className="font-mono">{draft.timezone}</span> — the server is authoritative; no local conversion is applied.
        </p>
      </div>
    </div>
  );
}
