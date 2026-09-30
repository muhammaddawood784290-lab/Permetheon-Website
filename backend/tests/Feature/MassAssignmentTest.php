<?php

/**
 * PARITY ROWS 7, 21, 32 — mass-assignment protection and shadow-field bans.
 * Privileged fields never survive; the persisted row carries only the
 * canonical fields; injected phone/whatsapp fields produce no shadow columns.
 */

use App\Models\BusinessInquiry;

it('ignores privileged fields and persists server-controlled status/priority (row 7)', function () {
    $hostile = array_merge(VALID_INQUIRY, [
        'status' => 'WON',
        'priority' => 'URGENT',
        'role' => 'SUPER_ADMIN',
        'id' => 'forged-id',
        'createdAt' => '1999-01-01T00:00:00.000Z',
        'adminId' => 'stolen',
        'permissions' => ['system.security.manage'],
    ]);

    $response = $this->postJson('/api/inquiries', $hostile);
    $response->assertStatus(201);

    $data = $response->json('data');
    expect($data['status'])->toBe('NEW')
        ->and($data['id'])->not->toBe('forged-id')
        ->and($data['createdAt'])->not->toBe('1999-01-01T00:00:00.000Z');

    // ONLY the canonical fields exist on the persisted row's API projection.
    $row = BusinessInquiry::where('email', 'hello@example.com')->first();
    $projected = array_keys($row->toArray());
    expect(sort($projected))->toBeTrue()
        ->and($projected)->toBe([
            'budget', 'company', 'contactNumber', 'createdAt', 'email',
            'id', 'message', 'name', 'priority', 'projectType', 'status', 'timeline', 'updatedAt',
        ]);

    // Server-controlled values, never the injected ones (row 32).
    expect($row->status)->toBe('NEW')
        ->and($row->priority)->toBe('MEDIUM');
});

it('ignores duplicate contact fields — no whatsapp/phone shadow values persisted (row 21)', function () {
    $response = $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'contactNumber' => '+12025550147',
        'whatsappNumber' => '+18005550100',
        'phone' => '+18005550100',
        'contact_phone' => '+18005550100',
    ]));

    $response->assertStatus(201);

    $row = BusinessInquiry::where('email', 'hello@example.com')->first();
    expect($row->contact_number)->toBe('+12025550147')
        ->and($row->getAttribute('phone'))->toBeNull()
        ->and($row->getAttribute('whatsappNumber'))->toBeNull()
        ->and($row->getAttribute('contact_phone'))->toBeNull();
});

it('persists the canonical E.164 value and derives wa.me by stripping the plus (row 32)', function () {
    $this->postJson('/api/inquiries', array_merge(VALID_INQUIRY, [
        'contactNumber' => '+1 202 555 0147', // formatted input
        'email' => 'Moin@Example.com',
    ]))->assertStatus(201);

    $row = BusinessInquiry::where('email', 'moin@example.com')->first();
    expect($row->contact_number)->toBe('+12025550147')
        ->and('https://wa.me/' . substr($row->contact_number, 1))->toBe('https://wa.me/12025550147');
});

it('sets createdAt equal to updatedAt on creation and never fabricates message content (row 9)', function () {
    $this->postJson('/api/inquiries', VALID_INQUIRY)->assertStatus(201);

    $row = BusinessInquiry::where('email', 'hello@example.com')->first();
    expect($row->created_at->toISOString())->toBe($row->updated_at->toISOString())
        ->and($row->message)->toBe(VALID_INQUIRY['message']);
});
