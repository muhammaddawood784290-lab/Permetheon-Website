<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use App\Support\Clock;
use Illuminate\Http\Request;

/**
 * AUDIT LOG WRITER (F5) — one call per successful admin mutation, made INSIDE
 * the controller after the change is committed. Failures to record are logged
 * but never break the API response: the mutation succeeded; losing an audit
 * row must not 500 a delete that already happened.
 */
class AuditLog
{
    /**
     * @param string $action stable machine string, e.g. 'inquiry.update'
     * @param string|null $resourceType e.g. 'inquiry', 'meeting', 'admin_user', 'meeting_block', 'availability'
     * @param string|null $resourceId the affected row id (or null for config-like actions)
     * @param array $summary small JSON snapshot of the change (never passwords)
     */
    public static function record(
        Request $request,
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $summary = [],
    ): void {
        try {
            $admin = $request->attributes->get('admin');
            if (! $admin instanceof AdminUser) {
                return; // not an authenticated admin action — nothing to attribute
            }

            AdminAuditLog::create([
                'admin_id'     => $admin->id,
                'admin_email'  => $admin->email,
                'action'       => $action,
                'resource_type' => $resourceType,
                'resource_id'  => $resourceId,
                'summary'      => $summary === [] ? null : json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'ip'           => (string) ($request->ip() ?? ''),
                'created_at'   => Clock::now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
