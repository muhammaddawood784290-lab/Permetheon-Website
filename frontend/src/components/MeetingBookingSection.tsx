import { useEffect, useMemo, useState } from "react";
import {
  fetchAvailability,
  formatLongDate,
  formatTime12h,
  splitSlot,
  type AvailabilityDay,
  type AvailabilityPayload,
} from "@/lib/meetings";

/**
 * MEETING BOOKING SECTION (public form) — meetings spec §1/§11.
 * A natural extension of the V3 inquiry form: identical tokens (rounded-card,
 * border-line, paper-soft, accent), identical label/typography scale. The 8
 * canonical inquiry fields live in InquiryForm and are untouched; this section
 * only adds the optional meeting booking below them.
 *
 * Flow: "Would you like to book a meeting?" → Yes reveals date chips (OPEN
 * days only) → slot grid (AVAILABLE only) → summary → the parent form's
 * submit button books inquiry + meeting in one atomic request.
 *
 * The server is the only authority on availability: this component renders
 * exactly what GET /api/meetings/availability returns and re-fetches after
 * every booking attempt (parent triggers via `refreshKey`).
 */

export type MeetingSelection = { startsAt: string } | null;

const chipBase =
  "rounded-pill border px-3.5 py-1.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40";
const chipIdle = `${chipBase} border-line bg-paper-soft text-ink hover:border-accent/50`;
const chipActive = `${chipBase} border-accent bg-accent/10 text-ink`;

const slotBase =
  "rounded-card border px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40";
const slotFree = `${slotBase} border-line bg-paper-soft text-ink hover:border-accent/50`;
const slotActive = `${slotBase} border-accent bg-accent/10 text-ink`;

const DAY_CHIP_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

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
  const [selectedDate, setSelectedDate] = useState<string | null>(null);

  // Availability loads only when the customer opts in, and refreshes whenever
  // the parent bumps refreshKey (e.g. after a 409 conflict or a booking).
  useEffect(() => {
    if (!optIn) return;
    const controller = new AbortController();
    setLoading(true);
    setLoadFailed(false);
    void fetchAvailability(controller.signal).then((data) => {
      setAvailability(data);
      setLoadFailed(data === null);
      setLoading(false);
    });
    return () => controller.abort();
  }, [optIn, refreshKey]);

  // Bookable days: OPEN (has ≥1 AVAILABLE slot). Dates the calendar exposes
  // are already future/working-day/unblocked — the server filtered them.
  const bookableDays = useMemo<AvailabilityDay[]>(
    () => availability?.days.filter((d) => d.status === "OPEN") ?? [],
    [availability],
  );

  // Keep the selected date coherent when the calendar refreshes.
  useEffect(() => {
    if (selectedDate && !bookableDays.some((d) => d.date === selectedDate)) {
      setSelectedDate(bookableDays[0]?.date ?? null);
    } else if (!selectedDate && bookableDays.length > 0) {
      setSelectedDate(bookableDays[0].date);
    }
  }, [bookableDays, selectedDate]);

  // Drop a stale slot selection if it vanished from the fresh calendar.
  useEffect(() => {
    if (!selection) return;
    const { date, time } = splitSlot(selection.startsAt);
    const day = bookableDays.find((d) => d.date === date);
    const stillFree = day?.slots.some((s) => splitSlot(s.startsAt).time === time && s.status === "AVAILABLE");
    if (!stillFree) onSelect(null);
  }, [bookableDays, selection, onSelect]);

  const day = bookableDays.find((d) => d.date === selectedDate) ?? null;
  const availableSlots = day?.slots.filter((s) => s.status === "AVAILABLE") ?? [];

  function selectSlot(startsAt: string) {
    onSelect({ startsAt });
  }

  return (
    <section
      aria-label="Optional meeting booking"
      className="flex flex-col gap-4 rounded-card border border-line bg-paper-soft p-6"
    >
      <div>
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">Meeting</p>
        <p className="mt-2 text-sm text-ink">Would you like to book a meeting with us?</p>
        <p className="mt-1 text-xs text-fog">
          Optional — pick a 30-minute slot and we&apos;ll confirm it. You can also submit without one.
        </p>
      </div>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Book a meeting?">
        <button
          type="button"
          className={optIn ? chipActive : chipIdle}
          aria-pressed={optIn}
          onClick={() => setOptIn(true)}
        >
          Yes, book a meeting
        </button>
        <button
          type="button"
          className={!optIn ? chipActive : chipIdle}
          aria-pressed={!optIn}
          onClick={() => {
            setOptIn(false);
            onSelect(null);
            setSelectedDate(null);
          }}
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
            We couldn&apos;t load the calendar right now — you can still submit your inquiry and
            we&apos;ll arrange a time by email.
          </p>
        ) : !availability.enabled ? (
          <p className="text-sm text-fog">
            Online booking is currently paused. Submit your inquiry and we&apos;ll propose a time.
          </p>
        ) : bookableDays.length === 0 ? (
          <p className="text-sm text-fog">
            No open slots in the next {availability.days.length} days. Submit your inquiry and
            we&apos;ll arrange a time by email.
          </p>
        ) : (
          <div className="flex flex-col gap-4">
            {/* Step 1 — date */}
            <fieldset className="flex flex-col gap-2" disabled={disabled}>
              <legend className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                1 · Choose a date <span className="normal-case tracking-normal text-fog">({availability.timezone})</span>
              </legend>
              <div className="flex flex-wrap gap-2">
                {bookableDays.map((d) => (
                  <button
                    key={d.date}
                    type="button"
                    className={selectedDate === d.date ? chipActive : chipIdle}
                    aria-pressed={selectedDate === d.date}
                    onClick={() => setSelectedDate(d.date)}
                  >
                    {DAY_CHIP_LABELS[d.dayOfWeek]} · {d.date.slice(8)}·{d.date.slice(5, 7)}
                  </button>
                ))}
              </div>
            </fieldset>

            {/* Step 2 — time */}
            {day ? (
              <fieldset className="flex flex-col gap-2" disabled={disabled}>
                <legend className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                  2 · Choose an available time <span className="normal-case tracking-normal text-fog">(UTC)</span>
                </legend>
                {availableSlots.length === 0 ? (
                  <p className="text-sm text-fog">No available times left on this date.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {availableSlots.map((s) => {
                      const time = splitSlot(s.startsAt).time;
                      const active = selection?.startsAt === s.startsAt;
                      return (
                        <button
                          key={s.startsAt}
                          type="button"
                          className={active ? slotActive : slotFree}
                          aria-pressed={active}
                          onClick={() => selectSlot(s.startsAt)}
                        >
                          {formatTime12h(time)}
                        </button>
                      );
                    })}
                  </div>
                )}
              </fieldset>
            ) : null}

            {/* Step 3 — summary */}
            {selection ? (
              <div className="rounded-card border border-accent/40 bg-paper p-4" aria-live="polite">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-fog">
                  3 · Your meeting
                </p>
                <p className="mt-1.5 font-display text-lg font-semibold text-ink">
                  {formatLongDate(splitSlot(selection.startsAt).date)}
                </p>
                <p className="text-sm text-ink">
                  {formatTime12h(splitSlot(selection.startsAt).time)} –{" "}
                  {formatTime12h(
                    splitSlot(
                      bookableDays
                        .find((d) => d.date === splitSlot(selection.startsAt).date)
                        ?.slots.find((s) => s.startsAt === selection.startsAt)?.endsAt ??
                      selection.startsAt,
                    ).time,
                  )}{" "}
                  · {availability.slotDurationMinutes} minutes
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
