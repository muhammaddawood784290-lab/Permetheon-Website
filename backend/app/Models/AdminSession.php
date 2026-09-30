<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ADMIN SESSION — the custom guard's session store. Tokens are stored SHA-256
 * hashed; the sliding 30-min idle window + 24-h absolute lifetime live here
 *.
 */
class AdminSession extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'admin_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'admin_id', 'created_at', 'last_seen_at', 'absolute_expires_at', 'revoked_at',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isIdleExpired(): bool
    {
        return strtotime($this->last_seen_at) < time() - 30 * 60;
    }

    public function isAbsolutelyExpired(): bool
    {
        return strtotime($this->absolute_expires_at) <= time();
    }
}
