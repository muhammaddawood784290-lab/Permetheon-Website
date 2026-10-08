<?php

namespace App\Models;

use App\Support\MeetingTime;
use Illuminate\Database\Eloquent\Model;

/** MEETING — 1:1 with its inquiry (FK restrict; never orphaned). Times are
 * stored as UTC (the only canonical standard — see App\Support\MeetingTime);
 * serialized API values are ISO 8601 UTC with the 'Z' suffix. The
 * double-booking guard is the partial unique index on (status, starts_at)
 * where status IN ('BOOKED','COMPLETED') — PostgreSQL native, no
 * generated column.
 */
class Meeting extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUSES = ['BOOKED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];
    /** Statuses that hold their slot (unavailable for re-booking). */
    public const SLOT_HOLDING = ['BOOKED', 'COMPLETED'];

    protected $table = 'meetings';

    public $incrementing = true;

    protected $keyType = 'integer';

    protected $fillable = [
        'inquiry_id', 'starts_at', 'duration_minutes', 'status',
        'cancelled_reason', 'cancelled_by', 'cancelled_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'starts_at' => \App\Support\UtcDatetime::class,
        'cancelled_at' => \App\Support\UtcDatetime::class,
        'created_at' => \App\Support\UtcDatetime::class,
        'updated_at' => \App\Support\UtcDatetime::class,
    ];

    /** camelCase serialization to match the JSON contract exactly (ISO 8601 UTC). */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'inquiryId' => $this->inquiry_id,
            'startsAt' => $this->starts_at ? \App\Support\MeetingTime::toWire($this->starts_at) : null,
            'durationMinutes' => (int) $this->duration_minutes,
            'status' => $this->status,
            'cancelledReason' => $this->cancelled_reason,
            'cancelledAt' => $this->cancelled_at ? \App\Support\MeetingTime::toWire($this->cancelled_at) : null,
            'createdAt' => $this->created_at ? \App\Support\MeetingTime::toWire($this->created_at) : null,
            'updatedAt' => $this->updated_at ? \App\Support\MeetingTime::toWire($this->updated_at) : null,
        ];
    }
}
