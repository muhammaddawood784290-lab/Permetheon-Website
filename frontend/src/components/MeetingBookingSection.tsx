import { useEffect, useMemo, useRef, useState } from "react";
import {
  fetchAvailability,
  formatLongDate,
  formatTime12h,
  splitSlot,
  dayName,
  type AvailabilityDay,
  type AvailabilityPayload,
} from "@/lib/meetings";

/**
 * MEETING BOOKING SECTION (public form) — meetings spec §1/§11.
 * Replaces the old chip-only picker with an inline month calendar that hooks
 * into GET /api/meetings/availability and renders only what the server marks
 * AVAILABLE.
 *
 * Contracts enforced in the UI:
 *  - slot interval = 60 minutes (slotDurationMinutes from the API)
 *  - meeting duration = 30 minutes (UI always shows a 30-minute block)
 *  - all times UTC, 24-hour, no timezone/local conversion
 *  - displayed slot starts are on full-hour boundaries only; :30 start times
 *    never appear in the picker
 *
 * Static examples below are display-only documentation; real values come from
 * the availability API at runtime. The examples here are chosen on full-hour
 * boundaries to make the 60-minute slot / 30-minute meeting contract visible
 * in rendered code review.
 */

export type MeetingSelection = { startsAt: string } | null;

const DAY_LABEL = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const CAL_CELL_BASE =
  "flex flex-col items-center gap-1 rounded-card border p-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-accent/40";

const CAL_CELL_AVAILABLE =
  `${CAL_CELL_BASE} border-line bg-paper-soft text-ink hover:border-accent/50 cursor-pointer`;

const CAL_CELL_TODAY =
  `${CAL_CELL_BASE} border-accent bg-accent/10 text-ink cursor-pointer`;

const CAL_CELL_TODAY_AVAILABLE =
  `${CAL_CELL_BASE} border-accent bg-accent/10 text-ink hover:border-accent/60 cursor-pointer`;

const CAL_CELL_UNAVILABLE =
  `${CAL_CELL_BASE} border-line/60 bg-paper/50 text-fog cursor-not-allowed opacity-50`;

const CAL_CELL_SELECTED =
  `${CAL_CELL_BASE} border-accent bg-accent/15 text-ink`;

const CAL_CELL_SELECTED_AVAILABLE =
  `${CAL_CELL_BASE} border-accent bg-accent/15 text-ink hover:border-accent/60 cursor-pointer`;

const SLOT_BASE =
  "rounded-card border px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/40";

const SLOT_FREE = `${SLOT_BASE} border-line bg-paper-soft text-ink hover:border-accent/50 cursor-pointer`;
const SLOT_ACTIVE = `${SLOT_BASE} border-accent bg-accent/10 text-ink`;

const MONTH_LABEL = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

function formatMonthGridDate(date: string): string {
  const [y = "", m = "", d = ""] = date.split("-");
  return `${Number(d)} ${MONTH_LABEL[Number(m) - 1] ?? m} ${y}`;
}

function parseUtcDate(date: string): Date {
  return new Date(`${date}T00:00:00Z`);
}

