<?php

use App\Models\BusinessInquiry;
use App\Models\Meeting;
use App\Models\MeetingSetting;
use App\Services\MeetingAvailability;
use App\Support\MeetingTime;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| TIMEZONE NEUTRALITY SUITE — UTC is the only canonical time standard
|--------------------------------------------------------------------------
| Proves the meeting system is geographically neutral:
|   1. bookings store the exact UTC instant the client sent (simulated UTC
|      environment — the PHP process TZ is forced around the booking),
|   2. conflict detection operates on UTC instants (different request-side
|      TZ assumptions still collide),
|   3. every API datetime is strict ISO 8601 UTC with the 'Z' suffix,
|   4. changing the SERVER timezone (config/app.php + php date.default)
|      does not shift existing bookings or availability,
|   5. no geographic timezone (Asia/Karachi, PKT, …) exists anywhere in the
|      meeting implementation.
*/

const GEO_ZONE_PATTERN = '/Asia\/Karachi|Karachi|PKT|Pakistan Standard Time/i';

it('stores the exact UTC instant regardless of the PHP process timezone (server-TZ independence)', function () {
    $slot = futureSlot(9, '10:00'); // 2026-XX-XXT10:00:00Z
    $storageBefore = MeetingTime::normalize($slot);
    $expectedWall = Carbon::parse($slot, 'UTC')->format('Y-m-d H:i');

    $availability = MeetingAvailability::make();

    // Simulate a server moved to a UTC+5 machine: the booking must still
    // store the exact same UTC instant.
    $previous = date_default_timezone_get();
    try {
        date_default_timezone_set('Asia/Karachi'); // process-local only — canonical clock ignores it
        config(['app.timezone' => 'Asia/Karachi']);
        $availability->bookForInquiry(
            BusinessInquiry::create([
                'id'             => (string) Illuminate\Support\Str::uuid(),
                'name'           => 'TZ Shift A',
                'company'        => '',
                'email'          => 'tz-a@example.com',
                'contact_number' => '+12025550147',
                'project_type'   => 'Web Application',
                'budget'         => '',
                'timeline'       => '',
                'message'        => 'Booking while the server pretends to be in Karachi.',
                'status'         => 'NEW',
                'priority'       => 'MEDIUM',
                'created_at'     => App\Support\Clock::now(),
                'updated_at'     => App\Support\Clock::now(),
            ])->id,
            $slot,
            30,
        );
    } finally {
        date_default_timezone_set($previous);
        config(['app.timezone' => 'UTC']);
    }

    $meeting = Meeting::query()->where('starts_at', $storageBefore)->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->starts_at->format('Y-m-d H:i'))->toBe($expectedWall)
        ->and($meeting->starts_at->equalTo(Carbon::parse($slot, 'UTC')))->toBeTrue();
});

it('conflict detection uses UTC instants: same instant from any requester side collides', function () {
    $slot = futureSlot(15, '11:00');
    $availability = MeetingAvailability::make();
    $a = createInquiry('UTC Book');
    $availability->bookForInquiry($a->id, $slot, 30);

    // The same instant written with an explicit +05:00 offset MUST resolve to
    // the same UTC instant and therefore conflict.
    $sameInstantOffsetForm = Carbon::parse($slot, 'UTC')->tz('Asia/Karachi')->format('Y-m-d\TH:i:sP');
    $storage = MeetingTime::normalize($slot);

    $b = createInquiry('Offset Book');
    try {
        $availability->bookForInquiry($b->id, $sameInstantOffsetForm, 30);
        $this->fail('Expected the offset-written same-instant booking to conflict.');
    } catch (\App\Services\MeetingConflictException $e) {
        expect(str_contains($e->getMessage(), 'just been taken'))->toBeTrue();
    }

    // And no second row was created for the slot.
    expect(Meeting::query()->where('starts_at', $storage)->whereIn('status', Meeting::SLOT_HOLDING)->count())->toBe(1);
});

