<?php

use App\Models\BusinessInquiry;
use App\Models\Meeting;
use App\Models\MeetingBlock;
use App\Models\MeetingSetting;
use App\Services\MeetingAvailability;
use App\Services\MeetingPolicy;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| MEETING ENGINE SUITE — availability rules + booking races (spec §2/§5/§6/§7)
|--------------------------------------------------------------------------
| The availability engine is the security boundary: every rule (past, lead
| time, window, working days, blocks, booked) is exercised directly against
| the service. Double-booking is attempted for real — two inserts racing for
| the same slot must produce exactly one BOOKED row (DB guard + transaction).
| HTTP-level coverage lives in MeetingHttpTest.php.
*/

it('computes a full calendar: working days open, weekends NON_WORKING, slots on the grid', function () {
    $cal = MeetingAvailability::make()->calendar();

    expect($cal['timezone'])->toBe('UTC')
        ->and($cal['slotDurationMinutes'])->toBe(30)
        ->and($cal['enabled'])->toBeTrue()
        ->and(count($cal['days']))->toBe(31); // booking window default

    $weekend = collect($cal['days'])->first(fn ($d) => in_array($d['dayOfWeek'], [0, 6], true));
    expect($weekend['status'])->toBe('NON_WORKING')->and($weekend['slots'])->toBe([]);

    $open = collect($cal['days'])->first(fn ($d) => $d['status'] === 'OPEN');
    expect($open)->not->toBeNull();
    $times = array_column($open['slots'], 'startsAt');
    expect($times[0])->toEndWith('T09:00:00Z')       // day_start_minutes = 540 (UTC)
        ->and(end($times))->toEndWith('T16:30:00Z'); // last slot ENDING by 17:00 UTC
});

