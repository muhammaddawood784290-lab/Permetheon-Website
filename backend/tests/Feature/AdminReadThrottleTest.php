<?php

/**
 * F6 — THE ADMIN-READ LIMITER IS WIRED (docs/ADMIN_AUTH_REVIEW.md, F6).
 *
 * `RateLimiter::for('admin-read', …)` (120/min, keyed by the resolved admin
 * id after admin.guard) was registered but referenced by NO route. It is now
 * attached to every admin GET route. These tests pin the contract:
 *
 *   - 121st read inside one minute → 429 RATE_LIMITED envelope (Retry-After kept);
 *   - the bucket is PER-ADMIN and SHARED across admin read routes — one
 *     admin's hammering cannot exhaust another admin's budget;
 *   - WRITE routes are deliberately NOT governed by it (their abuse budget
 *     is the per-route throttle + CSRF + permission gate);
 *   - unauthenticated requests (no admin → no key) are untouched: they get
 *     the plain 401, never 429.
 */

const F6_EMAIL_A = 'owner@permetheon.test';
const F6_EMAIL_B = 'second@permetheon.test';
const F6_PASSWORD = 'super-secret-passphrase';

function f6CreateAdmin(string $email): void
{
    App\Models\AdminUser::create([
        'id'                 => Illuminate\Support\Str::uuid()->toString(),
        'email'              => strtolower($email),
        'name'               => 'Admin ' . $email,
        'password_hash'      => Illuminate\Support\Facades\Hash::make(F6_PASSWORD),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => App\Support\Clock::now(),
        'updated_at'         => App\Support\Clock::now(),
    ]);
}

function f6Login($test, string $email): array
{
    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => $email,
        'password' => F6_PASSWORD,
    ]);
    $response->assertStatus(200);

    $cookies = $response->headers->getCookies();

    return [
        'session' => collect($cookies)->first(fn ($c) => $c->getName() === 'admin_session')?->getValue(),
        'csrf'    => collect($cookies)->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue(),
    ];
}

function f6Get($test, array $cookies, string $path)
{
    return $test->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
        'admin_csrf'    => $cookies['csrf'],
    ])->getJson($path);
}

it('F6: admin reads throttle at 120/min with a per-admin, route-shared bucket', function () {
    f6CreateAdmin(F6_EMAIL_A);
    f6CreateAdmin(F6_EMAIL_B);
    $a = f6Login($this, F6_EMAIL_A);
    $b = f6Login($this, F6_EMAIL_B);

    // Exactly 120 reads are allowed inside the window.
    for ($i = 0; $i < 120; $i++) {
        f6Get($this, $a, '/api/admin/inquiries')->assertStatus(200);
    }

    // The 121st is throttled — pinned envelope, Retry-After preserved.
    $throttled = f6Get($this, $a, '/api/admin/inquiries');
    $throttled->assertStatus(429);
    expect($throttled->json('error.code'))->toBe('RATE_LIMITED')
        ->and($throttled->json('error.message'))->toBe('Too many requests. Please try again shortly.')
        ->and($throttled->headers->get('Retry-After'))->not->toBeNull();

    // The budget is per-ADMIN and shared across the admin read routes: the
    // same admin is throttled on a different read route too...
    f6Get($this, $a, '/api/admin/users')->assertStatus(429);

    // ...while another admin's bucket is untouched (per-admin keying).
    f6Get($this, $b, '/api/admin/inquiries')->assertStatus(200);
    f6Get($this, $b, '/api/admin/users')->assertStatus(200);
});

it('F6: admin WRITE routes are not governed by the admin-read limiter', function () {
    f6CreateAdmin(F6_EMAIL_A);
    $a = f6Login($this, F6_EMAIL_A);

    // 125 write attempts — over the read limit, so a mis-wired limiter would
    // 429 them. Writes are PATCH/DELETE/POST with their own gates (CSRF +
    // permission), each answering 404 here (target does not exist) — never 429.
    for ($i = 0; $i < 125; $i++) {
        $this->withCredentials()->withUnencryptedCookies([
            'admin_session' => $a['session'],
            'admin_csrf'    => $a['csrf'],
        ])->withHeaders(['x-csrf-token' => $a['csrf']])
            ->patchJson('/api/admin/inquiries/does-not-exist', ['status' => 'REVIEWING'])
            ->assertStatus(404);
    }

    // The read bucket was never touched by the writes: the first read still passes.
    f6Get($this, $a, '/api/admin/inquiries')->assertStatus(200);
});

it('F6: unauthenticated requests get the plain 401 — no limiter interference', function () {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->getJson('/api/admin/inquiries');
        $response->assertStatus(401);
        expect($response->json('error.code'))->toBe('UNAUTHORIZED');
    }
});
