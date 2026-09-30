<?php

/**
 * PRE-LAUNCH HARDENING SET — F7 (password minimum), F4 (lockout recovery +
 * visibility), F9 (security response headers).
 *
 * F7: every site that SETS an admin password now requires 12+ chars
 * (App\Support\PasswordPolicy). Login is untouched — it verifies the stored
 * hash, so existing accounts are grandfathered.
 *
 * F4: `admin:unlock` clears a lockout the operator chooses to clear (the
 * failed-login lock is a deliberate DoS lever against named accounts);
 * lockouts are mirrored to the application log.
 *
 * F9: every response carries X-Frame-Options, X-Content-Type-Options,
 * Referrer-Policy and a CSP; HSTS is stamped only on HTTPS responses
 * (scheme-gated like the F2 cookie derivation).
 */

use App\Models\AdminUser;
use App\Support\Clock;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------------- F7 ---

it('F7: PasswordPolicy requires 12 characters and carries the contract message', function () {
    expect(PasswordPolicy::isValid('abcdefghijk'))->toBeFalse() // exactly 11
        ->and(PasswordPolicy::isValid('abcdefghijkl'))->toBeTrue() // exactly 12
        ->and(PasswordPolicy::MIN_LENGTH)->toBe(12)
        ->and(PasswordPolicy::MESSAGE)->toBe('Password must be at least 12 characters.');
});

