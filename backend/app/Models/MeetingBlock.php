<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MEETING BLOCK — an admin block on a whole DATE (blocked_date set, starts_at
 * NULL) or on a single SLOT (starts_at set, blocked_date NULL). A CHECK
 * constraint enforces exactly-one-target (see migration 100002). Blocks are
 * independent of meeting status: they are admin intent, never auto-removed.
 */
class MeetingBlock extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $table = 'meeting_blocks';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'blocked_date', 'starts_at', 'reason', 'blocker_id', 'created_at',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $casts = [
        'starts_at' => \App\Support\UtcDatetime::class,
        'created_at' => \App\Support\UtcDatetime::class,
    ];
}
