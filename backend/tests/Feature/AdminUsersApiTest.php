<?php

/**
 * ADMIN USER MANAGEMENT — the admins.* surface (list, create, delete, role
 * change). The authority matrix is the contract:
 *   - SUPER_ADMIN: full control, minus self-delete/self-demote and the
 *     last-active-SUPER_ADMIN invariant;
 *   - MANAGER: reads allowed (admins.read), every write 403s at the gate;
 *   - unauthenticated: 401 everywhere.
 */

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Services\AdminAuthService;
use App\Support\Clock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

const OWNER_EMAIL = 'owner@users.test';
const OWNER_PASSWORD = 'owner-passphrase-1';
const MANAGER_EMAIL = 'manager@users.test';
const MANAGER_PASSWORD = 'manager-passphrase-1';

/**
 * Login and return the raw session + csrf tokens. Follow-up requests must use
 * withUnencryptedCookies(['admin_session' => …]) (the suite convention — the
 * test client encrypts cookies by default) plus the x-csrf-token header.
 */
function usersLoginAs($test, string $email, string $password): array
{
    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => $email,
        'password' => $password,
    ]);
    $response->assertStatus(200);

    $cookies = collect($response->headers->getCookies());
    $session = $cookies->first(fn ($c) => $c->getName() === 'admin_session')?->getValue();
    $csrf = $cookies->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue();

    return ['session' => $session, 'csrf' => $csrf];
}

/** Prime the test client with an authenticated session (cookies only). */
function usersAuth($test, array $tokens)
{
    return $test->withCredentials()->withUnencryptedCookies(['admin_session' => $tokens['session']]);
}

/** Authenticated + CSRF double-submit (cookie AND header) — for mutating requests. */
function usersAuthCsrf($test, array $tokens)
{
    return $test
        ->withCredentials()
        ->withUnencryptedCookies([
            'admin_session' => $tokens['session'],
            'admin_csrf'    => $tokens['csrf'],
        ])
        ->withHeaders(['x-csrf-token' => $tokens['csrf']]);
}

function usersSeed(string $email, string $role, string $password = 'a-string-password'): AdminUser
{
    return AdminUser::create([
        'id'                 => (string) Str::uuid(),
        'email'              => strtolower($email),
        'name'               => strtoupper(strtok($email, '@')),
        'password_hash'      => Hash::make($password),
        'role'               => $role,
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => Clock::now(),
        'updated_at'         => Clock::now(),
    ]);
}

beforeEach(function () {
    // Owner (SUPER_ADMIN) + a MANAGER for the authority matrix.
    $this->owner = usersSeed(OWNER_EMAIL, 'SUPER_ADMIN', OWNER_PASSWORD);
    $this->manager = usersSeed(MANAGER_EMAIL, 'MANAGER', MANAGER_PASSWORD);
});

it('lists accounts for admins.read — including MANAGER — without secrets', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    $response = usersAuth($this, $owner)
        ->getJson('/api/admin/users');
    $response->assertStatus(200)
        ->assertJsonMissing(['password_hash'])
        ->assertJsonStructure(['data' => ['admins' => [['id', 'email', 'name', 'role', 'liveSessions', 'createdAt']]]]);

    // Both accounts present, ordered by email (manager sorts before owner).
    $emails = collect($response->json('data.admins'))->pluck('email')->all();
    expect($emails)->toContain(OWNER_EMAIL)
        ->and($emails)->toContain(MANAGER_EMAIL)
        ->and($emails)->toBe(collect($emails)->sort()->values()->all());

    $manager = usersLoginAs($this, MANAGER_EMAIL, MANAGER_PASSWORD);
    usersAuth($this, $manager)->getJson('/api/admin/users')->assertStatus(200);
});

it('denies unauthenticated access to the list', function () {
    $this->getJson('/api/admin/users')->assertStatus(401);
});

