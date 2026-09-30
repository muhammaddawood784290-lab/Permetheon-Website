<?php

/**
 * PARITY ROWS 23–31 + G8/G9 — admin API over real HTTP with cookie jar.
 * 401 gates · generic login errors · cookie flags · newest-first pagination ·
 * search · CSRF → 403 (not 419) · message untouched on PATCH · notes ·
 * server-side session revocation · HEAD /session → 204 · verb mismatches → 400.
 */

const ADMIN_EMAIL = 'owner@permetheon.test';
const ADMIN_PASSWORD = 'super-secret-passphrase';

function bootstrapAdmin(): void
{
    if (App\Models\AdminUser::count() > 0) {
        return;
    }

    // Mint the super admin directly (as the bootstrap would).
    App\Models\AdminUser::create([
        'id'                 => Illuminate\Support\Str::uuid()->toString(),
        'email'              => strtolower(ADMIN_EMAIL),
        'name'               => 'Owner',
        'password_hash'      => Illuminate\Support\Facades\Hash::make(ADMIN_PASSWORD),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => gmdate('Y-m-d H:i:s.v'),
        'updated_at'         => gmdate('Y-m-d H:i:s.v'),
    ]);
}

function loginAsAdmin($test): array
{
    bootstrapAdmin();

    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => ADMIN_EMAIL,
        'password' => ADMIN_PASSWORD,
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

it('requires authentication on every admin route (row 23)', function () {
    foreach ([
        ['GET', '/api/admin/inquiries'],
        ['POST', '/api/admin/inquiries'],
        ['GET', '/api/admin/inquiries/stats'],
        ['GET', '/api/admin/inquiries/nonexistent-id'],
        ['PATCH', '/api/admin/inquiries/nonexistent-id'],
        ['GET', '/api/admin/inquiries/nonexistent-id/notes'],
        ['POST', '/api/admin/inquiries/nonexistent-id/notes'],
        ['POST', '/api/admin/auth/logout'],
        ['GET', '/api/admin/auth/session'],
    ] as [$method, $path]) {
        $response = $this->json($method, $path);
        $response->assertStatus(401);
        expect($response->json('error.code'))->toBe('UNAUTHORIZED');
    }
});

it('rejects wrong password and unknown email with the same generic message (row 24)', function () {
    bootstrapAdmin();

    foreach ([
        ['email' => ADMIN_EMAIL, 'password' => 'wrong-password'],
        ['email' => 'nobody@permetheon.test', 'password' => 'wrong-password'],
    ] as $attempt) {
        $response = $this->postJson('/api/admin/auth/login', $attempt);
        $response->assertStatus(401);
        expect($response->json('error.message'))->toBe('Invalid email or password.')
            ->and($response->json('error.code'))->toBe('UNAUTHORIZED');
    }
});

it('sets HttpOnly+SameSite=Strict on the session cookie and exposes the CSRF cookie (row 25)', function () {
    bootstrapAdmin();

    $response = $this->postJson('/api/admin/auth/login', [
        'email'    => ADMIN_EMAIL,
        'password' => ADMIN_PASSWORD,
    ]);
    $response->assertStatus(200);

    $sessionCookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_session');
    $csrfCookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_csrf');

    expect($sessionCookie)->not->toBeNull()
        ->and($sessionCookie->isHttpOnly())->toBeTrue()
        ->and($sessionCookie->getSameSite())->toBe('strict') // symfony lowercases; the wire value is SameSite=strict
        ->and($sessionCookie->getValue())->not->toBe('')
        ->and($csrfCookie)->not->toBeNull()
        ->and($csrfCookie->isHttpOnly())->toBeFalse()
        ->and($response->json('data.csrfToken'))->toBe($csrfCookie->getValue());
});

it('paginates newest-first with honest totals (row 26)', function () {
    bootstrapAdmin();

    for ($i = 0; $i < 12; $i++) {
        $this->withHeader('X-Forwarded-For', '10.9.0.' . $i)
            ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
                'name' => 'Pagination Probe ' . $i,
            ]))->assertStatus(201);
    }

    $cookies = loginAsAdmin($this);

    $response = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
        'admin_csrf'    => $cookies['csrf'],
    ])->getJson('/api/admin/inquiries');

    $response->assertStatus(200);
    $body = $response->json('data');
    expect($body['pagination']['page'])->toBe(1)
        ->and($body['pagination']['pageSize'])->toBe(10)
        ->and($body['pagination']['total'])->toBe(12)
        ->and($body['pagination']['totalPages'])->toBe(2);

    $names = array_column($body['inquiries'], 'name');
    expect(count($body['inquiries']))->toBe(10)
        ->and($names[0])->toBe('Pagination Probe 11')
        ->and($names[9])->toBe('Pagination Probe 2');

    $page2 = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
        'admin_csrf'    => $cookies['csrf'],
    ])->getJson('/api/admin/inquiries?page=2');
    $page2->assertStatus(200);
    expect(count($page2->json('data.inquiries')))->toBe(2);
});

