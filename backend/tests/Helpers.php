<?php

/*
|--------------------------------------------------------------------------
| SHARED TEST HELPERS — parity suite (§4b)
|--------------------------------------------------------------------------
| Canonical fixtures used across Feature tests. VALID_INQUIRY mirrors the
| TS contract test's valid submission 1:1 (including +1 202 555 0147).
*/

// ---------------------------------------------------------------------------
// MEETING SUITE HELPERS (shared by the engine + HTTP meeting tests)
// ---------------------------------------------------------------------------

if (! function_exists('futureSlot')) {
    /** A business-hours slot N days ahead, walked forward to a configured working day.
     * Returns the ISO 8601 UTC wire format ('2026-10-05T15:00:00Z') — UTC is
     * the only canonical time standard for the meeting system. */
    function futureSlot(int $daysAhead, string $time = '15:00'): string
    {
        $date = Illuminate\Support\Carbon::now('UTC')->addDays($daysAhead);

        // Walk forward until a configured working day (Mon..Fri).
        while (in_array($date->dayOfWeek, [Illuminate\Support\Carbon::SATURDAY, Illuminate\Support\Carbon::SUNDAY], true)) {
            $date->addDay();
        }

        return $date->format('Y-m-d') . 'T' . $time . ':00Z';
    }
}

if (! function_exists('createInquiry')) {
    function createInquiry(string $name = 'Meeting Tester'): App\Models\BusinessInquiry
    {
        return App\Models\BusinessInquiry::create([
            'id'             => (string) Illuminate\Support\Str::uuid(),
            'name'           => $name,
            'company'        => '',
            'email'          => 'meeting@example.com',
            'contact_number' => '+12025550147',
            'project_type'   => 'Web Application',
            'budget'         => '',
            'timeline'       => '',
            'message'        => 'We need a meeting slot for discovery.',
            'status'         => 'NEW',
            'priority'       => 'MEDIUM',
            'created_at'     => App\Support\Clock::now(),
            'updated_at'     => App\Support\Clock::now(),
        ]);
    }
}

if (! defined('VALID_INQUIRY')) {
    define('VALID_INQUIRY', [
        'name' => 'Moin Udden',
        'company' => '',
        'email' => 'hello@example.com',
        'contactNumber' => '+12025550147',
        'projectType' => 'Web Application',
        'budget' => '',
        'timeline' => '',
        'message' => 'We need a restaurant booking platform with a customer reservation flow.',
    ]);
}

if (! function_exists('assertExactFields')) {
    /**
     * Assert the 422 response carries EXACTLY the given field(s), with exact
     * messages where supplied (parity rows 3/4). Lives here (not in the
     * inquiry validation test) so EVERY Feature file can use it standalone —
     * file-scope helpers in one test file are invisible to another file run
     * alone (G7 regression 2026-09-30).
     */
    function assertExactFields($response, string $field, ?string $message = null): void
    {
        $response->assertStatus(422);
        $body = $response->json();
        expect($body['success'])->toBeFalse()
            ->and($body['error']['code'])->toBe('VALIDATION_ERROR') // G7
            ->and(array_keys($body['error']['fields'] ?? []))->toBe([$field]);

        if ($message !== null) {
            expect($body['error']['fields'][$field])->toBe($message);
        }
    }
}