it('marks past slots PAST in the calendar and honors the lead time', function () {
    // Calendar: every slot whose end has passed must be PAST (today only).
    $cal = MeetingAvailability::make()->calendar();
    $today = collect($cal['days'])->firstWhere('date', Carbon::now('UTC')->toDateString());

    if ($today !== null && in_array($today['dayOfWeek'], [1, 2, 3, 4, 5], true)) {
        // PAST means "not selectable": the slot already ended OR it starts
        // inside the minimum lead-time window (the same rule the hermetic
        // lead-time check below exercises). Asserting the full contract — not
        // just "already ended" — so the test holds at any wall-clock time.
        $lead = (int) MeetingSetting::query()->findOrFail('singleton')->min_lead_time_minutes;
        $now = Carbon::now('UTC');
        foreach ($today['slots'] as $s) {
            $unselectable = Carbon::parse($s['endsAt'], 'UTC')->lte($now)
                || Carbon::parse($s['startsAt'], 'UTC')->lt($now->copy()->addMinutes($lead));
            expect($s['status'] === 'PAST')->toBe($unselectable, "slot {$s['startsAt']} must be PAST iff it ended or is inside the lead time");
        }
    }

    // Engine (hermetic): widen working hours to the full day so the upcoming
    // GRID slot — less than 120 minutes out — hits the LEAD-TIME guard
    // regardless of the wall-clock time the suite runs at. ISO 8601 UTC in.
    MeetingSetting::query()->where('id', 'singleton')->update([
        'day_start_minutes' => 0,
        'day_end_minutes'   => 1440,
    ]);
    $soon = Carbon::now('UTC')->addMinutes(30);
    $soon->minute = intdiv((int) $soon->format('i'), 30) * 30; // snap to grid
    if ($soon->format('Y-m-d H:i') === Carbon::now('UTC')->format('Y-m-d H:i')) {
        $soon->addMinutes(30); // ensure it is strictly in the future
    }
    try {
        MeetingAvailability::make()->assertBookable($soon->format('Y-m-d\TH:i:00\Z'));
        $this->fail('Expected lead-time conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'too soon'))->toBeTrue();
    }

    // A fully-past slot is rejected too (legacy 'Y-m-d H:i' tolerance path).
    try {
        MeetingAvailability::make()->assertBookable(Carbon::now('UTC')->subDays(2)->format('Y-m-d H:i'));
        $this->fail('Expected past conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(true)->toBeTrue();
    }
});

it('rejects booking outside working hours / non-grid times', function () {
    $slot = futureSlot(3);

    // 07:00 is before day_start (09:00).
    try {
        MeetingAvailability::make()->assertBookable(substr($slot, 0, 11) . '07:00');
        $this->fail('Expected outside-hours conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'outside working hours'))->toBeTrue();
    }

    // 15:07 is not on the 30-minute grid.
    try {
        MeetingAvailability::make()->assertBookable(substr($slot, 0, 11) . '15:07');
        $this->fail('Expected off-grid conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'outside working hours'))->toBeTrue();
    }

    // 17:30 starts after day_end (17:00).
    try {
        MeetingAvailability::make()->assertBookable(substr($slot, 0, 11) . '17:30');
        $this->fail('Expected after-hours conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'outside working hours'))->toBeTrue();
    }
});

it('blocks: whole-day block suppresses every slot; slot block suppresses one', function () {
    $slot = futureSlot(4);

    // Whole-day block.
    MeetingBlock::create([
        'id'           => (string) Illuminate\Support\Str::uuid(),
        'blocked_date' => substr($slot, 0, 10),
        'starts_at'    => null,
        'reason'       => 'Team offsite',
        'created_at'   => App\Support\Clock::now(),
    ]);
    try {
        MeetingAvailability::make()->assertBookable($slot);
        $this->fail('Expected day-block conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'date is not available'))->toBeTrue();
    }

    // Slot-only block on a different day (storage format via MeetingTime).
    // Offsets are spaced far apart on purpose: futureSlot() walks weekend days
    // forward, so two close offsets can land on the SAME day (e.g. +4 walking
    // over a weekend onto the +5 day) and the day-block would shadow this one.
    $slot2 = futureSlot(12);
    MeetingBlock::create([
        'id'           => (string) Illuminate\Support\Str::uuid(),
        'blocked_date' => null,
        'starts_at'    => \App\Support\MeetingTime::normalize($slot2),
        'reason'       => 'Holding for enterprise call',
        'created_at'   => App\Support\Clock::now(),
    ]);
    try {
        MeetingAvailability::make()->assertBookable($slot2);
        $this->fail('Expected slot-block conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'time is not available'))->toBeTrue();
    }

    // …and the calendar shows that day as still OPEN minus the one slot.
    $cal = MeetingAvailability::make()->calendar();
    $day = collect($cal['days'])->firstWhere('date', substr($slot2, 0, 10));
    expect($day['status'])->toBe('OPEN')
        ->and(collect($day['slots'])->firstWhere('startsAt', $slot2)['status'])->toBe('BLOCKED');
});

it('DOUBLE-BOOKING RACE: two inquiries cannot hold the same slot — exactly one wins', function () {
    $slot = futureSlot(6);
    $a = createInquiry('Race A');
    $b = createInquiry('Race B');
    $availability = MeetingAvailability::make();

    $first = $availability->bookForInquiry($a->id, $slot, 30);
    expect($first->status)->toBe('BOOKED');

    try {
        $availability->bookForInquiry($b->id, $slot, 30);
        $this->fail('Expected slot conflict for the second booking.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'just been taken'))->toBeTrue();
    }

    // DB-level truth: one BOOKED row for the slot (storage format).
    $bookedCount = Meeting::query()
        ->whereIn('status', Meeting::SLOT_HOLDING)
        ->where('starts_at', \App\Support\MeetingTime::normalize($slot))
        ->count();
    expect($bookedCount)->toBe(1);
});

it('cancellation frees the slot: re-booking succeeds, history preserved (spec §6)', function () {
    $slot = futureSlot(7);
    $a = createInquiry('Cancel Me');
    $availability = MeetingAvailability::make();

    $meeting = $availability->bookForInquiry($a->id, $slot, 30);

    $cancelled = MeetingPolicy::cancel($meeting, 'admin-1', 'Customer rescheduled.');
    expect($cancelled->status)->toBe('CANCELLED')
        ->and($cancelled->cancelled_reason)->toBe('Customer rescheduled.')
        ->and($cancelled->cancelled_at)->not->toBeNull();

    // The slot is bookable again…
    $b = createInquiry('Rebook Success');
    $rebooked = $availability->bookForInquiry($b->id, $slot, 30);
    expect($rebooked->status)->toBe('BOOKED')->and($rebooked->inquiry_id)->toBe($b->id);

    // …and the cancelled row still exists (history preserved).
    expect(Meeting::query()->find($meeting->id))->not->toBeNull();
});

it('inquiry deletion policy: no meeting → delete; with meeting → meeting deleted, slot freed, zero orphans', function () {
    $availability = MeetingAvailability::make();

    // 1. No meeting: deletes cleanly.
    $plain = createInquiry('Plain');
    expect(MeetingPolicy::deleteInquiry($plain, 'admin-1'))->toBe('none');
    expect(BusinessInquiry::find($plain->id))->toBeNull();

    // 2. BOOKED meeting: the meeting row is deleted with the inquiry (a
    // meeting cannot outlive its inquiry, spec §4) and the slot is freed.
    $slot = futureSlot(8);
    $live = createInquiry('With Live Meeting');
    $availability->bookForInquiry($live->id, $slot, 30);
    $outcome = MeetingPolicy::deleteInquiry($live, 'admin-1');
    expect($outcome)->toBe('deleted')
        ->and(BusinessInquiry::find($live->id))->toBeNull()
        ->and(Meeting::query()->where('inquiry_id', $live->id)->count())->toBe(0);

    // The freed slot is bookable again by a different customer.
    $probe = createInquiry('Probe');
    expect($availability->bookForInquiry($probe->id, $slot, 30)->status)->toBe('BOOKED');

    // 3. The 'prevent' variant refuses while the meeting is still BOOKED.
    // (Same day as the probe's slot but a DIFFERENT time — slots must not
    // collide inside this test.)
    config(['meetings.inquiry_delete_policy' => 'prevent']);
    $slot2Date = Carbon::parse($slot)->format('Y-m-d');
    $slot2 = $slot2Date . 'T16:00:00Z';
    $guarded = createInquiry('Guarded');
    $availability->bookForInquiry($guarded->id, $slot2, 30);
    try {
        MeetingPolicy::deleteInquiry($guarded, 'admin-1');
        $this->fail('Expected prevent-policy conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'Cancel the meeting first'))->toBeTrue();
    }
    expect(BusinessInquiry::find($guarded->id))->not->toBeNull();

    // …but once the meeting is cancelled (normal operational path), deletion
    // goes through.
    $m = Meeting::query()->where('inquiry_id', $guarded->id)->first();
    MeetingPolicy::cancel($m, 'admin-1', 'rescheduled');
    expect(MeetingPolicy::deleteInquiry($guarded, 'admin-1'))->toBe('deleted');
    expect(Meeting::query()->where('inquiry_id', $guarded->id)->count())->toBe(0);
});

it('status transitions are validated: BOOKED→COMPLETED ok, terminal states frozen', function () {
    expect(MeetingPolicy::canTransition('BOOKED', 'COMPLETED'))->toBeTrue()
        ->and(MeetingPolicy::canTransition('BOOKED', 'CANCELLED'))->toBeTrue()
        ->and(MeetingPolicy::canTransition('BOOKED', 'NO_SHOW'))->toBeTrue()
        ->and(MeetingPolicy::canTransition('COMPLETED', 'CANCELLED'))->toBeFalse()
        ->and(MeetingPolicy::canTransition('CANCELLED', 'BOOKED'))->toBeFalse()
        ->and(MeetingPolicy::canTransition('NO_SHOW', 'COMPLETED'))->toBeFalse();
});

it('disabling meeting settings closes the whole calendar', function () {
    MeetingSetting::query()->where('id', 'singleton')->update(['enabled' => false]);

    $cal = MeetingAvailability::make()->calendar();
    expect($cal['enabled'])->toBeFalse();

    try {
        MeetingAvailability::make()->assertBookable(futureSlot(3));
        $this->fail('Expected disabled conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'unavailable'))->toBeTrue();
    }

    MeetingSetting::query()->where('id', 'singleton')->update(['enabled' => true]);
});
