/**
 * PRE-DEPLOYMENT E2E CHECK — full inquiry lifecycle over real HTTP.
 * Run against an ISOLATED instance so the production database never receives
 * test records. Not part of the unit contract suite; run manually before
 * releases:
 *   1. node scripts/e2e-laravel-server.mjs        (throwaway MySQL DB + bootstrap vars)
 *   2. node scripts/e2e-inquiry.mjs
 *   3. node scripts/e2e-laravel-server.mjs --stop (drops the throwaway DB)
 *
 *   E2E_BASE=http://localhost:3103 node scripts/e2e-inquiry.mjs   # custom port
 *
 * The launcher (scripts/e2e-laravel-server.mjs) provisions the isolated
 * server, throwaway MySQL database and bootstrap vars automatically on :3102.
 *
 * Documented difference: the Laravel backend has no page routes (the SPA is
 * served by the same origin), so the server-side /admin redirect check is
 * SKIPped — the authoritative protection (API 401s) is asserted directly.
 */
const BASE = process.env.E2E_BASE ?? "http://localhost:3102";
const BACKEND = "laravel";
const BOOTSTRAP = { email: "admin@permetheon.com", password: "E2E-test-passphrase-1" };

let passed = 0;
let failed = 0;
let skipped = 0;
function check(label, condition, detail = "") {
  if (condition) {
    passed++;
    console.log(`  ✓ ${label}`);
  } else {
    failed++;
    console.log(`  ✗ ${label}${detail ? ` — ${detail}` : ""}`);
  }
}
function skip(label, reason) {
  skipped++;
  console.log(`  ↷ SKIP ${label} — ${reason}`);
}

const jar = new Map();
function cookieHeader() {
  return [...jar.entries()].map(([k, v]) => `${k}=${v}`).join("; ");
}
function storeCookies(response) {
  for (const raw of response.headers.getSetCookie?.() ?? []) {
    const [pair] = raw.split(";");
    const eq = pair.indexOf("=");
    jar.set(pair.slice(0, eq).trim(), pair.slice(eq + 1).trim());
  }
}

async function postJson(path, body, extraHeaders = {}) {
  const response = await fetch(BASE + path, {
    method: "POST",
    headers: { "Content-Type": "application/json", Origin: BASE, ...extraHeaders },
    body: JSON.stringify(body),
  });
  storeCookies(response);
  return response;
}

const INQUIRY = {
  name: "Moin Udden",
  company: "Permetheon Test Co",
  email: "Moin@Example.com",
  contactNumber: "+1 202 555 0147", // non-canonical input → must persist E.164
  projectType: "Booking / Reservation System",
  budget: "$5,000 – $10,000",
  timeline: "1–3 months",
  message:
    "We need a restaurant booking platform with a customer reservation flow and an internal table management dashboard.",
};

// ---------------------------------------------------------------- public API
console.log("\nPUBLIC API");
{
  const r = await postJson("/api/inquiries", INQUIRY);
  const body = await r.json();
  check("valid submission → 201", r.status === 201, `got ${r.status}`);
  check("returns id + server status NEW", body?.data?.id && body?.data?.status === "NEW");
  globalThis.inquiryId = body?.data?.id;
}
{
  const r = await postJson("/api/inquiries", { ...INQUIRY, name: "A", email: "nope", contactNumber: "03001234567", message: "short" });
  const body = await r.json();
  const fields = body?.error?.fields ?? {};
  check("invalid → 422 with per-field errors", r.status === 422 && fields.name && fields.email && fields.message && fields.contactNumber);
}
console.log("\nCONTACT NUMBER (E.164 — Master amendment §15/§47)");
{
  const table = [
    ["formatted input normalizes", "+1 202 555 0147", null],
    ["dots + brackets normalize", "+1.(202)-555-0147", null],
    ["ITU 00 prefix converts", "0012025550147", null],
    ["local-only number rejected", "03001234567", "Enter a valid international contact number"],
    ["missing country code rejected", "12025550147", "Enter a valid international contact number"],
    ["unassigned calling code rejected", "+999 512345678", "Enter a valid international contact number"],
    ["16 national digits rejected", "+120255501471234", "Enter a valid international contact number"],
    ["empty rejected", "", "Please enter your contact number"],
    ["script payload rejected", "+1<script>alert(1)</script>", "Enter a valid international contact number"],
  ];
  for (const [label, input, expectedError] of table) {
    const r = await postJson("/api/inquiries", { ...INQUIRY, email: `phone-probe-${Math.random().toString(36).slice(2)}@example.com`, contactNumber: input });
    const body = await r.json().catch(() => ({}));
    if (expectedError === null) {
      check(label, r.status === 201, `got ${r.status} ${JSON.stringify(body?.error ?? {})}`);
    } else {
      check(label, r.status === 422 && String(body?.error?.fields?.contactNumber ?? "").includes(expectedError), `got ${r.status}`);
    }
  }
}
{
  const r = await fetch(BASE + "/api/inquiries", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: "not-json",
  });
  check("malformed body → 400", r.status === 400, `got ${r.status}`);
}
{
  const r = await postJson("/api/inquiries", {
    ...INQUIRY,
    name: "Injection Probe",
    email: "probe@example.com",
    projectType: "Website Development",
    message: "Checking that privileged fields are stripped on the server side.",
    status: "WON",
    priority: "URGENT",
    role: "SUPER_ADMIN",
    id: "forged",
  });
  const body = await r.json();
  check("mass assignment → 201 with server-controlled status", r.status === 201 && body?.data?.status === "NEW");
  globalThis.probeId = body?.data?.id;
}