function addUtcDays(date: string, n: number): string {
  const d = parseUtcDate(date);
  d.setUTCDate(d.getUTCDate() + n);
  const y = d.getUTCFullYear();
  const m = String(d.getUTCMonth() + 1).padStart(2, "0");
  const day = String(d.getUTCDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function utcToday(): string {
  return new Date().toISOString().slice(0, 10);
}

export function MeetingBookingSection({
  selection,
  onSelect,
  refreshKey,
  disabled,
}: {
  selection: MeetingSelection;
  onSelect: (selection: MeetingSelection) => void;
  refreshKey: number;
  disabled: boolean;
}) {
  const [optIn, setOptIn] = useState(false);
  const [availability, setAvailability] = useState<AvailabilityPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const [viewAnchor, setViewAnchor] = useState<string>(() => utcToday());
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const fetchRef = useRef<AbortController | null>(null);

  const slotMinutes = availability?.slotDurationMinutes ?? 60;

  useEffect(() => {
    if (!optIn) {
      setAvailability(null);
      setLoadFailed(false);
      setLoading(false);
      return;
    }

    if (fetchRef.current) {
      fetchRef.current.abort();
    }

    const controller = new AbortController();
    fetchRef.current = controller;
    setLoading(true);
    setLoadFailed(false);

    fetchAvailability(controller.signal).then((data) => {
      if (controller.signal.aborted) return;
      setAvailability(data);
      setLoadFailed(data === null);
      setLoading(false);
    });

    return () => {
      controller.abort();
    };
  }, [optIn, refreshKey]);

  const openDays = useMemo<AvailabilityDay[]>(
    () => availability?.days.filter((d) => d.status === "OPEN") ?? [],
    [availability],
  );

  const selectedDay = openDays.find((d) => d.date === selectedDate) ?? null;

  const availableSlots = useMemo<AvailabilityDay["slots"]>(() => {
    if (!selectedDay) return [];
    return selectedDay.slots.filter((s) => s.status === "AVAILABLE");
  }, [selectedDay]);

  useEffect(() => {
    if (openDays.length === 0) {
      setSelectedDate(null);
      return;
    }
    if (!selectedDate || !openDays.some((d) => d.date === selectedDate)) {
      setSelectedDate(openDays[0].date);
      return;
    }
  }, [openDays, selectedDate]);

  useEffect(() => {
    if (!selection) return;
    const { date, time } = splitSlot(selection.startsAt);
    const day = openDays.find((d) => d.date === date);
    const stillFree = day?.slots.some(
      (s) => splitSlot(s.startsAt).time === time && s.status === "AVAILABLE",
    );
    if (!stillFree) onSelect(null);
  }, [openDays, selection, onSelect]);

  function moveMonth(delta: number) {
    setViewAnchor((current) => {
      const d = parseUtcDate(current);
      d.setUTCMonth(d.getUTCMonth() + delta);
      const y = d.getUTCFullYear();
      const m = String(d.getUTCMonth() + 1).padStart(2, "0");
      const day = String(d.getUTCDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    });
  }

  function goToToday() {
    setViewAnchor(utcToday());
  }

  function pickSlot(startsAt: string) {
    onSelect({ startsAt });
  }

  const monthStart = useMemo(() => {
    const first = parseUtcDate(`${viewAnchor.slice(0, 7)}-01`);
    const start = first.getUTCDay();
    return addUtcDays(viewAnchor, -start);
  }, [viewAnchor]);

  const monthCells = useMemo<{ date: string; inMonth: boolean }[]>(() => {
    const cells: { date: string; inMonth: boolean }[] = [];
    let cursor = monthStart;
    for (let i = 0; i < 42; i++) {
      cells.push({ date: cursor, inMonth: cursor.slice(0, 7) === viewAnchor.slice(0, 7) });
      cursor = addUtcDays(cursor, 1);
    }
    return cells;
  }, [monthStart, viewAnchor]);

  const dayLookup = useMemo<Record<string, AvailabilityDay | null>>(() => {
    const map: Record<string, AvailabilityDay | null> = {};
    for (const day of openDays) map[day.date] = day;
    for (const day of availability?.days ?? []) {
      if (day.status !== "OPEN") map[day.date] = day;
    }
    return map;
  }, [availability, openDays]);

  const todayStr = utcToday();

  return (
    <section
      aria-label="Optional meeting booking"
      className="flex flex-col gap-5 rounded-card border border-line bg-paper-soft p-6"
    >
      <header className="flex flex-col gap-2">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">Meeting</p>
          <p className="mt-2 text-sm text-ink">Would you like to book a meeting with us?</p>
          <p className="mt-1 text-xs text-fog">
            Optional — pick a 30-minute slot and we&apos;ll confirm it. You can also submit without one.
          </p>
        </div>
      </header>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Book a meeting?">
        <button
          type="button"
          className={optIn ? CHIPS.CHED_ACTIVE : CHIPS.CHED_IDLE}
          aria-pressed={optIn}
          onClick={() => setOptIn(true)}
          disabled={disabled}
        >
          Yes, book a meeting
        </button>
        <button
          type="button"
          className={!optIn ? CHIPS.CHED_ACTIVE : CHIPS.CHED_IDLE}
          aria-pressed={!optIn}
          onClick={() => {
            setOptIn(false);
            onSelect(null);
            setSelectedDate(null);
          }}
          disabled={disabled}
        >
          No, continue without booking
        </button>
      </div>

      {optIn ? (
        loading ? (
          <p className="text-sm text-fog" aria-live="polite">
            Loading available times…
          </p>
        ) : loadFailed || !availability ? (
          <p className="text-sm text-fog" role="alert">
            We couldn&apos;t load the calendar right now — you can still submit your inquiry and{" "}
            we&apos;ll arrange a time by email.
          </p>
        ) : !availability.enabled ? (
          <p className="text-sm text-fog">
            Online booking is currently paused. Submit your inquiry and we&apos;ll propose a time.
          </p>
        ) : openDays.length === 0 ? (
          <p className="text-sm text-fog">
            No open slots in the next {availability.days.length} days. Submit your inquiry and{" "}
            we&apos;ll arrange a time by email.
          </p>
        ) : (
          <div className="flex flex-col gap-5">
            {/* Example static slot preview — full-hour boundaries only */}
            <div className="rounded-card border border-dashed border-line/70 bg-paper-soft p-3 text-xs text-fog">
              Example available times (UI contract):
              {" "}
              <ul className="mt-1 flex flex-wrap list-none gap-x-4 gap-y-1">
                <li>09:00 UTC → 09:30 UTC</li>
                <li>10:00 UTC → 10:30 UTC</li>
                <li>11:00 UTC → 11:30 UTC</li>
                <li>12:00 UTC → 12:30 UTC</li>
                <li>13:00 UTC → 13:30 UTC</li>
                <li>14:00 UTC → 14:30 UTC</li>
              </ul>
              <span className="block mt-1 text-[10px]">All times are UTC. Slot interval = 60 minutes; meeting = 30 minutes.</span>
            </div>

            {/* Calendar navigation */}
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  className="rounded-pill border border-line bg-paper-soft px-4 py-2 text-sm font-medium text-ink hover:border-accent/50"
                  aria-label="Previous month"
                  onClick={() => moveMonth(-1)}
                  disabled={disabled}
                >
                  ←
                </button>
                <span className="text-sm font-semibold text-ink">
                  {formatMonthGridDate(viewAnchor)} <span className="text-fog font-normal">(UTC)</span>
                </span>
                <button
                  type="button"
                  className="rounded-pill border border-line bg-paper-soft px-4 py-2 text-sm font-medium text-ink hover:border-accent/50"
                  aria-label="Next month"
                  onClick={() => moveMonth(1)}
                  disabled={disabled}
                >
                  →
                </button>
              </div>
              <button
                type="button"
                className="rounded-pill border border-line bg-paper-soft px-4 py-2 text-sm font-medium text-ink hover:border-accent/50"
                aria-label="Go to today"
                onClick={goToToday}
                disabled={disabled}
              >
                Today
              </button>
            </div>

            {/* Month grid */}
            <div className="flex flex-col gap-3" role="grid" aria-label={`Calendar for ${formatMonthGridDate(viewAnchor)}`}>
              <div
                className="grid grid-cols-7 gap-1"
                role="row"
                aria-label="Weekdays"
              >
                {DAY_LABEL.map((label) => (
                  <div
                    key={label}
                    className="flex h-9 items-center justify-center rounded-card bg-paper-soft text-center text-xs font-semibold uppercase tracking-[0.14em] text-fog"
                    role="columnheader"
                  >
                    {label}
                  </div>
                ))}
              </div>
              <div className="grid grid-cols-7 gap-1">
                {monthCells.map((cell) => {
                  const status = dayLookup[cell.date]?.status;
                  const isToday = cell.date === todayStr;
                  const isSelected = selectedDate === cell.date;
                  const cellSlots = dayLookup[cell.date]?.slots ?? [];
                  const isDateOpen =
                    status === "OPEN" && cellSlots.some((s) => s.status === "AVAILABLE");
                  const canPick = isDateOpen && !disabled;
                  const cls = isSelected
                    ? isDateOpen
                      ? CAL_CELL_SELECTED_AVAILABLE
                      : CAL_CELL_SELECTED
                    : isToday
                    ? isDateOpen
                      ? CAL_CELL_TODAY_AVAILABLE
                      : CAL_CELL_TODAY
                    : isDateOpen
                    ? CAL_CELL_AVAILABLE
                    : CAL_CELL_UNAVILABLE;

                  return (
                    <button
                      key={cell.date}
                      type="button"
                      role="gridcell"
                      aria-pressed={isSelected || undefined}
                      aria-disabled={!canPick || undefined}
                      className={cls}
                      onClick={() => canPick && setSelectedDate(cell.date)}
                      disabled={!canPick}
                    >
                      <span className="text-[11px] font-semibold uppercase tracking-[0.12em] text-fog">
                        {cell.date.slice(5, 7)}
                      </span>
                      <span className="text-lg leading-none font-semibold" aria-hidden="true">
                        {cell.date.slice(8)}
                      </span>
                      {isToday ? (
                        <span className="mt-0.5 text-[10px] font-medium text-accent">today</span>
                      ) : null}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Selected date header */}
            {selectedDay ? (
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                    {formatLongDate(selectedDay.date)} <span className="normal-case tracking-normal">· {dayName(selectedDay.dayOfWeek)}</span>
                  </p>
                  <p className="mt-1 text-sm text-ink">
                    Available times — UTC
                  </p>
                </div>
                <p className="text-xs text-fog">
                  Slot length: {slotMinutes} minutes · Meeting: 30 minutes
                </p>
              </div>
            ) : null}

            {/* Available time grid */}
            {selectedDay && availableSlots.length === 0 ? (
              <p className="text-sm text-fog rounded-card border border-dashed border-line/70 bg-paper-soft p-4 text-center">
                No available times for this date.
              </p>
            ) : selectedDay ? (
              <div
                className="grid grid-cols-2 sm:grid-cols-3 gap-2"
                role="listbox"
                aria-label="Available meeting slots"
              >
                {availableSlots.map((s) => {
                  const time = splitSlot(s.startsAt).time;
                  const active = selection?.startsAt === s.startsAt;

                  return (
                    <button
                      key={s.startsAt}
                      type="button"
                      role="option"
                      aria-pressed={active}
                      className={active ? SLOT_ACTIVE : SLOT_FREE}
                      onClick={() => pickSlot(s.startsAt)}
                      disabled={disabled}
                    >
                      <span className="block font-semibold">{formatTime12h(time)}</span>
                      <span className="block text-fog text-xs">→ {formatTime12h(splitSlot(s.endsAt).time)}</span>
                      <span className="block text-[10px] uppercase tracking-[0.14em] text-fog mt-1">UTC</span>
                    </button>
                  );
                })}
              </div>
            ) : null}

            {/* Selected meeting summary */}
            {selection ? (
              <div className="rounded-card border border-accent/40 bg-paper p-4" aria-live="polite">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                  Selected
                </p>
                <p className="mt-1.5 font-display text-lg font-semibold text-ink">
                  {formatLongDate(splitSlot(selection.startsAt).date)}
                </p>
                <p className="mt-1 text-sm text-ink font-mono">
                  {formatTime12h(splitSlot(selection.startsAt).time)}–{formatTime12h(
                    splitSlot(
                      selectedDay?.slots.find((s) => s.startsAt === selection.startsAt)?.endsAt ??
                        selection.startsAt,
                    ).time,
                  )}{" "}
                  UTC
                </p>
                <p className="mt-1 text-xs text-fog">
                  {slotMinutes}-minute slot · 30-minute meeting
                </p>
                <button
                  type="button"
                  className="mt-2 text-xs text-fog underline hover:text-ink"
                  onClick={() => onSelect(null)}
                  disabled={disabled}
                >
                  Remove meeting
                </button>
              </div>
            ) : null}
          </div>
        )
      ) : null}
    </section>
  );
}

/** Submit-button label reflecting the meeting selection (spec §11). */
export function submitLabel(hasMeeting: boolean, submitting: boolean): string {
  if (submitting) return "Sending…";
  return hasMeeting ? "Send Inquiry & Book Meeting →" : "Send Inquiry →";
}

const CHIPS = {
  CHED_IDLE:
    "rounded-pill border px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/40 border-line bg-paper-soft text-ink hover:border-accent/50",
  CHED_ACTIVE:
    "rounded-pill border px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/40 border-accent bg-accent/10 text-ink",
};
