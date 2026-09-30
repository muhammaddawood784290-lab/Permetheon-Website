<?php

/**
 * F1 — LOGIN THROTTLE IS PROXY-AWARE (docs/ADMIN_AUTH_REVIEW.md, F1).
 *
 * The admin login limiter must key its buckets exactly like the inquiry
 * limiter: the X-Forwarded-For header counts ONLY when the socket peer is
 * listed in TRUSTED_PROXIES (deny-by-default). Behind a reverse proxy the
 * old `$request->ip()` keying collapsed every visitor into ONE shared
 * bucket — a single attacker could lock out all admin logins; these tests
 * pin the fixed behavior.
 *
 * phpunit.xml declares 127.0.0.1 a TRUSTED_PROXY, so feature requests can
 * exercise both keying modes: forwarded-key (X-Forwarded-For set) and
 * socket-key (header present but peer untrusted). RefreshDatabase truncates
 * the cache table per test, so limiter counters cannot leak between cases.
 */

const F1_LOGIN_LIMIT = 10;

it('F1: login throttle gives each X-Forwarded-For client its OWN bucket behind a trusted proxy', function () {
    // 10/10min per client: exhaust client A's bucket entirely.
    for ($i = 0; $i < F1_LOGIN_LIMIT; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.113.10')
            ->postJson('/api/admin/auth/login', [
                'email'    => 'f1-a@permetheon.test',
                'password' => 'nope',
            ])->assertStatus(401);
    }

    // Client A is now limited — with the Pinned 429 envelope.
    $limited = $this->withHeader('X-Forwarded-For', '203.0.113.10')
        ->postJson('/api/admin/auth/login', ['email' => 'f1-a@permetheon.test', 'password' => 'nope']);
    $limited->assertStatus(429);
    expect($limited->json('error.code'))->toBe('RATE_LIMITED')
        ->and($limited->json('error.message'))->toBe('Too many requests. Please try again shortly.');

    // Client B (same socket 127.0.0.1, different forwarded IP) must still
    // get 401s — this is the anti-collapse guarantee: one client's hammering
    // cannot lock all admin logins out behind the proxy.
    $fresh = $this->withHeader('X-Forwarded-For', '203.0.113.11')
        ->postJson('/api/admin/auth/login', ['email' => 'f1-b@permetheon.test', 'password' => 'nope']);
    $fresh->assertStatus(401);
});

it('F1: spoofed X-Forwarded-For from an UNTRUSTED socket peer is ignored (socket IP is the key)', function () {
    // Point TRUSTED_PROXIES at a proxy that is NOT this test's socket peer:
    // requests then arrive from an untrusted client, whose forwarded header
    // must be neutralized (deny-by-default).
    putenv('TRUSTED_PROXIES=10.250.0.9');
    $_ENV['TRUSTED_PROXIES'] = '10.250.0.9';
    $_SERVER['TRUSTED_PROXIES'] = '10.250.0.9';

    try {
        // Each request forges a DIFFERENT forwarded IP: if the header were
        // honored, none of these would share a bucket and the 11th would pass.
        for ($i = 0; $i < F1_LOGIN_LIMIT; $i++) {
            $this->withHeader('X-Forwarded-For', '198.51.100.' . $i)
                ->postJson('/api/admin/auth/login', [
                    'email'    => 'f1-spoof@permetheon.test',
                    'password' => 'nope',
                ])->assertStatus(401);
        }

        // All 10 landed in the socket-IP bucket: the 11th is limited even
        // though it presents a brand-new (never-seen) forwarded address.
        $this->withHeader('X-Forwarded-For', '198.51.100.254')
            ->postJson('/api/admin/auth/login', [
                'email'    => 'f1-spoof@permetheon.test',
                'password' => 'nope',
            ])->assertStatus(429);
    } finally {
        // Restore the phpunit.xml value so later tests keep the trusted mode.
        putenv('TRUSTED_PROXIES=127.0.0.1');
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
        $_SERVER['TRUSTED_PROXIES'] = '127.0.0.1';
    }
    expect(true)->toBeTrue(); // 11 assertions above carry the case
});

it('F1: with TRUSTED_PROXIES unset, keying falls back to the socket IP for both limiters', function () {
    // Deny-by-default contract: with no proxies configured the forwarded
    // header must be irrelevant, and BOTH limiters must key by socket IP.
    putenv('TRUSTED_PROXIES=');
    $_ENV['TRUSTED_PROXIES'] = '';
    $_SERVER['TRUSTED_PROXIES'] = '';

    try {
        // inquiry limiter: header ignored -> 5 socket-keyed submissions then 429.
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Forwarded-For', '192.0.2.' . $i)
                ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
                    'email' => "f1-fallback-{$i}@example.com",
                ]))->assertStatus(201);
        }
        $this->withHeader('X-Forwarded-For', '192.0.2.99')
            ->postJson('/api/inquiries', VALID_INQUIRY)->assertStatus(429);

        // login limiter: same fallback -> 10 socket-keyed attempts then 429.
        for ($i = 0; $i < F1_LOGIN_LIMIT; $i++) {
            $this->withHeader('X-Forwarded-For', '192.0.2.' . $i)
                ->postJson('/api/admin/auth/login', [
                    'email'    => 'f1-fb@permetheon.test',
                    'password' => 'nope',
                ])->assertStatus(401);
        }
        $this->withHeader('X-Forwarded-For', '192.0.2.99')
            ->postJson('/api/admin/auth/login', ['email' => 'f1-fb@permetheon.test', 'password' => 'nope'])
            ->assertStatus(429);
    } finally {
        // Restore the phpunit.xml value so later tests keep the trusted mode.
        putenv('TRUSTED_PROXIES=127.0.0.1');
        $_ENV['TRUSTED_PROXIES'] = '127.0.0.1';
        $_SERVER['TRUSTED_PROXIES'] = '127.0.0.1';
    }
});

it('F1: from a trusted proxy, the RIGHTMOST forwarded entry sets the key', function () {
    // Pinned semantics of clientKey(): once the socket peer is trusted, the
    // key is the rightmost X-Forwarded-For entry (the deployment contract is
    // that the outermost proxy supplies the real client as that entry).
    for ($i = 0; $i < F1_LOGIN_LIMIT; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.114.7, 10.250.0.9')
            ->postJson('/api/admin/auth/login', [
                'email'    => 'f1-chain@permetheon.test',
                'password' => 'nope',
            ])->assertStatus(401);
    }

    // The rightmost-entry bucket is exhausted: an identical chain 429s...
    $this->withHeader('X-Forwarded-For', '203.0.114.7, 10.250.0.9')
        ->postJson('/api/admin/auth/login', ['email' => 'f1-chain@permetheon.test', 'password' => 'nope'])
        ->assertStatus(429);

    // ...and the rightmost entry is what MATTERS: a different left-side value
    // with the same rightmost entry shares the same bucket.
    $this->withHeader('X-Forwarded-For', '203.0.114.99, 10.250.0.9')
        ->postJson('/api/admin/auth/login', ['email' => 'f1-chain2@permetheon.test', 'password' => 'nope'])
        ->assertStatus(429);

    // A plain single-entry header hashes to a different bucket entirely.
    $this->withHeader('X-Forwarded-For', '203.0.114.7')
        ->postJson('/api/admin/auth/login', ['email' => 'f1-chain3@permetheon.test', 'password' => 'nope'])
        ->assertStatus(401);
});
