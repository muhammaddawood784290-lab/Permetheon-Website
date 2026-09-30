<?php

/**
 * F5 — ADMIN AUDIT LOG (docs/ADMIN_AUTH_REVIEW.md, F5).
 *
 * Every successful admin mutation leaves an append-only record: WHO (admin
 * id + denormalized email), WHAT (stable action string), ON WHICH RESOURCE,
 * a small JSON summary and the client IP. Failure paths (404, validation,
 * permission denial) record NOTHING; the model refuses updates/deletes.
 */

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

const F5_OWNER_EMAIL = 'owner@permetheon.test';
const F5_MANAGER_EMAIL = 'manager@permetheon.test';
const F5_PASSWORD = 'audit-log-passphrase';

function f5SeedAdmins(): array
{
    $mk = function (string $email, string $role): AdminUser {
        $existing = AdminUser::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }
        return AdminUser::create([
            'id'                 => (string) Illuminate\Support\Str::uuid(),
            'email'              => $email,
            'name'               => ucfirst($role),
            'password_hash'      => Hash::make(F5_PASSWORD),
            'role'               => $role,
            'is_active'          => 1,
            'failed_login_count' => 0,
            'created_at'         => App\Support\Clock::now(),
            'updated_at'         => App\Support\Clock::now(),
        ]);
    };

    return ['owner' => $mk(strtolower(F5_OWNER_EMAIL), 'SUPER_ADMIN'), 'manager' => $mk(strtolower(F5_MANAGER_EMAIL), 'MANAGER')];
}

function f5Login($test, string $email): array
{
    $response = $test->postJson('/api/admin/auth/login', ['email' => $email, 'password' => F5_PASSWORD]);
    $response->assertStatus(200);

    return [
        'session' => collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'admin_session')?->getValue(),
        'csrf'    => collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue(),
        'admin'   => AdminUser::where('email', strtolower($email))->first(),
    ];
}

function f5Write($test, array $auth, string $method, string $path, array $body = [])
{
    return $test->withCredentials()->withUnencryptedCookies([
        'admin_session' => $auth['session'],
        'admin_csrf'    => $auth['csrf'],
    ])->withHeaders(['x-csrf-token' => $auth['csrf']])
        ->json($method, $path, $body);
}

function f5Inquiry(): App\Models\BusinessInquiry
{
    return App\Models\BusinessInquiry::create([
        'id'             => (string) Illuminate\Support\Str::uuid(),
        'name'           => 'Audit Probe',
        'company'        => '',
        'email'          => 'audit-probe@example.com',
        'contact_number' => '+12025550147',
        'project_type'   => 'Web Application',
        'budget'         => '',
        'timeline'       => '',
        'message'        => 'Fixture inquiry for the audit-log suite.',
        'status'         => 'NEW',
        'priority'       => 'MEDIUM',
        'created_at'     => App\Support\Clock::now(),
        'updated_at'     => App\Support\Clock::now(),
    ]);
}

/** Rows created after the given max id (null = all rows so far). */
function f5RowsAfter(?int $cursor): array
{
    $q = AdminAuditLog::query();
    if ($cursor !== null) {
        $q->where('id', '>', $cursor);
    }

    return $q->orderBy('id')->get()->all();
}

it('F5: inquiry update and delete leave audit rows with actor, action and resource', function () {
    f5SeedAdmins();
    $auth = f5Login($this, F5_OWNER_EMAIL);
    $inquiry = f5Inquiry();
    $cursor = AdminAuditLog::max('id');

    f5Write($this, $auth, 'PATCH', "/api/admin/inquiries/{$inquiry->id}", ['status' => 'REVIEWING', 'priority' => 'HIGH'])
        ->assertStatus(200);
    f5Write($this, $auth, 'DELETE', "/api/admin/inquiries/{$inquiry->id}")->assertStatus(200);

    $rows = f5RowsAfter($cursor);
    expect($rows)->toHaveCount(2);

    $update = $rows[0];
    expect($update->action)->toBe('inquiry.update')
        ->and($update->admin_id)->toBe($auth['admin']->id)
        ->and($update->admin_email)->toBe(strtolower(F5_OWNER_EMAIL))
        ->and($update->resource_type)->toBe('inquiry')
        ->and($update->resource_id)->toBe($inquiry->id)
        ->and($update->ip)->toBe('127.0.0.1')
        ->and($update->summary)->toBeJson();

    $delete = $rows[1];
    expect($delete->action)->toBe('inquiry.delete')
        ->and(json_decode((string) $delete->summary, true)['meeting'])->toBe('none');
});

