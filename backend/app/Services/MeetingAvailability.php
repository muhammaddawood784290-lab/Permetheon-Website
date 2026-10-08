<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingBlock;
use App\Models\MeetingSetting;
use App\Support\Clock;
use App\Support\MeetingTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** MEETING AVAILABILITY — the single server-side authority on what can be booked
 * (spec §2/§5/§12). The frontend is never trusted: availability is always
 * computed here, and the booking path re-validates inside the transaction.
 *
 * TIME STANDARD: UTC everywhere. Storage, database datetime values, the slot
 * grid, working days, lead time, booking window, conflict checks and every
 * API timestamp are UTC (ISO 8601, 'Z' suffix on the wire). No geographic
 * timezone, no browser/server-local conversion exists anywhere in this
 * system — see App\Support\MeetingTime.
 *
 * Slot model: fixed grid [day_start, day_end) in UTC, split into
 * slot_duration_minutes steps. A slot is available iff:
 *   - meeting_settings.enabled
 *   - the date is a configured working day, within the booking window
 *   - the slot is in the future by at least min_lead_time_minutes
 *   - the date is not admin-blocked, the slot is not admin-blocked
 *   - no meeting row holds the slot (BOOKED/COMPLETED — DB guard backs this)
 */
class MeetingAvailability
{
    public function __construct(private string $businessTz = MeetingTime::TZ)
    {
        $this->businessTz = MeetingTime::TZ;
    }

    public static function make(): self
    {
        return new self(MeetingTime::TZ);
    }

    /** Availability for every day in the public booking window. */
    public function calendar(): array
    {
        $settings = MeetingSetting::current();
        $tz = MeetingTime::TZ;
        $now = MeetingTime::now();

        $from = $now->copy()->startOfDay();
        $to = $now->copy()->addDays(max(1, $settings->booking_window_days))->endOfDay();

        $booked = Meeting::query()
            ->whereIn('status', Meeting::SLOT_HOLDING)
            ->whereBetween('starts_at', [MeetingTime::toStorage($from), MeetingTime::toStorage($to)])
            ->pluck('starts_at')
            ->map(fn ($s) => Carbon::parse($s, $tz)->format('Y-m-d H:i'));

        $blockedDays = MeetingBlock::query()->whereNotNull('blocked_date')
            ->whereBetween('blocked_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('blocked_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        $blockedSlots = MeetingBlock::query()->whereNotNull('starts_at')
            ->whereBetween('starts_at', [MeetingTime::toStorage($from), MeetingTime::toStorage($to)])
            ->pluck('starts_at')->map(fn ($s) => Carbon::parse($s, $tz)->format('Y-m-d H:i'))->all();

        $blockedSlotMap = array_fill_keys($blockedSlots, true);

        $bookedMap = array_fill_keys($booked->values()->all(), true);

        $days = [];
        $workingDays = $settings->workingDays();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dateStr = $day->toDateString();
            if (! in_array($day->dayOfWeek, $workingDays, true)) {
                $days[] = ['date' => $dateStr, 'dayOfWeek' => $day->dayOfWeek, 'status' => 'NON_WORKING', 'slots' => []];
                continue;
            }
            if (in_array($dateStr, $blockedDays, true)) {
                $days[] = ['date' => $dateStr, 'dayOfWeek' => $day->dayOfWeek, 'status' => 'BLOCKED', 'slots' => []];
                continue;
            }

            $slots = [];
            $cursor = $day->copy()->startOfDay()->addMinutes($settings->day_start_minutes);
            $end = $day->copy()->startOfDay()->addMinutes($settings->day_end_minutes);

            // FIXED: Use a strict 60-minute slot interval for the grid.
            $slotInterval = 60;

            while ($cursor->copy()->addMinutes($slotInterval)->lte($end)) {
                $key = $cursor->format('Y-m-d H:i');
                $endsAt = $cursor->copy()->addMinutes(30); // MEETING DURATION = 30
                $past = $endsAt->lte($now);
                $tooSoon = $cursor->lt($now->copy()->addMinutes($settings->min_lead_time_minutes));
                $taken = isset($bookedMap[$key]);
                $blocked = isset($blockedSlotMap[$key]);

                $slots[] = [
                    'startsAt' => MeetingTime::toWire($cursor),
                    'endsAt' => MeetingTime::toWire($endsAt),
                    'status' => ($past || $tooSoon) ? 'PAST' : ($taken ? 'BOOKED' : ($blocked ? 'BLOCKED' : 'AVAILABLE')),
                ];
                $cursor->addMinutes($slotInterval);
            }

            $anyAvailable = count(array_filter($slots, fn ($s) => $s['status'] === 'AVAILABLE')) > 0;
            $days[] = [
                'date' => $dateStr,
                'dayOfWeek' => $day->dayOfWeek,
                'status' => $anyAvailable ? 'OPEN' : 'FULL',
                'slots' => $slots,
            ];
        }

        return [
            'timezone' => $tz,
            'slotDurationMinutes' => $slotInterval,
            'enabled' => (bool) $settings->enabled,
            'days' => $days,
        ];
    }

