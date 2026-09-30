# Meetings / Booking Feature — Implementation Notes

Extension of the business-inquiry flow (spec: "Add a Meeting Booking / Calendar
system"). The V3 contact form design is untouched; the meeting section is an
additional collapsible step in the same form. No external calendar provider —
Laravel + MySQL (SQLite-compatible for tests) is the source of truth.

## Backend schema (migrations 100001–100003)

- **meeting_settings** — one row (`id=1`) holds global availability config:
  `timezone`, `slot_minutes`, `lead_time_minutes`, `horizon_days`,
  `working_hours` JSON `{"mon":[["09:00","12:00"],["14:00","17:00"]], ...}`.
  Seeded by the migration (Mon–Fri 09:00–17:00, 30-min slots, 60-min lead,
  60-day horizon, timezone `UTC`).
- **meeting_blocks** — admin blocks. `date` + `slot_time` (nullable):
  date-only row blocks the whole day; row with `slot_time` blocks one slot.
  `reason` optional, admin-facing.
- **meetings** — the booking.
  - `business_inquiry_id` unique FK → `business_inquiries.id`, `ON DELETE CASCADE`.
  - `starts_at` DATETIME(3) UTC — the exact slot start.
  - `ends_at` DATETIME(3) UTC = starts_at + slot_minutes.
  - `duration_minutes` snapshot of the setting at booking time.
  - `status` ENUM BOOKED / COMPLETED / CANCELLED / NO_SHOW (default BOOKED).
  - `cancelled_at`, `cancellation_reason` (nullable).
  - `unique_slot_guard` — **generated column** = `starts_at` when `status='BOOKED'` else NULL,
    with `UNIQUE(unique_slot_guard)`. Enforces "one live booking per slot" at the
    database level; cancelled/completed/no-show rows store NULL and never collide.
  - Indexes: `starts_at`, `status`, `business_inquiry_id` (unique).

## Deletion policy (backend-enforced, spec §7)

`DELETE /api/admin/inquiries/{id}`:
- No meeting → deletes the inquiry (notes cascade via FK).
- Meeting in terminal state (COMPLETED / CANCELLED / NO_SHOW) → deletes
  inquiry + meeting together.
- Meeting still BOOKED → **rejected with 409** `MEETING_STILL_BOOKED` unless
  the caller passes `?cascadeMeeting=1`, which cancels the meeting first
  (`cancelled_at`, reason "inquiry-deleted") then deletes both. No orphans
  are possible under either branch.

`DELETE /api/admin/meetings/{id}` — permanent single-meeting cleanup
(ADMIN: only `availability.write`/`meetings.delete` holders may call it).

## API routes added (existing 11 inquiry/auth routes untouched)

Public:
- `GET /api/meetings/availability?date=YYYY-MM-DD` — one date (or the whole
  horizon when omitted). Computes slots from meeting_settings, then removes
  blocked, already-BOOKED, past, and outside-hours slots server-side.

Admin (session + CSRF as for inquiries):
- `GET /api/admin/meetings?from&to&status&inquiry_id` — list with inquiry join
  (calendar + list data source).
- `GET /api/admin/meetings/{id}`
- `PATCH /api/admin/meetings/{id}` — status transitions (validated), including
  CANCELLED (frees the slot via the generated-column guard).
- `DELETE /api/admin/meetings/{id}` — permanent cleanup.
- `GET/PUT /api/admin/availability` — read/update the settings row
  (timezone, slot length, lead time, horizon, working hours per weekday).
- `GET/POST/DELETE /api/admin/blocks` — whole-day and single-slot blocks.

Permissions: `meetings.read` (SUPER_ADMIN + ADMIN), `meetings.update`,
`meetings.delete`, `availability.write` (SUPER_ADMIN only). Added to the
`Permissions` catalog — the `can:` middleware reads it via `Permissions::has`.

## Timezone (spec §12)

`config/meetings.php` + `meeting_settings.timezone` (DB wins). All slot
arithmetic happens in the configured zone via `Clock::businessNow()` /
`MeetingPolicy`; storage is UTC DATETIME(3). The public UI labels times with
the business timezone so a customer in another zone isn't misled.

## Atomic booking (spec §5)

`POST /api/inquiries` now wraps inquiry + optional meeting in one
`DB::transaction`. Inside it: re-validate the slot against settings, blocks,
existing BOOKED meetings, and lead time. A lost race (unique-slot guard or
concurrent check) → `409 SLOT_TAKEN` with the standard error envelope; the
inquiry is rolled back so no half-state exists. The client re-fetches
availability after any booking attempt.

## Frontend

- `frontend/src/lib/meetings.ts` — types + availability fetch helpers.
- `InquiryForm` — a "Would you like to book a meeting?" yes/no toggle
  (matching V3 styling); Yes reveals date list (from availability API) →
  time-slot grid → summary → the same submit button books inquiry + meeting.
  No changes to the 8 existing fields or their layout.
- Admin: new `frontend/src/components/admin/MeetingCalendar.tsx` (month view +
  day-list drill-in) and availability/blocks management; inquiry list gains a
  Meeting column and filters; inquiry detail shows the meeting panel.

## Test/E2E coverage

Pest: availability computation (blocks/booked/past/lead-time/horizon),
double-booking race (both 200s impossible), cancel-frees-slot, deletion policy
branches, permission denials. `scripts/e2e-meetings.mjs` extends the HTTP E2E
suite with the meeting lifecycle against the real server. The original
`scripts/e2e-inquiry.mjs` must keep passing unchanged.

## Post-implementation verification (2026-09-25)

- Pest: **88 passed / 0 failed** (69 pre-meeting + 19 meeting: engine + HTTP suites).
- `scripts/e2e-meetings.mjs`: **43 passed / 0 failed** on the isolated launcher.
- `scripts/e2e-inquiry.mjs --backend=laravel`: **41 passed / 0 failed / 1 skipped**
  (the documented /admin-redirect skip) — the inquiry contract is untouched.
- Migrations `100001–100003` ran on the production DB (`php artisan migrate --force`).
- Two real bugs found by the E2E and fixed:
  1. `MeetingAvailability::calendar()` kept integer collection keys in the booked
     map, so `isset($map['Y-m-d H:i'])` never matched — **booked slots were shown
     as AVAILABLE** (the exact defect spec §2 forbids). Now string-keyed via
     `array_fill_keys`.
  2. The admin meetings presenter leaked Laravel's ISO-8601 `startsAt`
     (`2026-10-05T15:00:00.000000Z`) instead of the contract wall-time format —
     re-booking from a listed meeting failed validation. Presenter now formats
     explicitly.
- Browser-verified end-to-end on the production server: form booking → success
  card → DB row with E.164 number; admin list badge; calendar month/day views;
  availability editor; probe cleanup through the delete API with **0 orphaned
  meetings**.

## Deliberate behavior decisions

1. **Time standard: UTC, the only canonical zone.** (2026-09-25 correction,
   supersedes the original Asia/Karachi business timezone.) Storage, database
   datetime values, the slot grid, working days, lead time, booking window,
   conflict checks, the admin calendar and every API timestamp are UTC —
   ISO 8601 with the `Z` suffix on the wire (`2026-09-30T10:00:00Z`). There is
   NO configurable business timezone (`MEETING_TIMEZONE` was removed), no
   browser/server-local conversion, and no geographic label: UIs say "UTC".
   `App\Support\MeetingTime` is the single clock (normalize/toWire/toStorage);
   `App\Support\UtcDatetime` is an Eloquent cast that pins parsing/writing to
   UTC so moving the server cannot shift existing timestamps. Legacy input
   tolerance: bare `Y-m-d H:i` is accepted and interpreted as UTC. Proofs live
   in `tests/Feature/MeetingTimezoneTest.php` (process-TZ relocation,
   offset-form conflict, Z-format contract, geo-name scan).
2. **Deletion policy** (`meetings.inquiry_delete_policy = 'delete_meeting'`):
   a meeting can never outlive its inquiry (FK RESTRICT), so deleting an inquiry
   deletes its meeting row (any status) in the same transaction and frees the
   slot; `'prevent'` refuses while the meeting is still BOOKED. Cancellation —
   not deletion — is the normal operational path and preserves history.
3. **Slot holds** = BOOKED + COMPLETED (a completed meeting keeps its historical
   slot); CANCELLED / NO_SHOW free the slot immediately.
4. **Availability refresh** — the public form re-fetches the calendar after every
   booking attempt, including 409 conflicts (the stale selection is dropped).
5. **Meeting filters** (With/Without/Upcoming/Completed/Cancelled) resolve
   client-side over the current page payload — honest, no fake pagination.
