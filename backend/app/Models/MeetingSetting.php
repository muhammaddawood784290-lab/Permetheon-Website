<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MEETING SETTINGS — singleton row (id='singleton'). Working days are CSV of
 * ISO day numbers 0=Sun..6=Sat. Everything is UTC semantics
 * (App\Support\MeetingTime) — the model stores only minutes-from-midnight.
 */
class MeetingSetting extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'meeting_settings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'working_days', 'day_start_minutes', 'day_end_minutes',
        'slot_duration_minutes', 'booking_window_days', 'min_lead_time_minutes',
        'enabled', 'created_at', 'updated_at',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return int[] ISO day numbers, sorted. */
    public function workingDays(): array
    {
        $days = array_map('intval', explode(',', (string) $this->working_days));

        return array_values(array_unique(array_filter($days, fn ($d) => $d >= 0 && $d <= 6)));
    }

    public static function current(): self
    {
        return static::query()->findOrFail('singleton');
    }
}