it('creates an account as SUPER_ADMIN with bcrypt storage and normalized email', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuthCsrf($this, $owner)
        ->postJson('/api/admin/users', [
            'email'    => 'New.Person@Example.com',
            'name'     => 'New Person',
            'role'     => 'admin', // case-insensitive
            'password' => 'a-good-password',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.admin.email', 'new.person@example.com')
        ->assertJsonPath('data.admin.role', 'ADMIN');

    $created = AdminUser::where('email', 'new.person@example.com')->first();
    expect($created)->not->toBeNull()
        ->and(Hash::check('a-good-password', $created->password_hash))->toBeTrue()
        ->and($created->role)->toBe('ADMIN');
});

it('rejects creation with validation errors for bad email, role, short password, duplicates', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuthCsrf($this, $owner)
        ->postJson('/api/admin/users', [
            'email' => 'not-an-email', 'name' => '', 'role' => 'GOD', 'password' => 'short',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    // Duplicate (case-insensitive)
    usersAuthCsrf($this, $owner)
        ->postJson('/api/admin/users', [
            'email' => OWNER_EMAIL, 'name' => 'Dup', 'role' => 'ADMIN', 'password' => 'a-good-password',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.email', 'An account with this email already exists.');
});

it('denies MANAGER every write and allows only the read', function () {
    $manager = usersLoginAs($this, MANAGER_EMAIL, MANAGER_PASSWORD);

    // create → 403
    usersAuthCsrf($this, $manager)
        ->postJson('/api/admin/users', [
            'email' => 'blocked@users.test', 'name' => 'Blocked', 'role' => 'ADMIN', 'password' => 'a-good-password',
        ])
        ->assertStatus(403);

    // role change → 403
    usersAuthCsrf($this, $manager)
        ->patchJson("/api/admin/users/{$this->owner->id}/role", ['role' => 'ADMIN'])
        ->assertStatus(403);

    // delete → 403
    usersAuthCsrf($this, $manager)
        ->deleteJson("/api/admin/users/{$this->owner->id}")
        ->assertStatus(403);

    expect(AdminUser::where('email', 'blocked@users.test')->exists())->toBeFalse()
        ->and($this->owner->fresh()->role)->toBe('SUPER_ADMIN');
});

it('denies CSRF-less writes even for SUPER_ADMIN', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuth($this, $owner)
        ->postJson('/api/admin/users', [
            'email' => 'x@users.test', 'name' => 'X', 'role' => 'ADMIN', 'password' => 'a-good-password',
        ])
        ->assertStatus(403);

    usersAuth($this, $owner)
        ->patchJson("/api/admin/users/{$this->manager->id}/role", ['role' => 'ADMIN'])
        ->assertStatus(403);

    usersAuth($this, $owner)
        ->deleteJson("/api/admin/users/{$this->manager->id}")
        ->assertStatus(403);
});

it('changes roles as SUPER_ADMIN and revokes the target live sessions', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    // Give the manager a live session, then demote it away.
    app(AdminAuthService::class)->createSession($this->manager->id);
    expect(AdminSession::where('admin_id', $this->manager->id)->whereNull('revoked_at')->count())->toBe(1);

    usersAuthCsrf($this, $owner)
        ->patchJson("/api/admin/users/{$this->manager->id}/role", ['role' => 'admin'])
        ->assertStatus(200)
        ->assertJsonPath('data.admin.role', 'ADMIN')
        ->assertJsonPath('data.previousRole', 'MANAGER');

    expect(AdminSession::where('admin_id', $this->manager->id)->whereNull('revoked_at')->count())->toBe(0);
});

it('refuses self-delete and self-role-change even for SUPER_ADMIN', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuthCsrf($this, $owner)
        ->deleteJson("/api/admin/users/{$this->owner->id}")
        ->assertStatus(409);

    usersAuthCsrf($this, $owner)
        ->patchJson("/api/admin/users/{$this->owner->id}/role", ['role' => 'MANAGER'])
        ->assertStatus(409);

    expect($this->owner->fresh())->not->toBeNull()
        ->and($this->owner->fresh()->role)->toBe('SUPER_ADMIN');
});

it('protects the last active SUPER_ADMIN from deletion and demotion', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuthCsrf($this, $owner)
        ->deleteJson("/api/admin/users/{$this->owner->id}")
        ->assertStatus(409);

    usersAuthCsrf($this, $owner)
        ->patchJson("/api/admin/users/{$this->owner->id}/role", ['role' => 'MANAGER'])
        ->assertStatus(409);

    // Deleting ANOTHER super admin is fine while one remains.
    $second = usersSeed('second-super@users.test', 'SUPER_ADMIN', 'a-string-password');
    usersAuthCsrf($this, $owner)
        ->deleteJson("/api/admin/users/{$second->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted', true);

    expect(AdminUser::find($second->id))->toBeNull()
        ->and($this->owner->fresh())->not->toBeNull();
});

it('deletes accounts as SUPER_ADMIN and revokes their sessions', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);
    $victim = usersSeed('victim@users.test', 'ADMIN', 'a-string-password');
    app(AdminAuthService::class)->createSession($victim->id);

    usersAuthCsrf($this, $owner)
        ->deleteJson("/api/admin/users/{$victim->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.deleted', true);

    expect(AdminUser::find($victim->id))->toBeNull()
        ->and(AdminSession::where('admin_id', $victim->id)->whereNull('revoked_at')->count())->toBe(0);
});

it('answers verb mismatches on /users with the BAD_REQUEST envelope', function () {
    $owner = usersLoginAs($this, OWNER_EMAIL, OWNER_PASSWORD);

    usersAuthCsrf($this, $owner)
        ->postJson("/api/admin/users/{$this->manager->id}")
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'BAD_REQUEST');
});
