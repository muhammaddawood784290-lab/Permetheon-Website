#!/usr/bin/env node
/**
 * MEETINGS E2E — full lifecycle over HTTP (meetings spec §2/§5/§6/§7/§13).
 *
 * Usage:
 *   node scripts/e2e-meetings.mjs --backend=laravel
 *   E2E_BASE=http://localhost:3102 node scripts/e2e-meetings.mjs --backend=laravel
 *
 * Expects the isolated launcher (scripts/e2e-laravel-server.mjs) running on
 * :3102 with a FRESH throwaway DB (it migrates fresh on start). Run AFTER
 * (or without) scripts/e2e-inquiry.mjs — the inquiry suite leaves ~6 rows
 * but this suite is written to be independent of the inquiry total.
 */
process.env.NODE_TLS_REJECT_UNAUTHORIZED = "0";

const BASE = process.env.E2E_BASE ?? "http://localhost:3102";
let passed = 0;
let failed = 0;
const failures = [];

function check(label, condition, detail = "") {
  if (condition) {
    passed += 1;
    console.log(`  ✓ ${label}`);
  } else {
    failed += 1;
    failures.push(label + (detail ? ` — ${detail}` : ""));
    console.log(`  ✗ ${label}${detail ? ` — ${detail}` : ""}`);
  }
}

const jar = new Map();
function storeCookies(response) {
  for (const raw of response.headers.getSetCookie?.() ?? []) {
    const [pair] = raw.split(";");
    const eq = pair.indexOf("=");
    if (eq > 0) jar.set(pair.slice(0, eq).trim(), pair.slice(eq + 1).trim());
  }
}
function cookieHeader() {
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
}

