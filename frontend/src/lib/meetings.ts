/**
 * MEETINGS CLIENT — typed helpers over the meetings API (meetings spec §10).
 * The server is the only authority on availability; this module just shapes
 * the requests and renders what it returns.
 *
 * TIME STANDARD: UTC only. All slot values are ISO 8601 UTC instants
 * ('2026-09-30T10:00:00Z'). Formatting is purely textual string surgery on
 * the UTC values the backend returned — no Date parsing, no Intl, no
 * browser-local timezone is ever consulted, and nothing is converted. The UI
 * always labels times "UTC".
 */

import { apiUrl } from "@/lib/api";

export type MeetingSlotStatus = "AVAILABLE" | "BOOKED" | "BLOCKED" | "PAST";
export type MeetingDayStatus = "OPEN" | "FULL" | "BLOCKED" | "NON_WORKING";
export type MeetingStatus = "BOOKED" | "COMPLETED" | "CANCELLED" | "NO_SHOW";

export type AvailabilitySlot = {
  startsAt: string; // ISO 8601 UTC: "2026-09-30T10:00:00Z"
  endsAt: string;
  status: MeetingSlotStatus;
};

export type AvailabilityDay = {
  date: string; // "YYYY-MM-DD" (UTC calendar date)
  dayOfWeek: number; // 0=Sun..6=Sat (UTC)
  status: MeetingDayStatus;
  slots: AvailabilitySlot[];
};

export type AvailabilityPayload = {
  timezone: string; // always "UTC"
  slotDurationMinutes: number;
  enabled: boolean;
  days: AvailabilityDay[];
};

export type MeetingSettings = {
  workingDays: number[];
  dayStartMinutes: number;
  dayEndMinutes: number;
  slotDurationMinutes: number;
  bookingWindowDays: number;
  minLeadTimeMinutes: number;
  enabled: boolean;
  timezone: string; // always "UTC"
};

export type MeetingBlock = {
  id: string;
  blockedDate: string | null;
  startsAt: string | null;
  reason: string;
  createdAt: string | null;
};

/** GET /api/meetings/availability — the public calendar. */
export async function fetchAvailability(signal?: AbortSignal): Promise<AvailabilityPayload | null> {
  try {
    const res = await fetch(apiUrl("/api/meetings/availability"), { cache: "no-store", signal });
    if (!res.ok) return null;
    const body = (await res.json()) as { data?: { availability?: AvailabilityPayload } };
    return body?.data?.availability ?? null;
  } catch {
    return null;
  }
}

/** "2026-09-30T10:00:00Z" → { date: "2026-09-30", time: "10:00" } (UTC text). */
export function splitSlot(slot: string): { date: string; time: string } {
  const [date = "", rest = ""] = slot.split("T");
  return { date, time: rest.slice(0, 5) };
}

/** "10:00" → "10:00 UTC" — neutral UTC label, no conversion. */
export function formatTime12h(hhmm: string): string {
  return `${hhmm} UTC`;
}

const DOW_LONG = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const MONTH_SHORT = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

/** "2026-09-30" → "30 September 2026" */
export function formatLongDate(date: string): string {
  const [y = "", m = "", d = ""] = date.split("-");
  return `${Number(d)} ${MONTH_LONG[Number(m) - 1] ?? m} ${y}`;
}

const MONTH_LONG = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

/** 0..6 → "Wednesday" */
export function dayName(dow: number): string {
  return DOW_LONG[dow] ?? "";
}

/** "2026-09-30" → "Wed 30 Sep" (computed from the date text, UTC-safe). */
export function formatShortDate(date: string): string {
  const [y = "", m = "", d = ""] = date.split("-");
  void y;
  const dow = new Date(`${date}T00:00:00Z`).getUTCDay();
  return `${DOW_LONG[dow]?.slice(0, 3)} ${Number(d)} ${MONTH_SHORT[Number(m) - 1] ?? m}`;
}

/** CRSF header helper shared by admin mutating calls. */
export function csrfHeaders(): Record<string, string> {
  const csrf = document.cookie.match(/(?:^|;\s*)admin_csrf=([^;]+)/)?.[1] ?? "";
  return { "x-csrf-token": decodeURIComponent(csrf) };
}