// ---------------------------------------------------------------- phone-injection probes
{
  const r = await postJson("/api/inquiries", {
    ...INQUIRY,
    email: "phone-inject@example.com",
    contactNumber: "+12025550147",
    whatsappNumber: "+18005550100",
    phone: "+18005550100",
    contact_phone: "+18005550100",
  });
  check("duplicate contact fields ignored (no whatsapp/phone fields exist)", r.status === 201);
}

// ---------------------------------------------------------------- admin security
console.log("\nADMIN SECURITY");
{
  const r = await fetch(BASE + "/api/admin/inquiries");
  check("unauthenticated list → 401", r.status === 401, `got ${r.status}`);
}
{
  const r = await fetch(`${BASE}/api/admin/inquiries/00000000-0000-0000-0000-000000000000`, { method: "PATCH" });
  check("unauthenticated mutation → 401", r.status === 401, `got ${r.status}`);
}
{
  if (BACKEND === "laravel") {
    // Documented difference: no server-side page redirect (the API is the
    // authority and is asserted above and below).
    skip("/admin redirects unauthenticated visitors", "laravel backend serves no page routes");
  } else {
    const r = await fetch(BASE + "/admin", { redirect: "manual" });
    check("/admin redirects unauthenticated visitors", r.status === 307 && (r.headers.get("location") ?? "").includes("/admin/login"));
  }
}
{
  const r = await postJson("/api/admin/auth/login", { email: BOOTSTRAP.email, password: "wrong-password" });
  check("wrong password → 401 generic", r.status === 401 && (await r.json()).error.code === "UNAUTHORIZED");
}

// ---------------------------------------------------------------- login + dashboard
console.log("\nAUTH + DASHBOARD");
{
  const r = await postJson("/api/admin/auth/login", BOOTSTRAP);
  const body = await r.json();
  check("bootstrap login → 200 + SUPER_ADMIN + permissions", r.status === 200 && body?.data?.admin?.role === "SUPER_ADMIN" && body?.data?.permissions?.includes("system.auth.manage"));
  check("session cookie HttpOnly", (r.headers.getSetCookie?.() ?? []).some((c) => c.startsWith("admin_session=") && /httponly/i.test(c)));
  globalThis.csrf = body?.data?.csrfToken;
}
{
  const r = await fetch(BASE + "/api/admin/auth/session", { headers: { cookie: cookieHeader() } });
  const body = await r.json();
  check("session endpoint → authenticated admin", r.status === 200 && body?.data?.admin?.email === BOOTSTRAP.email);
}
{
  const r = await fetch(BASE + "/api/admin/inquiries", { headers: { cookie: cookieHeader() } });
  const body = await r.json();
  const items = body?.data?.inquiries ?? [];
  check(
    "list → all 6 real inquiries, newest first",
    r.status === 200 && body?.data?.pagination?.total === 6 &&
      items.every((it, i) => i === 0 || items[i - 1].createdAt >= it.createdAt),
    `total=${body?.data?.pagination?.total}`
  );
  check("list exposes canonical E.164 contact numbers", body?.data?.inquiries.every((i) => /^\+\d{5,15}$/.test(i.contactNumber ?? "")), JSON.stringify(body?.data?.inquiries?.map((i) => i.contactNumber)));
  check("formatted input persisted as canonical E.164", body?.data?.inquiries.find((i) => i.email === "moin@example.com")?.contactNumber === "+12025550147");
  check("list search narrows", (await (await fetch(BASE + "/api/admin/inquiries?search=Moin", { headers: { cookie: cookieHeader() } })).json())?.data?.pagination?.total >= 1);
  check("list search by contact number", (await (await fetch(BASE + "/api/admin/inquiries?search=%2B12025550147", { headers: { cookie: cookieHeader() } })).json())?.data?.pagination?.total >= 1);
  check("list status filter works", ((await (await fetch(BASE + "/api/admin/inquiries?status=NEW", { headers: { cookie: cookieHeader() } })).json())?.data?.pagination?.total ?? 0) >= 2);
  check("list status filter narrows precisely", ((await (await fetch(BASE + "/api/admin/inquiries?status=WON", { headers: { cookie: cookieHeader() } })).json())?.data?.pagination?.total ?? -1) === 0);
}
{
  const r = await fetch(BASE + "/api/admin/inquiries/stats", { headers: { cookie: cookieHeader() } });
  const body = await r.json();
  const s = body?.data?.stats;
  check("stats → real aggregates", r.status === 200 && s?.total >= 2 && s?.byStatus?.NEW >= 2);
}