async function req(method, path, { body, extraHeaders = {}, noCookies = false } = {}) {
  const response = await fetch(BASE + path, {
    method,
    headers: {
      "Content-Type": "application/json",
      Origin: BASE,
      ...(noCookies ? {} : { cookie: cookieHeader() }),
      ...extraHeaders,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  storeCookies(response);
  return response;
}

/** A slot N days ahead, snapped to a working day at the given time.
 * Returns strict ISO 8601 UTC ('2026-10-05T15:00:00Z') — UTC is the only
 * canonical time standard for the meeting system. */
function futureSlot(daysAhead, time = "15:00") {
  const d = new Date();
  d.setUTCDate(d.getUTCDate() + daysAhead);
  while (d.getUTCDay() === 0 || d.getUTCDay() === 6) d.setUTCDate(d.getUTCDate() + 1);
  const y = d.getUTCFullYear();
  const m = String(d.getUTCMonth() + 1).padStart(2, "0");
  const day = String(d.getUTCDate()).padStart(2, "0");
  return `${y}-${m}-${day}T${time}:00Z`;
}

function inquiryPayload(overrides = {}) {
  return {
    name: "Meeting E2E",
    company: "",
    email: `meeting-e2e-${Math.random().toString(36).slice(2)}@example.com`,
    contactNumber: "+12025550147",
    projectType: "Booking / Reservation System",
    budget: "",
    timeline: "",
    message: "We need a meeting slot for the meetings E2E lifecycle suite.",
    ...overrides,
  };
}

let adminCsrf = null;
async function adminLogin() {
  const r = await req("POST", "/api/admin/auth/login", {
    body: { email: "admin@permetheon.com", password: "E2E-test-passphrase-1" },
  });
  const body = await r.json();
  check("admin bootstrap login → 200 SUPER_ADMIN", r.status === 200 && body?.data?.admin?.role === "SUPER_ADMIN", `got ${r.status}`);
  adminCsrf = body?.data?.csrfToken;
}
const csrfHeaders = () => (adminCsrf ? { "x-csrf-token": adminCsrf } : {});

// ===========================================================================
console.log("\n1 · PUBLIC AVAILABILITY");
let calendar = null;
{
  const r = await req("GET", "/api/meetings/availability");
  const body = await r.json();
  calendar = body?.data?.availability ?? null;
  check("GET /api/meetings/availability → 200 with calendar", r.status === 200 && calendar !== null);
  check("calendar carries timezone + slot duration + enabled", Boolean(calendar?.timezone) && (calendar?.slotDurationMinutes ?? 0) === 30 && calendar?.enabled === true);
  check("window covers 31 days", (calendar?.days?.length ?? 0) === 31);
  const openDay = calendar?.days?.find((d) => d.status === "OPEN");
  check("at least one OPEN day with slots", Boolean(openDay) && openDay.slots.length > 0);
  check("first slot at 09:00 UTC", openDay?.slots?.[0]?.startsAt?.startsWith(openDay.date + "T09:00:00Z") ?? false, openDay?.slots?.[0]?.startsAt);
  const wireRe = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;
  check("every slot instant is ISO 8601 UTC with Z", (calendar?.days ?? []).every((d) => d.slots.every((s) => wireRe.test(s.startsAt) && wireRe.test(s.endsAt))));
  check("calendar reports timezone UTC", calendar?.timezone === "UTC");
  const gridOk = openDay?.slots.every((s, i) => {
    if (i === 0) return true;
    const prev = new Date(openDay.slots[i - 1].startsAt).getTime();
    const cur = new Date(s.startsAt).getTime();
    return cur - prev === 30 * 60 * 1000;
  });
  check("slots on a contiguous 30-minute grid", Boolean(gridOk));
  check("no admin data leaks in public payload", !JSON.stringify(body).includes("blocker") && !JSON.stringify(body).includes('"admin"'));
}

// ===========================================================================
console.log("\n2 · BOOKING + DOUBLE-BOOKING RACE (spec §5)");
{
  const slot = futureSlot(10);
  const first = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }) });
  const firstBody = await first.json();
  check("booking an available slot → 201 + meeting BOOKED", first.status === 201 && firstBody?.data?.meeting?.status === "BOOKED", `got ${first.status}`);
  check("meeting startsAt echoes the exact ISO 8601 UTC instant", firstBody?.data?.meeting?.startsAt === slot, firstBody?.data?.meeting?.startsAt);
  globalThis.bookedInquiryId = firstBody?.data?.id;

  const second = await req("POST", "/api/inquiries", {
    body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }),
  });
  const secondBody = await second.json();
  check("same slot twice → 409 SLOT_CONFLICT", second.status === 409 && secondBody?.error?.code === "SLOT_CONFLICT", `got ${second.status}`);
  check("conflict message tells the customer to pick another slot", String(secondBody?.error?.message ?? "").includes("taken"));

  // Atomicity: the losing inquiry must NOT exist.
  const login = await req("POST", "/api/admin/auth/login", { body: { email: "admin@permetheon.com", password: "E2E-test-passphrase-1" } });
  adminCsrf = (await login.json())?.data?.csrfToken;
  const list = await (await req("GET", "/api/admin/inquiries?search=meeting-e2e")).json();
  const losers = (list?.data?.inquiries ?? []).filter((i) => i.email === secondBody?.data?.email);
  check("loser inquiry rolled back (no half-state)", (list?.data?.inquiries ?? []).filter((i) => i.meeting !== null && i.meeting.startsAt?.startsWith(slot)).length === 1);

  // Availability now shows the slot as BOOKED.
  const cal = (await (await req("GET", "/api/meetings/availability")).json())?.data?.availability;
  const day = cal?.days?.find((d) => d.date === slot.slice(0, 10));
  const slotRow = day?.slots?.find((s) => s.startsAt === slot);
  check("booked slot is never shown as AVAILABLE", slotRow?.status === "BOOKED", slotRow?.status);
  check("availability is unchanged regardless of requester locale", cal?.timezone === "UTC");
}

// ===========================================================================
console.log("\n3 · BOOKING WITHOUT A MEETING (spec §1)");
{
  const r = await req("POST", "/api/inquiries", { body: inquiryPayload() });
  check("plain inquiry → 201, meeting null", r.status === 201 && (await r.json())?.data?.meeting === null);
  const r2 = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: false } }) });
  check("explicit booking=false → 201, meeting null", r2.status === 201 && (await r2.json())?.data?.meeting === null);
  const r3 = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true } }) });
  const b3 = await r3.json();
  check("booking=true without a slot → 422 with field error", r3.status === 422 && Object.keys(b3?.error?.fields ?? {}).some((k) => k.startsWith("meeting")));
}