it('F5: note creation, block create/delete are audited with exact action sequence', function () {
    f5SeedAdmins();
    $auth = f5Login($this, F5_OWNER_EMAIL);
    $inquiry = f5Inquiry();
    $cursor = AdminAuditLog::max('id');

    f5Write($this, $auth, 'POST', "/api/admin/inquiries/{$inquiry->id}/notes", ['body' => 'Audit probe note body'])
        ->assertStatus(201);
    f5Write($this, $auth, 'POST', '/api/admin/blocks', ['blockedDate' => '2027-05-04', 'reason' => 'audit probe'])
        ->assertStatus(201);

    // The block.create row CARRIES the block id in resource_id — use it.
    $blockRow = AdminAuditLog::where('action', 'block.create')->orderByDesc('id')->first();
    $blockId = $blockRow->resource_id;

    f5Write($this, $auth, 'DELETE', "/api/admin/blocks/{$blockId}")->assertStatus(200);

    $actions = array_map(fn ($r) => $r->action, f5RowsAfter($cursor));
    expect($actions)->toBe(['inquiry.note.create', 'block.create', 'block.delete']);
});

it('F5: user administration writes are audited (create, role change, delete)', function () {
    f5SeedAdmins();
    $auth = f5Login($this, F5_OWNER_EMAIL);
    $cursor = AdminAuditLog::max('id');

    f5Write($this, $auth, 'POST', '/api/admin/users', [
        'email' => 'audited@users.test', 'name' => 'Audited', 'role' => 'ADMIN', 'password' => 'a-long-enough-pass',
    ])->assertStatus(201);
    $created = AdminUser::where('email', 'audited@users.test')->first();

    f5Write($this, $auth, 'PATCH', "/api/admin/users/{$created->id}/role", ['role' => 'MANAGER'])->assertStatus(200);
    f5Write($this, $auth, 'DELETE', "/api/admin/users/{$created->id}")->assertStatus(200);

    $rows = f5RowsAfter($cursor);
    expect(array_map(fn ($r) => $r->action, $rows))->toBe(['admin_user.create', 'admin_user.role.update', 'admin_user.delete'])
        ->and(json_decode((string) $rows[1]->summary, true))->toBe(['email' => 'audited@users.test', 'from' => 'ADMIN', 'to' => 'MANAGER']);

    // admin_email is the ACTOR; the deleted TARGET's identity lives in the
    // summary — so the deletion is answerable after the account is gone.
    expect($rows[2]->admin_email)->toBe(strtolower(F5_OWNER_EMAIL))
        ->and(json_decode((string) $rows[2]->summary, true)['email'])->toBe('audited@users.test');
});

it('F5: failures and permission denials record NOTHING', function () {
    f5SeedAdmins();
    $owner = f5Login($this, F5_OWNER_EMAIL);
    $manager = f5Login($this, F5_MANAGER_EMAIL);
    $cursor = AdminAuditLog::max('id');

    // Owner failures: 404 target, 422 validation — no rows.
    f5Write($this, $owner, 'PATCH', '/api/admin/inquiries/00000000-0000-0000-0000-00000000f500', ['status' => 'REVIEWING'])->assertStatus(404);
    f5Write($this, $owner, 'POST', '/api/admin/users', [
        'email' => 'bad', 'name' => '', 'role' => 'GOD', 'password' => 'short',
    ])->assertStatus(422);

    // MANAGER write denial (gate) — no row, even though the action was attempted.
    f5Write($this, $manager, 'POST', '/api/admin/users', [
        'email' => 'denied@users.test', 'name' => 'Denied', 'role' => 'ADMIN', 'password' => 'a-long-enough-pass',
    ])->assertStatus(403);

    expect(f5RowsAfter($cursor))->toBe([]);
});

it('F5: the log is append-only (updates and deletes are refused)', function () {
    f5SeedAdmins();
    $auth = f5Login($this, F5_OWNER_EMAIL);
    $inquiry = f5Inquiry();
    f5Write($this, $auth, 'PATCH', "/api/admin/inquiries/{$inquiry->id}", ['status' => 'REVIEWING'])->assertStatus(200);

    $row = AdminAuditLog::orderByDesc('id')->first();
    expect($row)->not->toBeNull();

    $row->action = 'tampered';

    expect(fn () => $row->save())->toThrow(LogicException::class);
    expect(fn () => $row->delete())->toThrow(LogicException::class);
});

it('F5: admin:audit lists entries with filters', function () {
    f5SeedAdmins();
    $auth = f5Login($this, F5_OWNER_EMAIL);
    $inquiry = f5Inquiry();
    f5Write($this, $auth, 'PATCH', "/api/admin/inquiries/{$inquiry->id}", ['status' => 'REVIEWING'])->assertStatus(200);
    f5Write($this, $auth, 'POST', '/api/admin/blocks', ['blockedDate' => '2027-06-06'])->assertStatus(201);

    // NOTE: PendingCommand consumes output after the first output assertion,
    // so each run gets exactly ONE output expectation.
    $this->artisan('admin:audit', ['--action' => 'inquiry.update'])
        ->expectsOutputToContain('inquiry.update')
        ->assertSuccessful();

    $this->artisan('admin:audit', ['--admin' => 'owner'])
        ->expectsOutputToContain(strtolower(F5_OWNER_EMAIL))
        ->assertSuccessful();

    $this->artisan('admin:audit', ['--admin' => 'nobody-at-all'])
        ->expectsOutputToContain('No audit entries match.')
        ->assertSuccessful();
});
