<?php

namespace App\Support;

/**
 * PORTABLE MILLIS CLOCK.
 *
 * PHP's DateTime::format('v') (milliseconds) is broken on Windows PHP builds —
 * it always returns `.000` even though microtime(true) carries a real fraction.
 * That silently collapses every DATETIME(3) timestamp to whole seconds, which
 * makes `ORDER BY created_at DESC` nondeterministic on MySQL when rows are
 * created within the same second.
 *
 * This helper formats timestamps from microtime(true) instead, so millisecond
 * precision survives on every platform. All persistence code must use it
 * rather than gmdate()/date() for 'Y-m-d H:i:s.v' values.
 */
final class Clock
{
    /** 'Y-m-d H:i:s.v' in UTC, milliseconds from microtime(true). */
    public static function now(): string
    {
        return self::format(null);
    }

    /** 'Y-m-d H:i:s.v' in UTC for a unix timestamp (floats keep their fraction). */
    public static function format(?float $unixTs = null): string
    {
        $ts = $unixTs ?? microtime(true);
        $secs = floor($ts);
        $millis = (int) round(($ts - $secs) * 1000);

        // Rounding can push .9995 over the second boundary.
        if ($millis === 1000) {
            $secs += 1;
            $millis = 0;
        }

        return gmdate('Y-m-d H:i:s', (int) $secs) . '.' . sprintf('%03d', $millis);
    }

    /** 'Y-m-d H:i:s.v' in UTC, offset by a number of seconds (float ok). */
    public static function offset(float $seconds): string
    {
        return self::format(microtime(true) + $seconds);
    }
}
