<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ADMIN AUDIT LOG (F5) — append-only. The model actively refuses updates
 * and deletes so application code cannot silently rewrite history; pruning
 * is a future, explicitly-operational concern (like session pruning), not a
 * code path. Integer auto-increment id.
 */
class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_audit_log';

    public $incrementing = true;

    protected $keyType = 'integer';

    protected $fillable = [
        'admin_id', 'admin_email', 'action', 'resource_type', 'resource_id', 'summary', 'ip', 'created_at',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('admin_audit_log is append-only.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new \LogicException('admin_audit_log is append-only.');
    }
}