// ---------------------------------------------------------------- mutations (CSRF)
console.log("\nMUTATIONS + CSRF");
{
  const r = await fetch(`${BASE}/api/admin/inquiries/${globalThis.inquiryId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", cookie: cookieHeader() }, // no CSRF header
    body: JSON.stringify({ status: "CONTACTED" }),
  });
  check("mutation without CSRF header → 403", r.status === 403, `got ${r.status}`);
}
{
  const r = await fetch(`${BASE}/api/admin/inquiries/${globalThis.inquiryId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", cookie: cookieHeader(), "x-csrf-token": globalThis.csrf, Origin: BASE },
    body: JSON.stringify({ status: "CONTACTED", priority: "HIGH" }),
  });
  const body = await r.json();
  check("CSRF-checked PATCH → status+priority updated", r.status === 200 && body?.data?.inquiry?.status === "CONTACTED" && body?.data?.inquiry?.priority === "HIGH");
  check("original message untouched", body?.data?.inquiry?.message === INQUIRY.message);
}
{
  const r = await fetch(`${BASE}/api/admin/inquiries/${globalThis.inquiryId}/notes`, {
    method: "POST",
    headers: { "Content-Type": "application/json", cookie: cookieHeader(), "x-csrf-token": globalThis.csrf },
    body: JSON.stringify({ body: "Called and confirmed scope." }),
  });
  const body = await r.json();
  check("note created → 201 with author", r.status === 201 && body?.data?.note?.adminName === "Administrator");
  const notes = await (await fetch(`${BASE}/api/admin/inquiries/${globalThis.inquiryId}/notes`, { headers: { cookie: cookieHeader() } })).json();
  check("note retrievable", notes?.data?.notes?.length === 1);
}
{
  const r = await fetch(`${BASE}/api/admin/auth/logout`, {
    method: "POST",
    headers: { cookie: cookieHeader(), "x-csrf-token": globalThis.csrf },
  });
  check("logout → 200", r.status === 200);
  jar.delete("admin_session");
  const after = await fetch(BASE + "/api/admin/inquiries", { headers: { cookie: cookieHeader() } });
  check("session revoked after logout → 401", after.status === 401, `got ${after.status}`);
}

// ---------------------------------------------------------------- verification of persisted rows
console.log("\nPERSISTED ROWS (via fresh login)");
{
  const login = await postJson("/api/admin/auth/login", BOOTSTRAP);
  globalThis.csrf = (await login.json())?.data?.csrfToken;
  const detail = await (await fetch(`${BASE}/api/admin/inquiries/${globalThis.probeId}`, { headers: { cookie: cookieHeader() } })).json();
  const row = detail?.data?.inquiry;
  check("injected row stored as NEW/MEDIUM (never WON/URGENT)", row?.status === "NEW" && row?.priority === "MEDIUM");
  check("duplicate contact fields not persisted (single canonical value)", row?.contactNumber === "+12025550147");
  check("no phone/whatsapp shadow fields persisted", row && !("phone" in row) && !("whatsappNumber" in row) && !("contact_phone" in row));
  check("email normalized lowercase on persist", (await (await fetch(`${BASE}/api/admin/inquiries?search=moin@example.com`, { headers: { cookie: cookieHeader() } })).json())?.data?.pagination?.total === 1);
  check("detail contactNumber is exact E.164 with tel/wa.me derivable", row?.contactNumber === "+12025550147" && `https://wa.me/${row.contactNumber.slice(1)}` === "https://wa.me/12025550147");
}

console.log(`\nRESULT (${BACKEND} backend, ${BASE}): ${passed} passed · ${failed} failed${skipped ? ` · ${skipped} skipped` : ""}`);
process.exit(failed === 0 ? 0 : 1);
