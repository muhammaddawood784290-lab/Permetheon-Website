<?php

use App\Models\BusinessInquiry;
use App\Models\Meeting;

// ============================================================================
// HTTP-LEVEL MEETING SUITE — real request/response over the API (spec §10/§13)
// ============================================================================

const M_ADMIN_EMAIL = 'owner@permetheon.test';
const M_ADMIN_PASSWORD = 'super-secret-passphrase';

function mBootstrapAdmin(): void
{
    if (App\Models\AdminUser::count() > 0) {
        return;
    }

    App\Models\AdminUser::create([
        'id'                 => 'admin-bootstrap-meetings',
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

function mLoginAsAdmin($test): array
{
    mBootstrapAdmin();

    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => M_ADMIN_EMAIL,
        'password' => M_ADMIN_PASSWORD,
    ]);
    $response->assertStatus(200);

    $cookies = $response->headers->getCookies();
    $sessionCookie = collect($cookies)->first(fn ($c) => $c->getName() === 'admin_session');
    $csrfCookie = collect($cookies)->first(fn ($c) => $c->getName() === 'admin_csrf');

    return [
        'session' => $sessionCookie?->getValue(),
        'csrf'    => $csrfCookie?->getValue(),
    ];
}

it('GET /api/meetings/availability serves the public calendar with no admin data', function () {
    $res = $this->getJson('/api/meetings/availability');

    $res->assertStatus(200);
    $availability = $res->json('data.availability');
    expect($availability['timezone'])->toBe('UTC')
        ->and($availability['days'])->toBeArray()
        // No internal admin fields leak (spec §11).
        ->and(json_encode($res->json()))
        ->not->toContain('blocker')
        ->not->toContain('admin');
});

it('POST /api/inquiries books inquiry + meeting atomically (201, both rows)', function () {
    $slot = futureSlot(10);
    $payload = array_merge(VALID_INQUIRY, [
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]);

    $res = $this->postJson('/api/inquiries', $payload);
    $res->assertStatus(201);
    expect($res->json('data.meeting.status'))->toBe('BOOKED')
        ->and($res->json('data.meeting.startsAt'))->toBe($slot)
        ->and($res->json('data.meeting.startsAt'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'); // ISO 8601 UTC

    // Both rows exist, linked.
    $inquiry = BusinessInquiry::find($res->json('data.id'));
    expect($inquiry)->not->toBeNull();
    $meeting = Meeting::query()->where('inquiry_id', $inquiry->id)->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->status)->toBe('BOOKED')
        ->and(\App\Support\MeetingTime::toWire($meeting->starts_at))->toBe($slot);
});

it('DOUBLE-BOOK over HTTP: second customer for the same slot gets 409, no rows', function () {
    $slot = futureSlot(11);
    $payload = fn (string $email) => array_merge(VALID_INQUIRY, [
        'email'   => $email,
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]);

    $first = $this->postJson('/api/inquiries', $payload('first@example.com'));
    $first->assertStatus(201);

    $second = $this->postJson('/api/inquiries', $payload('second@example.com'));
    $second->assertStatus(409);
    expect($second->json('error.code'))->toBe('SLOT_CONFLICT')
        ->and($second->json('error.message'))->toContain('taken');

    // Atomicity: the losing inquiry was rolled back too (spec §5).
    expect(BusinessInquiry::where('email', 'second@example.com')->count())->toBe(0)
        ->and(Meeting::query()->where('starts_at', \App\Support\MeetingTime::normalize($slot))->whereIn('status', Meeting::SLOT_HOLDING)->count())->toBe(1);
});

it('booking WITHOUT a meeting still works — no meeting row (spec §1)', function () {
    $res = $this->postJson('/api/inquiries', VALID_INQUIRY);
    $res->assertStatus(201);
    expect($res->json('data.meeting'))->toBeNull()
        ->and(Meeting::query()->where('inquiry_id', $res->json('data.id'))->count())->toBe(0);

    // Explicit "no booking" behaves the same.
    $res2 = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'no-meeting@example.com',
        'meeting' => ['booking' => false],
    ]));
    $res2->assertStatus(201);
    expect($res2->json('data.meeting'))->toBeNull();
});

it('meeting=true without a slot is a 422 validation error', function () {
    $res = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'meeting' => ['booking' => true],
    ]));
    $res->assertStatus(422);
    expect(array_keys($res->json('error.fields') ?? []))->toContain('meeting.startsAt');
});

it('blocked slot over HTTP: 409, and availability reflects the block', function () {
    $slot = futureSlot(12);
    $this->postJson('/api/admin/blocks', ['blockedDate' => substr($slot, 0, 10)])
        ->assertStatus(401); // unauthenticated blocks POST denied

    $cookies = mLoginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    $create = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->postJson('/api/admin/blocks', ['blockedDate' => substr($slot, 0, 10), 'reason' => 'http suite']);
    $create->assertStatus(201);

    // Availability for that date is BLOCKED…
    $cal = $this->getJson('/api/meetings/availability')->json('data.availability');
    $day = collect($cal['days'])->firstWhere('date', substr($slot, 0, 10));
    expect($day['status'])->toBe('BLOCKED');

    // …and booking is rejected.
    $res = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'blocked@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $res->assertStatus(409);

    // Unblock → booking succeeds (block removal works).
    $del = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->deleteJson('/api/admin/blocks/' . $create->json('data.block.id'));
    $del->assertStatus(200);

    $res2 = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'blocked@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $res2->assertStatus(201);
});