it('F7: the users API rejects an 11-char password with the pinned message', function () {
    ownerFixtureForHardening();
    $owner = hardeningLogin($this, 'owner@permetheon.test', 'hardening-passphrase');

    hardeningAuth($this, $owner)
        ->postJson('/api/admin/users', [
            'email' => 'pw11@users.test', 'name' => 'Pw', 'role' => 'ADMIN', 'password' => 'abcdefghijk',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.password', 'Password must be at least 12 characters.');

    // 12 chars passes:
    hardeningAuth($this, $owner)
        ->postJson('/api/admin/users', [
            'email' => 'pw12@users.test', 'name' => 'Pw', 'role' => 'ADMIN', 'password' => 'abcdefghijkl',
        ])
        ->assertStatus(201);
});

it('F7: admin:create and admin:reset-password enforce the same minimum', function () {
    ownerFixtureForHardening();

    $this->artisan('admin:create', [
        'email'      => 'pwconsole@permetheon.test',
        '--role'     => 'ADMIN',
        '--password' => 'only-eleven',
    ])->expectsOutputToContain('Password must be at least 12 characters.')
        ->assertFailed();

    $this->artisan('admin:create', [
        'email'      => 'pwconsole@permetheon.test',
        '--role'     => 'ADMIN',
        '--password' => 'twelve-exactly',
    ])->assertSuccessful();

    // reset-password on the existing owner: 11 rejected, hash unchanged...
    $before = AdminUser::where('email', 'owner@permetheon.test')->first()->password_hash;
    $this->artisan('admin:reset-password', ['email' => 'owner@permetheon.test', '--password' => 'abcdefghijk'])
        ->assertFailed();
    expect(AdminUser::where('email', 'owner@permetheon.test')->first()->password_hash)->toBe($before);

    // ...12 accepted.
    $this->artisan('admin:reset-password', ['email' => 'owner@permetheon.test', '--password' => 'abcdefghijkl'])
        ->assertSuccessful();
});

it('F7: bootstrap still accepts grandfathered 8+ char credentials (login verifies the hash, not the policy)', function () {
    // Simulate a pre-existing 8-char account: login must NOT be affected by
    // the new set-time policy.
    $admin = ownerFixtureForHardening();
    $admin->password_hash = Hash::make('old-8char');
    $admin->save();

    $this->postJson('/api/admin/auth/login', ['email' => 'owner@permetheon.test', 'password' => 'old-8char'])
        ->assertStatus(200);
});

// ---------------------------------------------------------------- F4 ---

it('F4: admin:unlock clears an active lock so the account can log in again', function () {
    $admin = ownerFixtureForHardening();
    $admin->failed_login_count = 5;
    $admin->locked_until = Clock::offset(15 * 60);
    $admin->save();

    Log::shouldReceive('warning')->atLeast()->once();

    $this->artisan('admin:unlock', ['email' => 'owner@permetheon.test'])
        ->expectsOutputToContain('Lock cleared')
        ->assertSuccessful();

    $admin->refresh();
    expect($admin->locked_until)->toBeNull()
        ->and((int) $admin->failed_login_count)->toBe(0);

    // Login works immediately after the unlock.
    $this->postJson('/api/admin/auth/login', ['email' => 'owner@permetheon.test', 'password' => 'hardening-passphrase'])
        ->assertStatus(200);
});

it('F4: the lock engages after 5 failures and is visible in the log; unknown email fails cleanly', function () {
    $admin = ownerFixtureForHardening();

    // Spy (not a mock): the unlock command ALSO logs 'admin.lockout.cleared'
    // and a strict mock would reject it. The spy records and asserts.
    Log::spy();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/admin/auth/login', ['email' => 'owner@permetheon.test', 'password' => 'wrong'])
            ->assertStatus(401);
    }
    expect($admin->refresh()->locked_until)->not->toBeNull();

    Log::assertHaveReceived('warning', 'admin.lockout.engaged', \Mockery::on(function ($ctx) {
        return $ctx['email'] === 'owner@permetheon.test' && $ctx['lock_minutes'] === 15;
    }));

    $this->artisan('admin:unlock', ['email' => 'ghost@permetheon.test'])
        ->expectsOutputToContain('No admin account found')
        ->assertFailed();

    // First unlock clears the active lock...
    $this->artisan('admin:unlock', ['email' => 'owner@permetheon.test'])
        ->expectsOutputToContain('Lock cleared')
        ->assertSuccessful();

    // ...a SECOND unlock on the now-unlocked account is a clean no-op.
    $this->artisan('admin:unlock', ['email' => 'owner@permetheon.test'])
        ->expectsOutputToContain('not locked')
        ->assertSuccessful();
});

// ---------------------------------------------------------------- F9 ---

it('F9: every API response carries the security headers', function () {
    $response = $this->getJson('/api/definitely/not/a/route'); // web SPA-host JSON 404

    $response->assertStatus(404)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('F9: HSTS is stamped on HTTPS responses and absent over plain HTTP', function () {
    // Plain HTTP + http APP_URL (test defaults): no HSTS.
    $plain = $this->getJson('/api/definitely/not/a/route');
    expect($plain->headers->get('Strict-Transport-Security'))->toBeNull();

    // Absolute https:// URI simulates an HTTPS request (see AdminCookieSecureFlagTest).
    $secure = $this->getJson('https://localhost/api/definitely/not/a/route');
    expect($secure->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('F9: an https APP_URL stamps HSTS even when the request arrives over plain HTTP (TLS on the proxy)', function () {
    config(['app.url' => 'https://permetheon.example']);

    $response = $this->getJson('/api/definitely/not/a/route');
    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

// ------------------------------------------------------------- helpers ---

function ownerFixtureForHardening(): AdminUser
{
    $existing = AdminUser::where('email', 'owner@permetheon.test')->first();
    if ($existing) {
        return $existing;
    }

    return AdminUser::create([
        'id'                 => (string) Illuminate\Support\Str::uuid(),
        'email'              => 'owner@permetheon.test',
        'name'               => 'Owner',
        'password_hash'      => Hash::make('hardening-passphrase'),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => Clock::now(),
        'updated_at'         => Clock::now(),
    ]);
}

function hardeningLogin($test, string $email, string $password): array
{
    $response = $test->postJson('/api/admin/auth/login', ['email' => $email, 'password' => $password]);
    $response->assertStatus(200);
    $cookies = collect($response->headers->getCookies());

    return [
        'session' => $cookies->first(fn ($c) => $c->getName() === 'admin_session')?->getValue(),
        'csrf'    => $cookies->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue(),
    ];
}

function hardeningAuth($test, array $auth)
{
    return $test->withCredentials()->withUnencryptedCookies([
        'admin_session' => $auth['session'],
        'admin_csrf'    => $auth['csrf'],
    ])->withHeaders(['x-csrf-token' => $auth['csrf']]);
}
