<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E.164 CONTACT NUMBER — PHP port of the shared contract in
 * src/lib/inquiries.ts. Byte-identical
 * semantics: normalize presentation formatting, ITU 00 prefix, reject numbers
 * without +, unassigned calling codes, >15 digits; NANP/+7 fixed 10-digit
 * national length; variable-length plans 4–12 national digits; the country
 * code is NEVER guessed.
 */
class E164PhoneNumber implements ValidationRule
{
    public const MISSING_MESSAGE = 'Please enter your contact number.';
    public const INVALID_MESSAGE = 'Enter a valid international contact number, e.g. +12025550147.';

    private const E164_PATTERN = '/^\+[1-9]\d{1,14}$/';

    /** Assigned ITU country calling codes (copied verbatim from inquiries.ts). */
    private const COUNTRY_CODES = [
        '1', '7', '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41',
        '43', '44', '45', '46', '47', '48', '49', '51', '52', '53', '54', '55', '56',
        '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84', '86',
        '90', '91', '92', '93', '94', '95', '98',
        '211', '212', '213', '216', '218', '220', '221', '222', '223', '224', '225',
        '226', '227', '228', '229', '230', '231', '232', '233', '234', '235', '236',
        '237', '238', '239', '240', '241', '242', '243', '244', '245', '246', '247',
        '248', '249', '250', '251', '252', '253', '254', '255', '256', '257', '258',
        '260', '261', '262', '263', '264', '265', '266', '267', '268', '269', '290',
        '291', '297', '298', '299', '350', '351', '352', '353', '354', '355', '356',
        '357', '358', '359', '370', '371', '372', '373', '374', '375', '376', '377',
        '378', '379', '380', '381', '382', '383', '385', '386', '387', '389', '420',
        '421', '423', '500', '501', '502', '503', '504', '505', '506', '507', '508',
        '509', '590', '591', '592', '593', '594', '595', '596', '597', '598', '599',
        '670', '672', '673', '674', '675', '676', '677', '678', '679', '680', '681',
        '682', '683', '684', '685', '686', '687', '688', '689', '690', '691', '692',
        '850', '852', '853', '855', '856', '870', '878', '880', '881', '882', '883',
        '886', '960', '961', '962', '963', '964', '965', '966', '967', '968', '970',
        '971', '972', '973', '974', '975', '976', '977', '992', '993', '994', '995',
        '996', '998',
    ];

    /** Numbering plans with a fixed national (significant) number length. */
    private const FIXED_NATIONAL_LENGTH = ['1' => 10, '7' => 10];

    public static function normalize(string $raw): string
    {
        // Strip presentation formatting: whitespace, dots, hyphens (incl. en/em
        // dashes and the Unicode minus), brackets, braces — matches the TS regex
        // [\s().[\]{}\-\u2013\u2014\u2212] exactly (inquiries.ts line 116).
        $compact = preg_replace('/[\s().\[\]{}\-–—−]/u', '', trim($raw)) ?? '';

        return str_starts_with($compact, '00') ? '+' . substr($compact, 2) : $compact;
    }

    public static function isValid(string $e164): bool
    {
        if (preg_match(self::E164_PATTERN, $e164) !== 1) {
            return false;
        }
        $digits = substr($e164, 1);

        // Longest-match probe: 3 → 2 → 1 digit calling codes, same as the TS loop.
        foreach ([3, 2, 1] as $length) {
            $callingCode = substr($digits, 0, $length);
            if (! in_array($callingCode, self::COUNTRY_CODES, true)) {
                continue;
            }
            $national = substr($digits, $length);
            $fixed = self::FIXED_NATIONAL_LENGTH[$callingCode] ?? null;

            return $fixed !== null
                ? strlen($national) === $fixed
                : strlen($national) >= 4 && strlen($national) <= 12;
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = self::normalize(is_string($value) ? $value : '');

        if ($normalized === '') {
            $fail(self::MISSING_MESSAGE);

            return;
        }
        if (! self::isValid($normalized)) {
            $fail(self::INVALID_MESSAGE);
        }
    }
}
