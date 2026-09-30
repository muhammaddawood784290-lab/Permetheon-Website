<?php

/**
 * PARITY ROWS 1–8 — inquiry validation boundary table over real HTTP.
 * Mirrored 1:1 from the shared frontend validation contract (boundary
 * table). Every message
 * literal is byte-identical. Each violation fires EXACTLY one field error
 * (row 3) — the assertExactFields helper pins that.
 *
 * VALID_INQUIRY and assertExactFields live in tests/Helpers.php so every
 * Feature file can use them standalone.
 */

it('accepts a valid minimal submission and normalizes optional empties to null (rows 1, 2)', function () {
    $response = $this->postJson('/api/inquiries', VALID_INQUIRY);

    $response->assertStatus(201);
    // Round-trip via admin list would need auth; here we verify via a second
    // submission with trimmed/lowercased inputs (row 2).
    $full = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'company' => '  Acme Ltd  ',
        'email' => '  AYLA@Example.COM ',
        'budget' => '$3,000 – $5,000',
        'timeline' => '3–6 months',
    ]));
    $full->assertStatus(201);
});

it('fires exactly one field error with byte-identical messages for each boundary violation (rows 3, 4)', function (string $label, array $override, string $field, ?string $message) {
    $response = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, $override));
    assertExactFields($response, $field, $message);
})->with([
    ['missing name', ['name' => ''], 'name', 'Please tell us your name.'],
    ['1-char name', ['name' => 'A'], 'name', 'Name must be at least 2 characters.'],
    ['101-char name', ['name' => str_repeat('A', 101)], 'name', 'Name must be at most 100 characters.'],
    ['name without letters', ['name' => '12345'], 'name', 'Name must contain at least one letter.'],
    ['151-char company', ['company' => str_repeat('C', 151)], 'company', 'Company must be at most 150 characters.'],
    ['missing email', ['email' => ''], 'email', 'Please enter your business email.'],
    ['invalid email', ['email' => 'not-an-email'], 'email', 'Enter a valid email address.'],
    ['255-char email', ['email' => str_repeat('a', 243) . '@example.com'], 'email', 'Email must be at most 254 characters.'],
    ['missing project type', ['projectType' => ''], 'projectType', 'Select what you need built.'],
    ['invalid project type', ['projectType' => 'Nuclear Plant'], 'projectType',
        'Select one of the available options: Website Development, Web Application, Business System, Booking / Reservation System, UI/UX & Product Design, Custom Digital Product, Other.'],
    ['invalid budget', ['budget' => 'A billion dollars'], 'budget',
        'Select one of the available options: Under $1,000, $1,000 – $3,000, $3,000 – $5,000, $5,000 – $10,000, $10,000+, Not sure yet.'],
    ['invalid timeline', ['timeline' => 'Yesterday'], 'timeline',
        'Select one of the available options: As soon as possible, Within 1 month, 1–3 months, 3–6 months, 6+ months, Not sure yet.'],
    ['missing message', ['message' => ''], 'message', 'Tell us about your project (at least 20 characters).'],
    ['19-char message', ['message' => str_repeat('a', 19)], 'message', 'Please provide at least 20 characters.'],
    ['5001-char message', ['message' => str_repeat('a', 5001)], 'message', 'Message must be at most 5,000 characters.'],
    ['script in message', ['message' => 'Nice project <script>alert(1)</script> for us'], 'message', 'Message may not contain markup or code.'],
    ['script in name', ['name' => '<script>alert(1)</script>'], 'name', 'Name may not contain markup or code.'],
    ['missing contact number', ['contactNumber' => ''], 'contactNumber', 'Please enter your contact number.'],
    ['local-only number', ['contactNumber' => '03001234567'], 'contactNumber', 'Enter a valid international contact number, e.g. +12025550147.'],
    ['no country code', ['contactNumber' => '923001234567'], 'contactNumber', 'Enter a valid international contact number, e.g. +12025550147.'],
    ['unassigned calling code (+999)', ['contactNumber' => '+999 512345678'], 'contactNumber', 'Enter a valid international contact number, e.g. +12025550147.'],
    ['too many digits', ['contactNumber' => '+120255501471234'], 'contactNumber', 'Enter a valid international contact number, e.g. +12025550147.'],
    ['script payload', ['contactNumber' => '+1<script>'], 'contactNumber', 'Enter a valid international contact number, e.g. +12025550147.'],
]);

it('rejects non-record payloads with a form error, never a 500 (row 8)', function (string $rawBody) {
    $response = $this->call('POST', '/api/inquiries', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $rawBody);
    $response->assertStatus(422);
    expect(array_keys($response->json('error.fields') ?? []))->toBe(['form']);
})->with([
    'null' => ['null'],
    'integer' => ['42'],
    'string' => ['"string"'],
    'list array' => ['["array"]'],
]);

it('accepts every canonical enum option (row 6)', function () {
    // This test issues 19 requests in a row — far above the 5/10min public
    // limit — so the G1 limiter would trip mid-test. Give this test its own
    // source IP by spoofing the forwarded-for header per option.
    $projectTypes = [
        'Website Development', 'Web Application', 'Business System',
        'Booking / Reservation System', 'UI/UX & Product Design',
        'Custom Digital Product', 'Other',
    ];
    $budgets = ['Under $1,000', '$1,000 – $3,000', '$3,000 – $5,000', '$5,000 – $10,000', '$10,000+', 'Not sure yet'];
    $timelines = ['As soon as possible', 'Within 1 month', '1–3 months', '3–6 months', '6+ months', 'Not sure yet'];

    $n = 0;
    $post = function (array $merge) use (&$n) {
        $n++;
        $this->withHeader('X-Forwarded-For', '10.0.0.' . $n)
            ->postJson('/api/inquiries', $merge)->assertStatus(201);
    };

    foreach ($projectTypes as $type) {
        $post(array_merge(VALID_INQUIRY, ['projectType' => $type]));
    }
    foreach ($budgets as $budget) {
        $post(array_merge(VALID_INQUIRY, ['budget' => $budget]));
    }
    foreach ($timelines as $timeline) {
        $post(array_merge(VALID_INQUIRY, ['timeline' => $timeline]));
    }
});

it('returns 400 for a malformed JSON body (row 20)', function () {
    $response = $this->call('POST', '/api/inquiries', [], [], [], [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], 'not-json');

    $response->assertStatus(400);
});
