<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * MEETING TIME — the single canonical clock for the meeting system.
 *
 * UTC is the ONLY time standard: storage, database values, availability rules,
 * conflict checks, admin calendar grouping and every API timestamp are UTC.
 * There is deliberately NO configurable business timezone and NO conversion
 * to or from any geographic zone — the system must behave identically no
 * matter where the server or the customer is located.
 */
final class MeetingTime
{
    /** The canonical timezone. Always 'UTC'. */
    public const TZ = 'UTC';

    /** ISO 8601 UTC wire format (public API + admin API). */
    public const WIRE = 'Y-m-d\\TH:i:s\\Z';

    /** Datetime(3) storage format (MySQL/MariaDB-compatible, UTC wall time). */
    public const STORAGE = 'Y-m-d H:i:s.v';

    /**
     * Accept any of the accepted ISO 8601 UTC inputs and normalize it to the
     * exact storage string ('Y-m-d H:i:s.v', UTC). Accepted:
     *   2026-09-30T10:00:00Z · 2026-09-30T10:00Z · 2026-09-30 10:00
     * ('2026-09-30 10:00' is tolerated for compatibility with clients that
     * predate the ISO wire format; it is interpreted as UTC — never local.)
     *
     * Returns null when the value is not a recognizable instant.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Strict first: ...Z / explicit offset (converted to UTC).
        $carbon = \DateTimeImmutable::createFromFormat(DATE_ATOM, str_replace(' ', 'T', $value), new \DateTimeZone(self::TZ));
        if ($carbon instanceof \DateTimeImmutable) {
            $carbon = $carbon->setTimezone(new \DateTimeZone(self::TZ));

            return $carbon->format(self::STORAGE);
        }

        // Legacy tolerance: 'Y-m-d H:i[:s]' (no zone) — UTC by definition.
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value) === 1) {
            $padded = str_replace('T', ' ', substr($value, 0, 19));
            if (strlen($padded) === 16) {
                $padded .= ':00';
            }

            return $padded . '.000';
        }

        // Internal storage passthrough: 'Y-m-d H:i:s.v' (already canonical UTC).
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,3}))?$/', $value, $m) === 1) {
            $millis = str_pad(substr($m[2] ?? '', 0, 3), 3, '0');

            return $m[1] . '.' . $millis;
        }

        return null;
    }

    /**
     * The value a client may echo back for re-booking: exact UTC instant in
     * the ISO 8601 'Z' wire format.
     */
    public static function toWire(Carbon $dt): string
    {
        return $dt->clone()->setTimezone(new \DateTimeZone(self::TZ))->format(self::WIRE);
    }

    /** Datetime(3) storage string for a Carbon instant. */
    public static function toStorage(Carbon $dt): string
    {
        return $dt->clone()->setTimezone(new \DateTimeZone(self::TZ))->format(self::STORAGE);
    }

    /** Carbon now, in UTC. */
    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /**
     * Contract guard: true when the string is a strict ISO 8601 UTC instant
     * ending in 'Z' (the public/API wire format). Used by tests.
     */
    public static function isWireFormat(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1;
    }
}