it('API responses always carry strict ISO 8601 UTC timestamps with Z', function () {
    $slot = futureSlot(16, '13:00');
    $book = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'wire-format@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $book->assertStatus(201);
    expect($book->json('data.meeting.startsAt'))->toBe($slot)
        ->and(MeetingTime::isWireFormat((string) $book->json('data.meeting.startsAt')))->toBeTrue();

    // Public calendar: every slot start/end is strict ISO 8601 UTC.
    $cal = $this->getJson('/api/meetings/availability')->json('data.availability');
    expect($cal['timezone'])->toBe('UTC');
    foreach ($cal['days'] as $day) {
        foreach ($day['slots'] as $s) {
            expect(MeetingTime::isWireFormat($s['startsAt']))->toBeTrue("slot startsAt {$s['startsAt']} must be ISO 8601 UTC (Z)");
            expect(MeetingTime::isWireFormat($s['endsAt']))->toBeTrue("slot endsAt {$s['endsAt']} must be ISO 8601 UTC (Z)");
            expect(str_starts_with($s['startsAt'], $day['date'] . 'T'))->toBeTrue('slot instant must belong to its UTC day');
        }
    }

    // Admin list: the joined meeting rows are ISO 8601 UTC too.
    $cookies = mLoginAsAdmin($this);
    $list = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
    ])->getJson('/api/admin/meetings');
    $list->assertStatus(200);
    foreach ($list->json('data.meetings') as $m) {
        expect(MeetingTime::isWireFormat((string) $m['startsAt']))->toBeTrue('admin startsAt must be ISO 8601 UTC (Z)');
    }
});

it('server timezone relocation does not change existing booking timestamps or the calendar', function () {
    $slot = futureSlot(17, '14:00');
    $availability = MeetingAvailability::make();
    $a = createInquiry('Relocation Book');
    $availability->bookForInquiry($a->id, $slot, 30);
    $storage = MeetingTime::normalize($slot);

    $calBefore = $this->getJson('/api/meetings/availability')->json('data.availability');

    $previous = date_default_timezone_get();
    try {
        // "Move the server" across the planet mid-flight.
        date_default_timezone_set('America/New_York');
        config(['app.timezone' => 'America/New_York']);

        $row = Meeting::query()->where('starts_at', $storage)->first();
        expect($row)->not->toBeNull()
            ->and($row->starts_at->equalTo(Carbon::parse($slot, 'UTC')))->toBeTrue();

        $calAfter = $this->getJson('/api/meetings/availability')->json('data.availability');
        $daySlots = collect($calAfter['days'])->firstWhere('date', substr($slot, 0, 10))['slots'] ?? [];
        expect($calAfter['timezone'])->toBe('UTC')
            // The day the booking lives on still exposes it at the same UTC instant.
            ->and(collect($daySlots)->contains(fn ($s) => $s['startsAt'] === $slot && $s['status'] === 'BOOKED'))->toBeTrue();
    } finally {
        date_default_timezone_set($previous);
        config(['app.timezone' => 'UTC']);
    }

    // Date boundaries did not shift: the booking is on the same UTC date.
    expect(substr($storage, 0, 10))->toBe(substr($slot, 0, 10));
});

it('availability does not depend on client or server local timezone', function () {
    // The calendar is derived from MeetingTime::now() (UTC) and UTC-only
    // settings — the values below must hold on ANY machine.
    $cal = MeetingAvailability::make()->calendar();
    expect($cal['timezone'])->toBe('UTC')
        ->and(MeetingSetting::current()->workingDays())->toBe([1, 2, 3, 4, 5]);

    $open = collect($cal['days'])->first(fn ($d) => $d['status'] === 'OPEN');
    expect($open)->not->toBeNull()
        ->and($open['slots'][0]['startsAt'])->toEndWith('T09:00:00Z');
});

it('no geographic timezone appears anywhere in the meeting implementation', function () {
    $roots = [
        app_path('Services'),
        app_path('Http/Controllers'),
        app_path('Models'),
        app_path('Support'),
        config_path('meetings.php'),
        database_path('migrations'),
        base_path('routes'),
    ];

    $offenders = [];
    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'json'], true)) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && preg_match(GEO_ZONE_PATTERN, $contents) === 1) {
                $offenders[] = $file->getPathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});
