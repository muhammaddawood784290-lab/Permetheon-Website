<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ADMIN USER — same columns/semantics as the current admin_users table. */
class AdminUser extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $table = 'admin_users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'email', 'name', 'password_hash', 'role', 'is_active',
        'failed_login_count', 'locked_until', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isActive(): bool
    {
        return $this->is_active === true || $this->is_active === 1;
    }
}