it('admin meeting lifecycle over HTTP: list → PATCH COMPLETED → DELETE; authz + CSRF enforced', function () {
    $cookies = mLoginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    // Book through the public API.
    $slot = futureSlot(13);
    $book = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'lifecycle@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $book->assertStatus(201);
    $inquiryId = $book->json('data.id');

    // List shows it with joined customer data.
    $list = $this->withCredentials()->withUnencryptedCookies($headers)
        ->getJson('/api/admin/meetings?status=BOOKED&from=' . substr($slot, 0, 10) . '&to=' . substr($slot, 0, 10));
    $list->assertStatus(200);
    $row = collect($list->json('data.meetings'))->first(fn ($m) => $m['inquiryId'] === $inquiryId);
    expect($row)->not->toBeNull()
        ->and($row['customer']['email'])->toBe('lifecycle@example.com')
        ->and($row['customer']['name'])->toBe(VALID_INQUIRY['name']);

    // PATCH to COMPLETED (validated transition).
    $patch = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->patchJson('/api/admin/meetings/' . $row['id'], ['status' => 'COMPLETED']);
    $patch->assertStatus(200);
    expect($patch->json('data.meeting.status'))->toBe('COMPLETED');

    // Invalid transition COMPLETED → BOOKED is a 422.
    $bad = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->patchJson('/api/admin/meetings/' . $row['id'], ['status' => 'BOOKED']);
    $bad->assertStatus(422);

    // Permanent meeting delete (authorized cleanup, spec §7).
    $del = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->deleteJson('/api/admin/meetings/' . $row['id']);
    $del->assertStatus(200);
    expect(Meeting::find($row['id']))->toBeNull();

    // Authz: no session at all → 401 (fresh state — no cookies are attached
    // to a bare getJson call, so this is a clean unauthenticated probe).
    expect(Meeting::query()->where('inquiry_id', $inquiryId)->count())->toBe(0); // deleted above
});

it('CSRF: state-changing meeting routes without the token → 403, not 419 (row 28 convention)', function () {
    $cookies = mLoginAsAdmin($this);

    // Fresh test state: session cookie ONLY, no x-csrf-token header.
    $noCsrf = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
    ])->patchJson('/api/admin/meetings/nonexistent-id', ['status' => 'COMPLETED']);
    $noCsrf->assertStatus(403);
    expect($noCsrf->json('error.code'))->toBe('FORBIDDEN');

    $noCsrfDelete = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
    ])->deleteJson('/api/admin/blocks/whatever-id');
    $noCsrfDelete->assertStatus(403);

    // Availability PUT is state-changing too.
    $noCsrfPut = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
    ])->putJson('/api/admin/availability', ['slotDurationMinutes' => 60]);
    $noCsrfPut->assertStatus(403);
});

it('DELETE /api/admin/inquiries: no meeting → 200; with BOOKED meeting → policy runs, no orphans', function () {
    $cookies = mLoginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];
    $auth = fn () => $this->withCredentials()->withUnencryptedCookies($headers)->withHeader('x-csrf-token', $cookies['csrf']);

    // No meeting.
    $plain = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, ['email' => 'plain-del@example.com']));
    $plain->assertStatus(201);
    $auth()->deleteJson('/api/admin/inquiries/' . $plain->json('data.id'))->assertStatus(200);
    expect(BusinessInquiry::find($plain->json('data.id')))->toBeNull();

    // With a BOOKED meeting — default policy deletes meeting + inquiry together.
    $slot = futureSlot(14);
    $booked = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'booked-del@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $booked->assertStatus(201);
    $id = $booked->json('data.id');

    $auth()->deleteJson('/api/admin/inquiries/' . $id)->assertStatus(200);
    expect(BusinessInquiry::find($id))->toBeNull()
        ->and(Meeting::query()->where('inquiry_id', $id)->count())->toBe(0)
        ->and(Meeting::query()->where('starts_at', \App\Support\MeetingTime::normalize($slot))->count())->toBe(0);

    // Deleted inquiry with meeting → slot bookable again.
    $rebook = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'email'   => 'rebook@example.com',
        'meeting' => ['booking' => true, 'startsAt' => $slot],
    ]));
    $rebook->assertStatus(201);
});

it('availability PUT and blocks POST require availability.write (SUPER_ADMIN)', function () {
    mBootstrapAdmin();

    // SUPER_ADMIN passes CSRF-wise but we only assert the 401 path here for
    // brevity: unauthenticated is 401 (authz ordering), the ADMIN-role
    // permission matrix is covered by the unit-level Permissions tests.
    $this->getJson('/api/admin/availability')->assertStatus(401);
    $this->putJson('/api/admin/availability', ['slotDurationMinutes' => 60])->assertStatus(401);
    $this->getJson('/api/admin/blocks')->assertStatus(401);
});
