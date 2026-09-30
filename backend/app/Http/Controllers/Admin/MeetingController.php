<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Meeting;
use App\Models\MeetingSetting;
use App\Models\MeetingBlock;
use App\Services\ApiResponse;
use App\Services\MeetingAvailability;
use App\Services\MeetingConflictException;
use App\Services\MeetingPolicy;
use App\Support\Clock;
use App\Support\MeetingTime;
use Illuminate\Support\Str;

/**
 * ADMIN MEETINGS (spec §3/§6/§7/§10). Same envelope + auth/CSRF conventions as
 * the inquiry API. List is calendar-shaped (grouped by business-tz date) plus a
 * flat mode for the table view. Status transitions are validated server-side.
 * Cancellation preserves history; hard delete is the explicit cleanup path.
 */
class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $status = $request->query('status');

        $query = Meeting::query()
            ->join('business_inquiries', 'business_inquiries.id', '=', 'meetings.inquiry_id')
            ->selectRaw('meetings.*, business_inquiries.name as customer_name, business_inquiries.company as customer_company, business_inquiries.email as customer_email, business_inquiries.contact_number as customer_contact, business_inquiries.project_type as project_type');

        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where('meetings.starts_at', '>=', $from . ' 00:00:00.000');
        }
        if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where('meetings.starts_at', '<=', $to . ' 23:59:59.999');
        }
        if (is_string($status) && in_array($status, Meeting::STATUSES, true)) {
            $query->where('meetings.status', $status);
        }

        $meetings = $query->orderBy('meetings.starts_at')->get();

        // Group by UTC date for the calendar views — UTC is the only calendar
        // basis; nothing is derived from the admin's browser timezone.
        $tz = MeetingTime::TZ;
        $days = [];
        foreach ($meetings as $m) {
            $date = \Illuminate\Support\Carbon::parse($m->starts_at, $tz)->toDateString();
            $days[$date][] = $this->present($m);
        }

        return ApiResponse::ok([
            'timezone' => $tz,
            'days' => $days,
            'meetings' => $meetings->map(fn ($m) => $this->present($m))->all(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $meeting = $this->findWithInquiry($id);
        if ($meeting === null) {
            return ApiResponse::notFound('Meeting not found.');
        }

        return ApiResponse::ok(['meeting' => $this->present($meeting)]);
    }

    /** Update status with validated transitions (spec §6). */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $meeting = Meeting::find($id);
        if ($meeting === null) {
            return ApiResponse::notFound('Meeting not found.');
        }

        $body = json_decode($request->getContent(), true);
        $status = is_array($body) ? ($body['status'] ?? null) : null;
        if (! is_string($status) || ! in_array($status, Meeting::STATUSES, true)) {
            return ApiResponse::validation(['status' => 'Unknown meeting status.']);
        }
        if ($status === $meeting->status) {
            return ApiResponse::ok(['meeting' => $this->present($meeting)]);
        }
        if (! MeetingPolicy::canTransition($meeting->status, $status)) {
            return ApiResponse::validation(['status' => "A {$meeting->status} meeting cannot move to {$status}."]);
        }

        $admin = $request->attributes->get('admin');

        if ($status === 'CANCELLED') {
            $reason = is_array($body) && is_string($body['reason'] ?? null) ? $body['reason'] : '';
            $meeting = MeetingPolicy::cancel($meeting, $admin->id, $reason);
        } else {
            $meeting->status = $status;
            $meeting->updated_at = Clock::now();
            if (in_array($status, ['CANCELLED', 'NO_SHOW'], true)) {
                // not reachable via this branch, kept for safety
            }
            $meeting->save();
        }

        \App\Services\AuditLog::record($request, 'meeting.update', 'meeting', $meeting->id, [
            'status' => $status, 'reason' => $status === 'CANCELLED' ? ($body['reason'] ?? null) : null,
        ]);

        return ApiResponse::ok(['meeting' => $this->present($meeting->fresh())]);
    }

    /** Cancel = status transition to CANCELLED with a reason (spec §6/§7). */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $reason = is_array($body) && is_string($body['reason'] ?? null) ? $body['reason'] : 'Cancelled by administrator.';

        $request->merge(['__cancel_reason' => $reason]);
        return $this->updateStatus($request, $id);
    }

    /**
     * PERMANENT delete (authorized cleanup, spec §7). Any status allowed —
     * this is the explicit "destroy the record" path, distinct from cancel.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $meeting = Meeting::find($id);
        if ($meeting === null) {
            return ApiResponse::notFound('Meeting not found.');
        }

        $meeting->delete();

        \App\Services\AuditLog::record($request, 'meeting.delete', 'meeting', $id);

        return ApiResponse::ok(['deleted' => true, 'id' => $id]);
    }

    // ------------------------------------------------------------- availability

    public function availabilitySettings(): JsonResponse
    {
        $s = MeetingSetting::current();

        return ApiResponse::ok(['settings' => [
            'workingDays' => $s->workingDays(),
            'dayStartMinutes' => (int) $s->day_start_minutes,
            'dayEndMinutes' => (int) $s->day_end_minutes,
            'slotDurationMinutes' => (int) $s->slot_duration_minutes,
            'bookingWindowDays' => (int) $s->booking_window_days,
            'minLeadTimeMinutes' => (int) $s->min_lead_time_minutes,
            'enabled' => (bool) $s->enabled,
            'timezone' => MeetingTime::TZ,
        ]]);
    }

    public function updateAvailabilitySettings(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $s = MeetingSetting::current();
        $errors = [];

        $days = $body['workingDays'] ?? null;
        if (is_array($days)) {
            $days = array_values(array_unique(array_filter(array_map('intval', $days), fn ($d) => $d >= 0 && $d <= 6)));
            if ($days === []) {
                $errors['workingDays'] = 'Select at least one working day.';
            } else {
                sort($days);
                $s->working_days = implode(',', $days);
            }
        }

        foreach ([
            'dayStartMinutes' => 'day_start_minutes',
            'dayEndMinutes' => 'day_end_minutes',
            'slotDurationMinutes' => 'slot_duration_minutes',
            'bookingWindowDays' => 'booking_window_days',
            'minLeadTimeMinutes' => 'min_lead_time_minutes',
        ] as $key => $column) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                $value = (int) $body[$key];
                $s->$column = $value;
            }
        }

        if ((int) $s->day_start_minutes >= (int) $s->day_end_minutes) {
            $errors['dayEndMinutes'] = 'End of day must be after the start.';
        }
        if ((int) $s->slot_duration_minutes < 10 || (int) $s->slot_duration_minutes > 240) {
            $errors['slotDurationMinutes'] = 'Slot duration must be between 10 and 240 minutes.';
        }
        if (isset($body['enabled'])) {
            $s->enabled = (bool) $body['enabled'];
        }

        if ($errors !== []) {
            return ApiResponse::validation($errors);
        }

        $s->updated_at = Clock::now();
        $s->save();

        \App\Services\AuditLog::record($request, 'availability.update', 'availability', null, [
            'workingDays' => $s->workingDays(), 'enabled' => (bool) $s->enabled,
        ]);

        return $this->availabilitySettings();
    }

    /** List blocks (dates + slots). */
    public function blocks(): JsonResponse
    {
        $blocks = MeetingBlock::query()->orderBy('created_at')->get();

        return ApiResponse::ok(['blocks' => $blocks->map(fn ($b) => [
            'id' => $b->id,
            'blockedDate' => $b->blocked_date,
            'startsAt' => $b->starts_at ? $this->wire($b->starts_at) : null,
            'reason' => $b->reason,
            'createdAt' => $this->wire($b->created_at),
        ])->all()]);
    }

    /** Create a block: either {blockedDate} or {startsAt}. */
    public function createBlock(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $date = $body['blockedDate'] ?? null;
        $startsAt = $body['startsAt'] ?? null;
        $reason = is_string($body['reason'] ?? null) ? mb_substr($body['reason'], 0, 255) : '';

        if (($date === null) === ($startsAt === null)) {
            return ApiResponse::validation(['block' => 'Provide exactly one of blockedDate or startsAt.']);
        }
        if ($date !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return ApiResponse::validation(['blockedDate' => 'Invalid date.']);
        }
        if ($startsAt !== null) {
            $startsAt = MeetingTime::normalize((string) $startsAt);
            if ($startsAt === null) {
                return ApiResponse::validation(['startsAt' => 'Invalid slot time (use ISO 8601 UTC, e.g. 2026-09-30T10:00:00Z).']);
            }
        }

        $admin = $request->attributes->get('admin');
        $now = Clock::now();

        // Idempotent: an identical block already in place returns it unchanged.
        $existing = MeetingBlock::query()
            ->when($date !== null, fn ($q) => $q->where('blocked_date', $date)->whereNull('starts_at'))
            ->when($date === null, fn ($q) => $q->where('starts_at', $startsAt)->whereNull('blocked_date'))
            ->first();
        if ($existing !== null) {
            return ApiResponse::ok(['block' => ['id' => $existing->id, 'blockedDate' => $existing->blocked_date, 'startsAt' => $existing->starts_at ? $this->wire($existing->starts_at) : null, 'reason' => $existing->reason, 'createdAt' => $this->wire($existing->created_at)]], 200);
        }

        $block = MeetingBlock::create([
            'id' => (string) Str::uuid(),
            'blocked_date' => $date,
            'starts_at' => $startsAt,
            'reason' => $reason,
            'blocker_id' => $admin?->id,
            'created_at' => $now,
        ]);

        \App\Services\AuditLog::record($request, 'block.create', 'meeting_block', $block->id, [
            'blockedDate' => $date, 'startsAt' => $startsAt,
        ]);

        return ApiResponse::ok(['block' => ['id' => $block->id, 'blockedDate' => $block->blocked_date, 'startsAt' => $block->starts_at ? $this->wire($block->starts_at) : null, 'reason' => $block->reason, 'createdAt' => $this->wire($block->created_at)]], 201);
    }

    public function deleteBlock(Request $request, string $id): JsonResponse
    {
        $block = MeetingBlock::find($id);
        if ($block === null) {
            return ApiResponse::notFound('Block not found.');
        }
        $block->delete();

        \App\Services\AuditLog::record($request, 'block.delete', 'meeting_block', $id);

        return ApiResponse::ok(['deleted' => true, 'id' => $id]);
    }

    // ------------------------------------------------------------------ helpers

    private function findWithInquiry(string $id): ?object
    {
        return Meeting::query()
            ->join('business_inquiries', 'business_inquiries.id', '=', 'meetings.inquiry_id')
            ->selectRaw('meetings.*, business_inquiries.name as customer_name, business_inquiries.company as customer_company, business_inquiries.email as customer_email, business_inquiries.contact_number as customer_contact, business_inquiries.project_type as project_type')
            ->where('meetings.id', $id)
            ->first();
    }

    /** Any stored datetime → ISO 8601 UTC wire string ('…Z'). */
    private function wire($dt): ?string
    {
        if ($dt === null) {
            return null;
        }
        $carbon = $dt instanceof \Illuminate\Support\Carbon ? $dt : \Illuminate\Support\Carbon::parse($dt, MeetingTime::TZ);

        return MeetingTime::toWire($carbon);
    }

    private function present(object $m): array
    {
        // startsAt MUST be strict ISO 8601 UTC ('Y-m-d\TH:i:s\Z') — an instant,
        // never an ambiguous wall string. Clients slice nothing; re-booking
        // sends the value back and MeetingTime::normalize accepts it.
        $startsAt = $m->starts_at instanceof \Illuminate\Support\Carbon
            ? MeetingTime::toWire($m->starts_at)
            : (MeetingTime::normalize((string) $m->starts_at) !== null
                ? MeetingTime::toWire(\Illuminate\Support\Carbon::parse(MeetingTime::normalize((string) $m->starts_at), MeetingTime::TZ))
                : null);

        return [
            'id' => $m->id,
            'inquiryId' => $m->inquiry_id,
            'startsAt' => $startsAt,
            'durationMinutes' => (int) $m->duration_minutes,
            'status' => $m->status,
            'cancelledReason' => $m->cancelled_reason ?? '',
            'cancelledAt' => $this->wire($m->cancelled_at),
            'customer' => [
                'name' => $m->customer_name,
                'company' => $m->customer_company,
                'email' => $m->customer_email,
                'contactNumber' => $m->customer_contact,
            ],
            'projectType' => $m->project_type,
        ];
    }
}