it('searches by name/email/company/phone/message (row 27)', function () {
    bootstrapAdmin();

    $this->withHeader('X-Forwarded-For', '10.9.1.1')
        ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
            'name'    => 'Zelda Querystorm',
            'email'   => 'zelda@storm.test',
            'company' => 'Stormworks',
            'message' => 'We build weather balloons and storm chasers.',
        ]))->assertStatus(201);

    $cookies = loginAsAdmin($this);

    foreach (['Zelda', 'zelda@storm.test', 'Stormworks', '+12025550147', 'weather balloons'] as $term) {
        $response = $this->withCredentials()->withUnencryptedCookies([
            'admin_session' => $cookies['session'],
            'admin_csrf'    => $cookies['csrf'],
        ])->getJson('/api/admin/inquiries?search=' . urlencode($term));

        $response->assertStatus(200);
        expect(count($response->json('data.inquiries')))->toBeGreaterThan(0);
    }
});

it('updates status/priority but never touches the original message (row 29)', function () {
    bootstrapAdmin();

    $this->withHeader('X-Forwarded-For', '10.9.2.1')
        ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
            'message' => 'Original immutable project description.',
        ]))->assertStatus(201);

    $cookies = loginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    $list = $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/inquiries');
    $id = $list->json('data.inquiries.0.id');

    $update = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->patchJson("/api/admin/inquiries/{$id}", [
            'status'   => 'REVIEWING',
            'priority' => 'HIGH',
        ]);
    $update->assertStatus(200);
    expect($update->json('data.inquiry.status'))->toBe('REVIEWING')
        ->and($update->json('data.inquiry.priority'))->toBe('HIGH')
        ->and($update->json('data.inquiry.message'))->toBe('Original immutable project description.');

    // Hostile update attempts.
    $hostile = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->patchJson("/api/admin/inquiries/{$id}", [
            'message' => 'HACKED',
        ]);
    $hostile->assertStatus(200);
    expect($hostile->json('data.inquiry.message'))->toBe('Original immutable project description.');

    $badStatus = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->patchJson("/api/admin/inquiries/{$id}", [
            'status' => 'NOT_A_STATUS',
        ]);
    $badStatus->assertStatus(422);
});

it('returns 403 for a missing CSRF token on mutating verbs — not 419 (row 28)', function () {
    bootstrapAdmin();

    $this->withHeader('X-Forwarded-For', '10.9.3.1')
        ->postJson('/api/inquiries', VALID_INQUIRY)->assertStatus(201);

    $cookies = loginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session']];

    $list = $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/inquiries');
    $id = $list->json('data.inquiries.0.id');

    // PATCH without the x-csrf-token header → 403 with FORBIDDEN envelope.
    $response = $this->withCredentials()->withUnencryptedCookies($headers)
        ->patchJson("/api/admin/inquiries/{$id}", ['status' => 'WON']);
    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('FORBIDDEN')
        ->and($response->json('error.message'))->toBe('Invalid CSRF token.');

    // And the row must be unchanged.
    $check = $this->withCredentials()->withUnencryptedCookies($headers)->getJson("/api/admin/inquiries/{$id}");
    expect($check->json('data.inquiry.status'))->toBe('NEW');
});