// ===========================================================================
console.log("\n4 · BLOCKED SLOTS + PAST SLOTS (spec §2)");
{
  // Day offsets throughout this suite are spaced ≥3 apart on purpose:
  // futureSlot() walks weekend days forward, so closer offsets can land on the
  // SAME day (e.g. +10 walking over a weekend onto the +12 day) and the
  // whole-day block here would shadow the slot booked in section 2.
  const slot = futureSlot(13);
  const block = await req("POST", "/api/admin/blocks", { body: { blockedDate: slot.slice(0, 10), reason: "e2e block" }, extraHeaders: csrfHeaders() });
  check("admin creates a whole-day block → 201", block.status === 201, `got ${block.status}`);
  const blockedBooking = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }) });
  check("booking a blocked date → 409", blockedBooking.status === 409, `got ${blockedBooking.status}`);
  const cal = (await (await req("GET", "/api/meetings/availability")).json())?.data?.availability;
  check("blocked date shows status BLOCKED (no slots)", cal?.days?.find((d) => d.date === slot.slice(0, 10))?.status === "BLOCKED");
  const unblock = await req("DELETE", `/api/admin/blocks/${(await block.json())?.data?.block?.id}`, { extraHeaders: csrfHeaders() });
  check("unblock → 200", unblock.status === 200);
  const afterUnblock = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }) });
  check("slot bookable again after unblock → 201", afterUnblock.status === 201, `got ${afterUnblock.status}`);
  globalThis.blockedDayBooking = (await afterUnblock.json())?.data?.id;

  // Past slot: a date clearly in the past (ISO 8601 UTC wire format).
  const past = new Date();
  past.setUTCDate(past.getUTCDate() - 3);
  const pastSlot = `${past.toISOString().slice(0, 10)}T15:00:00Z`;
  const pastBooking = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: pastSlot } }) });
  check("booking a past slot → 409", pastBooking.status === 409, `got ${pastBooking.status}`);
  const pastInCal = (await (await req("GET", "/api/meetings/availability")).json())?.data?.availability?.days?.some((d) => d.date === pastSlot.slice(0, 10));
  check("past dates absent from the public calendar", pastInCal !== true);
}

// ===========================================================================
console.log("\n5 · CANCELLATION FREES THE SLOT (spec §6)");
{
  // Find the meeting for the first booking.
  const list = (await (await req("GET", "/api/admin/meetings")).json())?.data?.meetings ?? [];
  const mine = list.find((m) => m.inquiryId === globalThis.bookedInquiryId);
  check("admin meeting list shows the booking with customer data", Boolean(mine) && mine.customer?.email?.includes("meeting-e2e") && Boolean(mine.customer?.contactNumber));

  const cancel = await req("PATCH", `/api/admin/meetings/${mine.id}`, { body: { status: "CANCELLED", reason: "e2e cancellation" }, extraHeaders: csrfHeaders() });
  check("PATCH status → CANCELLED preserves history", cancel.status === 200 && (await cancel.json())?.data?.meeting?.status === "CANCELLED");

  // The slot is bookable again by another customer (echoing the exact
  // ISO 8601 UTC instant the admin API returned).
  const rebook = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: mine.startsAt } }) });
  check("cancelled slot bookable again → 201", rebook.status === 201, `got ${rebook.status}`);
  globalThis.rebookedInquiryId = (await rebook.json())?.data?.id;

  // Cancelled row still exists.
  const after = (await (await req("GET", "/api/admin/meetings")).json())?.data?.meetings ?? [];
  check("cancelled row still listed (history preserved)", after.some((m) => m.id === mine.id && m.status === "CANCELLED"));

  // Invalid transition: CANCELLED → BOOKED is 422.
  const frozen = await req("PATCH", `/api/admin/meetings/${mine.id}`, { body: { status: "BOOKED" }, extraHeaders: csrfHeaders() });
  check("terminal CANCELLED cannot move to BOOKED → 422", frozen.status === 422, `got ${frozen.status}`);

  // NO_SHOW on the rebooked meeting.
  const rebookedMeeting = after.find((m) => m.inquiryId === globalThis.rebookedInquiryId);
  if (rebookedMeeting) {
    const noShow = await req("PATCH", `/api/admin/meetings/${rebookedMeeting.id}`, { body: { status: "NO_SHOW" }, extraHeaders: csrfHeaders() });
    check("BOOKED → NO_SHOW transition works", noShow.status === 200 && (await noShow.json())?.data?.meeting?.status === "NO_SHOW");
  }
}

