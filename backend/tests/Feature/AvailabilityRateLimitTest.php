<?php

/**
 * AVAILABILITY RATE LIMIT — dedicated read bucket (calendar bug fix).
 *
 * GET /api/meetings/availability previously shared the inquiry limiter
 * (5/10min): five calendar fetches consumed the contact form's budget and
 * vice versa, and behind a reverse proxy (one shared per-IP bucket without
 * TRUSTED_PROXIES) a single visitor could blank the booking calendar AND the
 * form for everyone — the "We couldn't load the calendar right now" bug.
 *
 * Now: 60/min per client, keyed by the same F1 clientKey rule. These tests
 * pin the DECOUPLING (form budget untouched by calendar reads and the
 * reverse) and the pinned 429 envelope.
 *
 * phpunit.xml declares 127.0.0.1 a trusted proxy, so X-Forwarded-For keys the
 * buckets exactly like production traffic behind a proxy. RefreshDatabase
 * truncates the cache table per test, so counters cannot leak.
 */

const AVAIL_LIMIT = 60;

it('availability allows a realistic browsing burst well past the old 5-request wall', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.119.7')
            ->getJson('/api/meetings/availability')
            ->assertStatus(200);
    }
});

it('availability has its OWN 60/min bucket and never consumes the inquiry limiter', function () {
    // 60 availability reads -> the availability bucket is now exactly full.
    for ($i = 0; $i < AVAIL_LIMIT; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.119.8')
            ->getJson('/api/meetings/availability')
            ->assertStatus(200);
    }
    $this->withHeader('X-Forwarded-For', '203.0.119.8')
        ->getJson('/api/meetings/availability')
        ->assertStatus(429);

    // THE DECOUPLING: the contact form's write budget is untouched.
    $this->withHeader('X-Forwarded-For', '203.0.119.8')
        ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
            'email' => 'decoupled-form@example.com',
        ]))->assertStatus(201);
});

it('availability exhaustion carries the pinned RATE_LIMITED envelope', function () {
    for ($i = 0; $i < AVAIL_LIMIT; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.119.9')
            ->getJson('/api/meetings/availability');
    }
    $response = $this->withHeader('X-Forwarded-For', '203.0.119.9')
        ->getJson('/api/meetings/availability');

    $response->assertStatus(429);
    expect($response->json('error.code'))->toBe('RATE_LIMITED')
        ->and($response->json('error.message'))->toBe('Too many requests. Please try again shortly.')
        ->and($response->headers->get('Retry-After'))->not->toBeNull();
});

it('availability buckets are per-client (proxy keying parity with F1)', function () {
    // Exhaust one client's bucket entirely...
    for ($i = 0; $i < AVAIL_LIMIT + 1; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.119.10')
            ->getJson('/api/meetings/availability');
    }

    // ...a different client behind the same proxy is unaffected — the old
    // shared-bucket collapse could NOT say this.
    $this->withHeader('X-Forwarded-For', '203.0.119.11')
        ->getJson('/api/meetings/availability')
        ->assertStatus(200);
});

it('inquiry writes do not consume the availability bucket', function () {
    // 5 form submissions (the entire old shared budget)...
    for ($i = 0; $i < 5; $i++) {
        $this->withHeader('X-Forwarded-For', '203.0.119.12')
            ->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
                'email' => "avail-coupling-{$i}@example.com",
            ]))->assertStatus(201);
    }

    // ...and the calendar still works fine afterwards.
    $this->withHeader('X-Forwarded-For', '203.0.119.12')
        ->getJson('/api/meetings/availability')
        ->assertStatus(200);
});