it('manages notes: create with author, list, 404 on unknown inquiry (row 30)', function () {
    bootstrapAdmin();

    $this->withHeader('X-Forwarded-For', '10.9.4.1')
        ->postJson('/api/inquiries', VALID_INQUIRY)->assertStatus(201);

    $cookies = loginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    $list = $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/inquiries');
    $id = $list->json('data.inquiries.0.id');

    $created = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->postJson("/api/admin/inquiries/{$id}/notes", ['body' => '  Called back, interested.  ']);
    $created->assertStatus(201);
    // TS contract: note carries adminId + adminName (author joined from admin_users)
    expect($created->json('data.note.body'))->toBe('Called back, interested.')
        ->and($created->json('data.note.adminName'))->toBe('Owner')
        ->and($created->json('data.note.adminId'))->not->toBeNull();

    $list = $this->withCredentials()->withUnencryptedCookies($headers)->getJson("/api/admin/inquiries/{$id}/notes");
    $list->assertStatus(200);
    expect(count($list->json('data.notes')))->toBe(1);

    $missing = $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/inquiries/no-such-id/notes');
    $missing->assertStatus(404);
});

it('revokes sessions server-side: cookie alone is dead after logout (row 31)', function () {
    bootstrapAdmin();

    $cookies = loginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    // Works before logout.
    $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/auth/session')->assertStatus(200);

    // Logout requires CSRF (double-submit) — the standard for every mutation.
    $logout = $this->withCredentials()->withUnencryptedCookies($headers)
        ->withHeader('x-csrf-token', $cookies['csrf'])
        ->postJson('/api/admin/auth/logout');
    $logout->assertStatus(200);

    // Same cookie replayed after logout → 401 (server-side revocation, not a client delete).
    $replayed = $this->withCredentials()->withUnencryptedCookies($headers)->getJson('/api/admin/auth/session');
    $replayed->assertStatus(401);
});

it('answers HEAD /admin/auth/session with 204 and no body (G8)', function () {
    bootstrapAdmin();
    $cookies = loginAsAdmin($this);

    // NOTE: call() does NOT transmit withUnencryptedCookies — only the
    // get()/post()/json() wrappers prepare cookies. Pass the session cookie
    // explicitly (same wire format the browser would send).
    $response = $this->call('HEAD', '/api/admin/auth/session', [], [
        'admin_session' => $cookies['session'],
    ]);

    $response->assertStatus(204);
    expect($response->getContent())->toBe('');
});

it('rejects verb mismatches with 400 BAD_REQUEST (G9)', function () {
    bootstrapAdmin();
    $cookies = loginAsAdmin($this);
    $headers = ['admin_session' => $cookies['session'], 'admin_csrf' => $cookies['csrf']];

    // POST to list (method-mismatch → 400, not 405).
    $post = $this->withCredentials()->withUnencryptedCookies($headers)->postJson('/api/admin/inquiries', []);
    $post->assertStatus(400);
    expect($post->json('error.code'))->toBe('BAD_REQUEST');

    $postStats = $this->withCredentials()->withUnencryptedCookies($headers)
        ->postJson('/api/admin/inquiries/stats', []);
    $postStats->assertStatus(400);
    expect($postStats->json('error.code'))->toBe('BAD_REQUEST');

    // POST to the session probe → 400 BAD_REQUEST as well.
    $postSession = $this->withCredentials()->withUnencryptedCookies($headers)
        ->postJson('/api/admin/auth/session', []);
    $postSession->assertStatus(400);
});

it('serves stats with the full status vocabulary and last30Days (row 28)', function () {
    bootstrapAdmin();

    $cookies = loginAsAdmin($this);
    $response = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $cookies['session'],
    ])->getJson('/api/admin/inquiries/stats');

    $response->assertStatus(200);
    expect($response->json('data.stats'))->toHaveKeys(['total', 'byStatus', 'last30Days'])
        ->and($response->json('data.stats.byStatus'))->toBe([
            'NEW' => 0, 'REVIEWING' => 0, 'CONTACTED' => 0, 'QUALIFIED' => 0,
            'PROPOSAL' => 0, 'WON' => 0, 'LOST' => 0,
        ]);
});
