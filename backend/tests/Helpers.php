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


// ---------------------------------------------------------------------------
// SHARED ADMIN AUTHENTICATION HELPER — meeting API + timezone tests
// ---------------------------------------------------------------------------

if (! defined('M_ADMIN_EMAIL')) {
    define('M_ADMIN_EMAIL', 'owner@permetheon.test');
}

if (! defined('M_ADMIN_PASSWORD')) {
    define('M_ADMIN_PASSWORD', 'super-secret-passphrase');
}

if (! function_exists('mBootstrapAdmin')) {
    function mBootstrapAdmin(): void
    {
        if (App\Models\AdminUser::count() > 0) {
            return;
        }

        App\Models\AdminUser::create([
            'id'                 => 1,
            'email'              => M_ADMIN_EMAIL,
            'name'               => 'Administrator',
            'password_hash'      => password_hash(M_ADMIN_PASSWORD, PASSWORD_BCRYPT),
            'role'               => 'SUPER_ADMIN',
            'is_active'          => 1,
            'failed_login_count' => 0,
            'created_at'         => gmdate('Y-m-d H:i:s.v'),
            'updated_at'         => gmdate('Y-m-d H:i:s.v'),
        ]);
    }
}

if (! function_exists('mLoginAsAdmin')) {
    function mLoginAsAdmin($test): array
    {
        mBootstrapAdmin();

        $response = $test->postJson('/api/admin/auth/login', [
            'email'    => M_ADMIN_EMAIL,
            'password' => M_ADMIN_PASSWORD,
        ]);

        $response->assertStatus(200);

        $cookies = $response->headers->getCookies();
        $sessionCookie = collect($cookies)->first(
            fn ($cookie) => $cookie->getName() === 'admin_session'
        );
        $csrfCookie = collect($cookies)->first(
            fn ($cookie) => $cookie->getName() === 'admin_csrf'
        );

        return [
            'session' => $sessionCookie?->getValue(),
            'csrf'    => $csrfCookie?->getValue(),
        ];
    }
}


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
        static $counter = 0;
        $counter++;
        return App\Models\BusinessInquiry::create([
            'id'             => $counter,
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
        'city' => 'Islamabad',
        'country' => 'Pakistan',
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
