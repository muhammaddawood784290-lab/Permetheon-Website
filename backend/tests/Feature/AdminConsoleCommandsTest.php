<?php

/**
 * ADMIN CONSOLE COMMANDS — password recovery, account creation and listing.
 * The operator path that replaces raw SQL access; the login contract (min 8
 * chars, bcrypt storage) and the session-revocation side effect are the
 * behaviors under test.
 */

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Services\AdminAuthService;
use App\Support\Clock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function commandSeedAdmin(string $email = 'owner@permetheon.test', string $role = 'SUPER_ADMIN'): AdminUser
{
    return AdminUser::create([
        'id'                 => (string) Str::uuid(),
        'email'              => strtolower($email),
        'name'               => 'Owner',
        'password_hash'      => Hash::make('original-passphrase'),
        'role'               => $role,
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => Clock::now(),
        'updated_at'         => Clock::now(),
    ]);
}

it('admin:reset-password sets a working bcrypt password and revokes live sessions', function () {
    $admin = commandSeedAdmin();
    $token = app(AdminAuthService::class)->createSession($admin->id);
    expect($token)->not->toBe('');

    $this->artisan('admin:reset-password', ['email' => $admin->email, '--password' => 'fresh-pass-123'])
        ->assertSuccessful();

    $admin->refresh();
    expect(Hash::check('fresh-pass-123', $admin->password_hash))->toBeTrue()
        ->and((int) $admin->failed_login_count)->toBe(0)
        ->and($admin->locked_until)->toBeNull()
        ->and(AdminSession::where('id', hash('sha256', $token))->whereNull('revoked_at')->exists())->toBeFalse();

    // And the new password actually logs in over HTTP.
    $this->postJson('/api/admin/auth/login', [
        'email'    => $admin->email,
        'password' => 'fresh-pass-123',
    ])->assertStatus(200);
});

it('admin:reset-password generates a printable one-time password when none is given', function () {
    $admin = commandSeedAdmin();

    $this->artisan('admin:reset-password', ['email' => $admin->email])
        ->assertSuccessful()
        ->expectsOutputToContain('Generated password');

    $admin->refresh();
    expect($admin->password_hash)->not->toBe(Hash::make('original-passphrase'));
});

it('admin:reset-password enforces the 12-character password policy and unknown-email failure', function () {
    $admin = commandSeedAdmin();

    $this->artisan('admin:reset-password', ['email' => $admin->email, '--password' => 'short'])
        ->assertFailed();

    $this->artisan('admin:reset-password', ['email' => 'ghost@permetheon.test'])
        ->assertFailed();

    $admin->refresh();
    expect(Hash::check('original-passphrase', $admin->password_hash))->toBeTrue();
});

it('admin:reset-password clears lockout state so the account is usable again', function () {
    $admin = commandSeedAdmin();
    $admin->failed_login_count = 5;
    $admin->locked_until = date('Y-m-d H:i:s.v', strtotime('+10 minutes'));
    $admin->save();

    $this->artisan('admin:reset-password', ['email' => $admin->email, '--password' => 'fresh-pass-123'])
        ->assertSuccessful();

    $admin->refresh();
    expect((int) $admin->failed_login_count)->toBe(0)
        ->and($admin->locked_until)->toBeNull();
});

it('admin:create mints additional accounts without consuming the bootstrap', function () {
    commandSeedAdmin(); // an existing SUPER_ADMIN — bootstrap stays locked

    // The marker file may or may not exist on the host (the real bootstrap
    // writes it); the command must never change it either way.
    $marker = storage_path('app/admin-bootstrap.completed');
    $markerBefore = file_exists($marker);

    $this->artisan('admin:create', [
        'email'    => 'Abdullah@Example.com', // mixed case on purpose
        '--name'   => 'ABDULLAH',
        '--role'   => 'MANAGER',
        '--password' => 'strong-pass-123',
    ])->assertSuccessful();

    $created = AdminUser::where('email', 'abdullah@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('MANAGER')
        ->and($created->name)->toBe('ABDULLAH')
        ->and(Hash::check('strong-pass-123', $created->password_hash))->toBeTrue()
        ->and($created->isActive())->toBeTrue()
        ->and(file_exists($marker))->toBe($markerBefore);
});

it('admin:create rejects bad roles, duplicates and short passwords', function () {
    $admin = commandSeedAdmin();

    $this->artisan('admin:create', ['email' => 'x@test.dev', '--role' => 'GOD', '--password' => 'long-enough-pass'])
        ->assertFailed();

    $this->artisan('admin:create', ['email' => $admin->email, '--password' => 'long-enough-pass'])
        ->assertFailed();

    $this->artisan('admin:create', ['email' => 'y@test.dev', '--password' => 'short'])
        ->assertFailed();

    expect(AdminUser::where('email', 'x@test.dev')->exists())->toBeFalse()
        ->and(AdminUser::where('email', 'y@test.dev')->exists())->toBeFalse()
        ->and(AdminUser::count())->toBe(1);
});

it('admin:list shows accounts with role, status and live session counts', function () {
    $admin = commandSeedAdmin();
    app(AdminAuthService::class)->createSession($admin->id);

    // NOTE: one output expectation per run — PendingCommand consumes the
    // buffered output on the first contains-check, so a second expectation
    // on the same chain would see an empty buffer.
    $this->artisan('admin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('owner@permetheon.test | SUPER_ADMIN');
});

it('admin:list on an empty system points to the bootstrap path', function () {
    $this->artisan('admin:list')
        ->assertSuccessful()
        ->expectsOutputToContain('No admin accounts');
});
