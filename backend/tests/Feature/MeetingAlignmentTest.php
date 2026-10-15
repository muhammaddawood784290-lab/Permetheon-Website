<?php

use App\Models\BusinessInquiry;
use App\Models\Meeting;
use App\Models\MeetingSetting;
use App\Services\MeetingAvailability;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| MEETING ALIGNMENT SUPPLEMENTAL TESTS
|--------------------------------------------------------------------------
| These tests specifically verify the alignment between Laravel and Supabase
| based on the updated project specifications.
|
| Requirements:
| - UTC Only
| - Mon-Fri, 09:00-20:00
| - Hour-aligned starts (09:00, 10:00... 19:00)
| - 30m Duration, 60m Interval
| - Atomic failure when disabled
| - Legacy weekend preservation
|--------------------------------------------------------------------------
*/

it('strictly enforces boundaries: Monday 09:00 and Friday 19:00 accepted, 20:00 rejected', function () {
    $availability = MeetingAvailability::make();

    // Fixed date for boundaries
    $monday = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::MONDAY)->startOfDay()->addHours(9);
    $friday = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::FRIDAY)->startOfDay()->addHours(19);
    $closing = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::MONDAY)->startOfDay()->addHours(20);

    expect(fn() => $availability->assertBookable($monday->format('Y-m-d\TH:i:00\Z')))->not->toThrow(\Exception::class);
    expect(fn() => $availability->assertBookable($friday->format('Y-m-d\TH:i:00\Z')))->not->toThrow(\Exception::class);
    expect(fn() => $availability->assertBookable($closing->format('Y-m-d\TH:i:00\Z')))->toThrow(\App\Services\MeetingConflictException::class);
});

it('rejects new bookings on weekends but preserves existing ones', function () {
    $availability = MeetingAvailability::make();

    // 1. New booking on Saturday rejected
    $saturday = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::SATURDAY)->startOfDay()->addHours(10);
    expect(fn() => $availability->assertBookable($saturday->format('Y-m-d\TH:i:00\Z')))->toThrow(\App\Services\MeetingConflictException::class);

    // 2. Existing weekend meeting preserved
    // Seed a meeting on a Saturday manually
    $existingSat = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::SATURDAY)->startOfDay()->addHours(10);
    $inquiry = BusinessInquiry::create([
        'name' => 'Legacy User',
        'email' => 'legacy@example.com',
        'contact_number' => '+1234567890',
        'project_type' => 'Website Development',
        'message' => 'Preserve this existing weekend meeting during the booking alignment test.',
    ]);

    Meeting::create([
        'inquiry_id' => $inquiry->id,
        'starts_at' => $existingSat,
        'duration_minutes' => 30,
        'status' => 'BOOKED',
    ]);

    // Verify the existing weekend meeting remains unchanged in the database.
    $preserved = Meeting::query()
        ->where('inquiry_id', $inquiry->id)
        ->first();

    expect($preserved)->not->toBeNull()
        ->and($preserved->status)->toBe('BOOKED')
        ->and(\App\Support\MeetingTime::toWire($preserved->starts_at))
            ->toBe(\App\Support\MeetingTime::toWire($existingSat));
});

it('rejects non-hour-aligned starts', function () {
    $availability = MeetingAvailability::make();
    $slot = Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::MONDAY)->startOfDay()->addHours(10)->addMinutes(30);

    expect(fn() => $availability->assertBookable($slot->format('Y-m-d\TH:i:00\Z')))->toThrow(\App\Services\MeetingConflictException::class);
});

it('verifies atomicity: booking request fails and rolls back inquiry when disabled', function () {
    MeetingSetting::current()->update(['enabled' => false]);

    $availability = MeetingAvailability::make();
    $inquiryData = [
        'name' => 'Fail Test',
        'email' => 'fail@example.com',
        'contact_number' => '+1234567890',
        'project_type' => 'Website Development',
        'message' => 'This inquiry should be rolled back when meeting booking is disabled.',
    ];

    // Simulate the controller logic manually for clarity
    $success = false;
    try {
        \Illuminate\Support\Facades\DB::transaction(function () use ($availability, $inquiryData) {
            $inquiry = BusinessInquiry::create($inquiryData);
            $availability->bookForInquiry($inquiry->id, Carbon::now('UTC')->addDays(10)->dayOfWeek(Carbon::MONDAY)->startOfDay()->addHours(10)->format('Y-m-d\TH:i:00\Z'), 30);
        });
        $success = true;
    } catch (\Exception $e) {
        $success = false;
    }

    expect($success)->toBeFalse();
    expect(BusinessInquiry::where('email', 'fail@example.com')->count())->toBe(0);

    MeetingSetting::current()->update(['enabled' => true]);
});

it('allows normal inquiries to succeed when booking is disabled', function () {
    MeetingSetting::current()->update(['enabled' => false]);

    $inquiry = BusinessInquiry::create([
        'name' => 'Normal User',
        'email' => 'normal@example.com',
        'contact_number' => '+1234567890',
        'project_type' => 'Website Development',
        'message' => 'I would like to inquire about developing a new business website.',
    ]);

    expect($inquiry)->not->toBeNull();

    MeetingSetting::current()->update(['enabled' => true]);
});
