<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

/**
 * UTC-PINNED DATETIME CAST.
 *
 * Eloquent's default 'datetime' cast parses naive database strings
 * ('2026-09-30 10:00:00.000') using the application's configured timezone.
 * If the server is moved and app.timezone changes, every parsed timestamp
 * silently shifts — exactly the failure mode the UTC-canonical requirement
 * forbids ("moving the server must not change existing timestamps").
 *
 * This cast pins BOTH directions to UTC: values are read as UTC wall time
 * and written back in MeetingTime::STORAGE ('Y-m-d H:i:s.v'), independent of
 * app.timezone, the PHP process timezone and the database server's zone.
 */
final class UtcDatetime implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->clone()->utc();
        }

        return Carbon::parse($value, MeetingTime::TZ)->setTimezone(MeetingTime::TZ);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return MeetingTime::toStorage(Carbon::instance($value));
        }
        if (is_numeric($value)) {
            return MeetingTime::toStorage(Carbon::createFromTimestamp((int) $value, MeetingTime::TZ));
        }

        $normalized = MeetingTime::normalize(trim((string) $value));
        if ($normalized !== null) {
            return $normalized;
        }

        // Unknown shape: let the database surface it rather than guess.
        return (string) $value;
    }
}
