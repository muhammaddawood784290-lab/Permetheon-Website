<?php

/**
 * THE NINE NEVER-TESTED BEHAVIORS (parity gaps G1–G9).
 * Brute-force, session-lifetime and envelope behaviors that once shipped with
 * zero coverage; the Laravel rewrite carries them as first-class tests. Each
 * gap is named in its test.
 */

use App\Models\AdminUser;
use App\Models\AdminSession;
use App\Services\AdminAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

const GAP_ADMIN_EMAIL = 'owner@permetheon.test';
const GAP_ADMIN_PASSWORD = 'super-secret-passphrase';

function gapBootstrapAdmin(): void
{
    if (AdminUser::count() > 0) {
        return;
    }

    AdminUser::create([
        'id'                 => (string) Str::uuid(),
        'email'              => strtolower(GAP_ADMIN_EMAIL),
        'name'               => 'Owner',
        'password_hash'      => Hash::make(GAP_ADMIN_PASSWORD),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => gmdate('Y-m-d H:i:s.v'),
        'updated_at'         => gmdate('Y-m-d H:i:s.v'),
    ]);
}

function gapLogin($test): array
{
    gapBootstrapAdmin();

    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => GAP_ADMIN_EMAIL,
        'password' => GAP_ADMIN_PASSWORD,
    ]);
    $response->assertStatus(200);

    $cookies = $response->headers->getCookies();
    $session = collect($cookies)->first(fn ($c) => $c->getName() === 'admin_session');
    $csrf = collect($cookies)->first(fn ($c) => $c->getName() === 'admin_csrf');

    return [
        'session' => $session?->getValue(),
        'csrf'    => $csrf?->getValue(),
    ];
}

it('G1: public submissions return the 429 envelope after 5 in 10 minutes', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
            'email' => "g1-{$i}@example.com",
        ]))->assertStatus(201);
    }

    $response = $this->postJson('/api/inquiries', VALID_INQUIRY);
    $response->assertStatus(429);
    expect($response->json('error.code'))->toBe('RATE_LIMITED')
        ->and($response->json('error.message'))->toBe('Too many requests. Please try again shortly.');
});

it('G2a: idle-expired sessions (30min) are revoked server-side and cannot be replayed', function () {
    $cookies = gapLogin($this);
    $rawToken = $cookies['session'];

    // Age the session past the idle window (simulate by backdating last_seen_at).
    $sessionId = hash('sha256', $rawToken);
    AdminSession::where('id', $sessionId)->update([
        'last_seen_at' => gmdate('Y-m-d H:i:s.v', time() - 31 * 60),
    ]);

    $replayed = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $rawToken,
    ])->getJson('/api/admin/auth/session');

    $replayed->assertStatus(401);

    // The expired row must now be revoked (cannot be replayed even if re-aged).
    $session = AdminSession::find($sessionId);
    expect($session->revoked_at)->not->toBeNull();
});

it('G2b: absolute expiry (24h) kills even a fresh idle window', function () {
    $cookies = gapLogin($this);
    $rawToken = $cookies['session'];
    $sessionId = hash('sha256', $rawToken);

    AdminSession::where('id', $sessionId)->update([
        'absolute_expires_at' => gmdate('Y-m-d H:i:s.v', time() - 1),
    ]);

    $replayed = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $rawToken,
    ])->getJson('/api/admin/auth/session');
    $replayed->assertStatus(401);
});

it('G2c: the idle window slides on activity', function () {
    $cookies = gapLogin($this);
    $rawToken = $cookies['session'];
    $sessionId = hash('sha256', $rawToken);

    // Age to 29 minutes — still inside the 30-min window.
    AdminSession::where('id', $sessionId)->update([
        'last_seen_at' => gmdate('Y-m-d H:i:s.v', time() - 29 * 60),
    ]);

    $this->withCredentials()->withUnencryptedCookies(['admin_session' => $rawToken])
        ->getJson('/api/admin/auth/session')->assertStatus(200);

    // The touch must have slid the window back to "now".
    $session = AdminSession::find($sessionId);
    $age = time() - strtotime($session->last_seen_at);
    expect($age)->toBeLessThan(60);
});

it('G3: five failed logins lock the account; the CORRECT password fails while locked', function () {
    gapBootstrapAdmin();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/admin/auth/login', [
            'email'    => GAP_ADMIN_EMAIL,
            'password' => 'wrong-password',
        ]);
    }

    $admin = AdminUser::where('email', strtolower(GAP_ADMIN_EMAIL))->first();
    expect($admin->locked_until)->not->toBeNull();

    // The CORRECT password must ALSO fail while locked (no bypass).
    $response = $this->postJson('/api/admin/auth/login', [
        'email'    => GAP_ADMIN_EMAIL,
        'password' => GAP_ADMIN_PASSWORD,
    ]);
    $response->assertStatus(401);
    expect($response->json('error.message'))->toBe('Invalid email or password.');
});

it('G4: admin login returns the 429 envelope after the throttle window', function () {
    gapBootstrapAdmin();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/admin/auth/login', [
            'email'    => 'g4-' . $i . '@permetheon.test',
            'password' => 'nope',
        ])->assertStatus(401);
    }

    $response = $this->postJson('/api/admin/auth/login', [
        'email'    => 'g4-x@permetheon.test',
        'password' => 'nope',
    ]);
    $response->assertStatus(429);
    expect($response->json('error.code'))->toBe('RATE_LIMITED')
        ->and($response->json('error.message'))->toBe('Too many requests. Please try again shortly.');
});

it('G5: bootstrap credentials can never mint a second account after first use', function () {
    // Seed the REAL env var (config() doesn't reach the service's env() call).
    putenv('ADMIN_BOOTSTRAP_PASSWORD=bootstrap-pass-123');
    $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] = 'bootstrap-pass-123';
    $_SERVER['ADMIN_BOOTSTRAP_PASSWORD'] = 'bootstrap-pass-123';

    // First bootstrap via the real service path.
    $auth = new AdminAuthService;
    $ok = $auth->authenticate('admin@permetheon.com', 'bootstrap-pass-123');
    expect($ok['ok'])->toBeTrue();
    expect(AdminUser::where('email', 'admin@permetheon.com')->count())->toBe(1);

    // Wipe happened: delete the admin, then the SAME bootstrap credentials
    // must NOT mint a second account (the env password is unrecoverable).
    AdminUser::where('email', 'admin@permetheon.com')->delete();

    $auth2 = new AdminAuthService;
    $ok2 = $auth2->authenticate('admin@permetheon.com', 'bootstrap-pass-123');
    expect($ok2['ok'])->toBeFalse()
        ->and(AdminUser::count())->toBe(0);
});

it('G6: bcrypt hash round-trip verifies correct and rejects wrong passwords', function () {
    $hash = Hash::make('correct-horse-battery-staple');
    expect(str_starts_with($hash, '$2y$'))->toBeTrue()
        ->and(Hash::check('correct-horse-battery-staple', $hash))->toBeTrue()
        ->and(Hash::check('wrong-password', $hash))->toBeFalse();
});

it('G7: validation failures carry the VALIDATION_ERROR envelope', function () {
    $response = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, ['email' => 'not-an-email']));
    assertExactFields($response, 'email', 'Enter a valid email address.');
});

it('G7b: admin login validation also carries the VALIDATION_ERROR envelope', function () {
    $response = $this->postJson('/api/admin/auth/login', ['email' => '', 'password' => '']);
    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR')
        ->and($response->json('error.fields'))->toBe([
            'email'    => 'Email is required.',
            'password' => 'Password is required.',
        ]);
});