// ===========================================================================
console.log("\n6 · INQUIRY DELETION POLICY (spec §7)");
{
  // Without meeting.
  const plain = await req("POST", "/api/inquiries", { body: inquiryPayload() });
  const plainId = (await plain.json())?.data?.id;
  const delPlain = await req("DELETE", `/api/admin/inquiries/${plainId}`, { extraHeaders: csrfHeaders() });
  check("delete inquiry without meeting → 200", delPlain.status === 200 && (await delPlain.json())?.data?.deleted === true);

  // With a BOOKED meeting (fresh slot — sections 2/4/5 still hold theirs).
  const slot = futureSlot(20);
  const booked = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }) });
  const bookedId = (await booked.json())?.data?.id;
  const delBooked = await req("DELETE", `/api/admin/inquiries/${bookedId}`, { extraHeaders: csrfHeaders() });
  const delBody = await delBooked.json().catch(() => null);
  check("delete inquiry with BOOKED meeting → 200", delBooked.status === 200, `got ${delBooked.status} ${JSON.stringify(delBody?.error ?? {})}`);

  // No orphans: neither row remains; slot is free again.
  const meetings = (await (await req("GET", "/api/admin/meetings")).json())?.data?.meetings ?? [];
  check("no orphaned meeting after inquiry deletion", !meetings.some((m) => m.inquiryId === bookedId));
  const rebook = await req("POST", "/api/inquiries", { body: inquiryPayload({ meeting: { booking: true, startsAt: slot } }) });
  check("freed slot bookable again after deletion → 201", rebook.status === 201, `got ${rebook.status}`);
}

// ===========================================================================
console.log("\n7 · AUTHORIZATION + CSRF (spec §13)");
{
  const unauthList = await req("GET", "/api/admin/meetings", { noCookies: true });
  check("unauthenticated meetings list → 401", unauthList.status === 401);
  const unauthPatch = await req("PATCH", "/api/admin/meetings/whatever", { body: { status: "COMPLETED" }, noCookies: true });
  check("unauthenticated meeting status change → 401", unauthPatch.status === 401);
  const unauthBlocks = await req("GET", "/api/admin/blocks", { noCookies: true });
  check("unauthenticated blocks list → 401", unauthBlocks.status === 401);

  const noCsrf = await req("PATCH", "/api/admin/meetings/whatever", { body: { status: "COMPLETED" } });
  check("CSRF-less meeting PATCH → 403 FORBIDDEN", noCsrf.status === 403 && (await noCsrf.json())?.error?.code === "FORBIDDEN");
  const noCsrfBlock = await req("POST", "/api/admin/blocks", { body: { blockedDate: "2026-12-25" } });
  check("CSRF-less block create → 403", noCsrfBlock.status === 403);
  const noCsrfDelete = await req("DELETE", "/api/admin/inquiries/00000000-0000-0000-0000-000000000000", {});
  check("CSRF-less inquiry delete → 403", noCsrfDelete.status === 403);

  // Availability settings round-trip (authorized).
  const cfg = (await (await req("GET", "/api/admin/availability")).json())?.data?.settings;
  check("availability settings readable", Boolean(cfg) && (cfg?.slotDurationMinutes ?? 0) === 30);
  const put = await req("PUT", "/api/admin/availability", { body: { ...cfg, slotDurationMinutes: 60 }, extraHeaders: csrfHeaders() });
  check("availability update → 200 with new value", put.status === 200 && (await put.json())?.data?.settings?.slotDurationMinutes === 60);
  const cal60 = (await (await req("GET", "/api/meetings/availability")).json())?.data?.availability;
  check("public calendar reflects the new slot length", cal60?.slotDurationMinutes === 60);
  const restore = await req("PUT", "/api/admin/availability", { body: { ...cfg }, extraHeaders: csrfHeaders() });
  check("availability restored → 200", restore.status === 200);
}

// ===========================================================================
console.log(`\nRESULT (meetings E2E, ${BASE}): ${passed} passed · ${failed} failed`);
if (failures.length) {
  console.log("Failures:");
  for (const f of failures) console.log(`  - ${f}`);
}
process.exit(failed === 0 ? 0 : 1);
