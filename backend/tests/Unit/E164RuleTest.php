<?php

/**
 * CONTACT NUMBER — normalization + E.164 structural validation table.
 * Every message literal is part of the pinned API contract.
 */

use App\Rules\E164PhoneNumber;

it('normalizes presentation formatting and converts the ITU 00 prefix', function (string $input, ?string $expected) {
    $normalized = E164PhoneNumber::normalize($input);

    if ($expected === null) {
        // Rejected inputs: normalization may produce a candidate, but isValid must fail.
        expect(E164PhoneNumber::isValid($normalized))->toBeFalse();
    } else {
        expect($normalized)->toBe($expected)
            ->and(E164PhoneNumber::isValid($normalized))->toBeTrue();
    }
})->with([
    '+12025550147 canonical' => ['+12025550147', '+12025550147'],
    'spaces normalize' => ['+1 202 555 0147', '+12025550147'],
    'hyphens + brackets normalize' => ['+1-202-555-0147', '+12025550147'],
    'dots + brackets normalize' => ['+1.(202)-555-0147', '+12025550147'],
    'en-dash + unicode minus' => ['+1–202−555-0147', '+12025550147'],
    'ITU 00 prefix converts' => ['0012025550147', '+12025550147'],
    'local-only (no country code) rejected' => ['2025550147', null],
    'bare national (no +) rejected' => ['12025550147', null],
    'unassigned calling code rejected' => ['+999512345678', null],
    'more than 15 digits rejected' => ['+120255501471234', null],
    'empty rejected' => ['', null],
    'country code never guessed' => ['2025550147', null],
]);

it('requires exactly 10 national digits for NANP (+1)', function () {
    expect(E164PhoneNumber::isValid('+12125550123'))->toBeTrue()
        ->and(E164PhoneNumber::isValid('+1212555012'))->toBeFalse();
});