    /** Server-side check immediately before insert (inside the transaction). */
    public function assertBookable(string $startsAt): void
    {
        $settings = MeetingSetting::current();

        if (! $settings->enabled) {
            throw new MeetingConflictException('Meeting booking is currently unavailable.');
        }

        $tz = MeetingTime::TZ;
        $normalized = MeetingTime::normalize($startsAt);
        if ($normalized === null) {
            throw new MeetingConflictException('Invalid meeting time.');
        }
        $slot = Carbon::parse($normalized, $tz);

        // FIXED: Must land exactly on a 60-minute boundary (0 minutes).
        if ((int) $slot->format('i') !== 0
            || (int) $slot->format('G') * 60 + (int) $slot->format('i') < (int) $settings->day_start_minutes
            || (int) $slot->format('G') * 60 + (int) $slot->format('i') + 30 > (int) $settings->day_end_minutes) {
            throw new MeetingConflictException('The selected time is outside working hours.');
        }
        if (! in_array($slot->dayOfWeek, $settings->workingDays(), true)) {
            throw new MeetingConflictException('The selected date is not a working day.');
        }

        $now = MeetingTime::now();
        if ($slot->copy()->addMinutes(30)->lte($now)) {
            throw new MeetingConflictException('The selected time is in the past.');
        }
        if ($slot->lt($now->copy()->addMinutes($settings->min_lead_time_minutes))) {
            throw new MeetingConflictException('The selected time is too soon — please pick a later slot.');
        }
        if ($slot->gt($now->copy()->addDays(max(1, $settings->booking_window_days))->endOfDay())) {
            throw new MeetingConflictException('The selected date is outside the booking window.');
        }

        if (MeetingBlock::query()->whereNotNull('blocked_date')->where('blocked_date', $slot->toDateString())->exists()) {
            throw new MeetingConflictException('The selected date is not available.');
        }
        if (MeetingBlock::query()->whereNotNull('starts_at')->where('starts_at', MeetingTime::toStorage($slot))->exists()) {
            throw new MeetingConflictException('The selected time is not available.');
        }

        $holding = Meeting::query()
            ->whereIn('status', Meeting::SLOT_HOLDING)
            ->where('starts_at', MeetingTime::toStorage($slot))
            ->exists();
        if ($holding) {
            throw new MeetingConflictException('That time slot has just been taken. Please choose another available slot.');
        }
    }

    /**
     * ATOMIC BOOKING (spec §5): create the meeting with the availability
     * re-check, then let the unique partial index on active slots turn any
     * race into a duplicate-key failure, surfaced as MeetingConflictException.
     */
    public function bookForInquiry(string $inquiryId, string $startsAt, int $durationMinutes): Meeting
    {
        $storage = MeetingTime::normalize($startsAt);
        if ($storage === null) {
            throw new MeetingConflictException('Invalid meeting time.');
        }

        $this->assertBookable($storage);

        $now = Clock::now();

        // FIXED: Meeting duration is strictly 30 minutes.
        $meeting = Meeting::create([
            'inquiry_id' => $inquiryId,
            'starts_at' => $storage,
            'duration_minutes' => 30,
            'status' => 'BOOKED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $meeting;
    }
}
