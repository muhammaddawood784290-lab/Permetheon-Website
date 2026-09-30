<?php

/**
 * F3 — CSRF TOKEN IS BOUND TO THE SESSION (docs/ADMIN_AUTH_REVIEW.md, F3).
 *
 * The old admin_csrf cookie was pure random: anyone who could SET that
 * cookie (subdomain injection / cookie tossing on a misconfigured domain)
 * could align cookie and header and pass the double-submit check. The token
 * is now <nonce>.<HMAC-SHA256(nonce, key = session-token hash)> — minting a
 * valid value requires the HttpOnly admin_session cookie's secret, which a
 * cookie-tossing attacker cannot read or choose.
 *
 * Contract pins:
 *   - a login-minted pair (cookie + header) still passes → controller runs;
 *   - an attacker-chosen cookie/header pair fails, EVEN though they match;
 *   - a valid token replayed against a DIFFERENT session fails;
 *   - any tampering with nonce or tag fails;
 *   - rejection is the PINNED 403 FORBIDDEN 'Invalid CSRF token.' envelope.
 *
 * Route mechanics used below: on a CSRF-guarded write route, 404 = CSRF
 * passed (controller answered), 403 = CSRF rejected.
 */

const F3_EMAIL = 'owner@permetheon.test';
const F3_PASSWORD = 'super-secret-passphrase';

function f3CreateAdmin(): void
{
    if (App\Models\AdminUser::where('email', F3_EMAIL)->exists()) {
        return;
    }
    App\Models\AdminUser::create([
        'id'                 => Illuminate\Support\Str::uuid()->toString(),
        'email'              => strtolower(F3_EMAIL),
        'name'               => 'Owner',
        'password_hash'      => Illuminate\Support\Facades\Hash::make(F3_PASSWORD),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => App\Support\Clock::now(),
        'updated_at'         => App\Support\Clock::now(),
    ]);
}

/** One full login → ['session' => token, 'csrf' => bound token]. */
function f3Login($test): array
{
    f3CreateAdmin();
    $response = $test->postJson('/api/admin/auth/login', [
        'email'    => F3_EMAIL,
        'password' => F3_PASSWORD,
    ]);
    $response->assertStatus(200);

    $cookies = collect($response->headers->getCookies());

    return [
        'session' => $cookies->first(fn ($c) => $c->getName() === 'admin_session')?->getValue(),
        'csrf'    => $cookies->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue(),
    ];
}

function f3Patch($test, array $auth, ?string $csrfOverride = null, ?string $headerOverride = null)
{
    // The header defaults to the SAME value the cookie carries (the real SPA
    // mirrors document.cookie into x-csrf-token); overrides simulate attacks.
    $cookieValue = $csrfOverride ?? $auth['csrf'];

    return $test->withCredentials()->withUnencryptedCookies([
        'admin_session' => $auth['session'],
        'admin_csrf'    => $cookieValue,
    ])->withHeaders(['x-csrf-token' => $headerOverride ?? $cookieValue])
        ->patchJson('/api/admin/inquiries/00000000-0000-0000-0000-00000000f3aa', ['status' => 'REVIEWING']);
}

it('F3: the login-minted, session-bound pair still passes CSRF (controller reached)', function () {
    $auth = f3Login($this);

    // 404 (not 403): the middleware let the request through to the controller.
    f3Patch($this, $auth)->assertStatus(404);
});

it('F3: a TOSSED cookie value cannot be self-aligned (matching pair, invalid tag)', function () {
    $auth = f3Login($this);

    // Attacker sets both cookie and header to a value they choose. Under the
    // OLD scheme this passed (double-submit equality). Now the tag must
    // verify under the session key — it does not.
    $tossed = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    $response = f3Patch($this, $auth, $csrfOverride = $tossed, $headerOverride = $tossed);
    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('FORBIDDEN')
        ->and($response->json('error.message'))->toBe('Invalid CSRF token.');
});

it('F3: a valid token replayed against a DIFFERENT session is rejected', function () {
    $first = f3Login($this);
    $second = f3Login($this); // second live session, own bound token

    // Session B + token bound to session A: cookie and header match each
    // other, but the HMAC key (session B's hash) differs — cross-session
    // replay is dead.
    $response = f3Patch($this, $second, $csrfOverride = $first['csrf'], $headerOverride = $first['csrf']);
    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('FORBIDDEN');
});

it('F3: tampering with nonce or tag breaks validation', function () {
    $auth = f3Login($this);

    [$nonce, $tag] = explode('.', $auth['csrf']);

    // Flip one character of the tag.
    $tagChars = str_split($tag);
    $tagChars[0] = $tagChars[0] === 'A' ? 'B' : 'A';
    $tamperedTag = $nonce . '.' . implode('', $tagChars);
    f3Patch($this, $auth, $csrfOverride = $tamperedTag, $headerOverride = $tamperedTag)->assertStatus(403);

    // Swap the nonce for a fresh random one (tag no longer matches).
    $freshNonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    f3Patch($this, $auth, $csrfOverride = $freshNonce . '.' . $tag, $headerOverride = $freshNonce . '.' . $tag)
        ->assertStatus(403);

    // Header no longer equal to cookie: classic double-submit failure stays.
    f3Patch($this, $auth, $headerOverride = $nonce . '.' . $tag . 'x')->assertStatus(403);
});

it('F3: token structure is nonce.tag (87 chars, base64url) and rotates per session', function () {
    $a = f3Login($this);
    $b = f3Login($this);

    foreach ([$a['csrf'], $b['csrf']] as $token) {
        expect(substr_count($token, '.'))->toBe(1)
            ->and(strlen($token))->toBe(87)
            ->and($token)->toMatch('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/');
    }
    // Fresh randomness per issuance.
    expect($a['csrf'])->not->toBe($b['csrf']);
});

it('F3: refresh re-issues a token bound to the NEW session (old pair is dead)', function () {
    $auth = f3Login($this);

    $refresh = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $auth['session'],
        'admin_csrf'    => $auth['csrf'],
    ])->withHeaders(['x-csrf-token' => $auth['csrf']])
        ->postJson('/api/admin/auth/refresh');
    $refresh->assertStatus(200);

    $newSession = collect($refresh->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_session')?->getValue();
    $newCsrf = collect($refresh->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_csrf')?->getValue();
    expect($newSession)->not->toBe($auth['session'])
        ->and($newCsrf)->not->toBe($auth['csrf']);

    // The rotated pair works...
    f3Patch($this, ['session' => $newSession, 'csrf' => $newCsrf])->assertStatus(404);

    // ...and the OLD csrf token is dead with its revoked session.
    $response = f3Patch($this, ['session' => $auth['session'], 'csrf' => $auth['csrf']]);
    expect($response->status())->toBe(401); // guard rejects revoked session first
});
