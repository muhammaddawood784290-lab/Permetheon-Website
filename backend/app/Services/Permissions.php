<?php

namespace App\Services;

/**
 * PERMISSION MODEL — explicit, server-side enforced.
 * Port of src/lib/admin/permissions.ts. Frontend visibility is never
 * authorization; every admin API route re-checks here.
 */
class Permissions
{
    public const ALL = [
        'inquiries.read',
        'inquiries.update',
        'inquiries.delete',
        'inquiries.notes.read',
        'inquiries.notes.create',
        'inquiries.stats.read',
        'admins.read',
        'admins.create',
        'admins.update',
        'admins.delete',
        'admins.role.update',
        'system.auth.manage',
        'system.security.manage',
        // Meetings extension (spec §3/§7/§10) — SUPER_ADMIN only by default.
        'meetings.read',
        'meetings.update',
        'meetings.delete',
        'availability.write',
    ];

    private const ADMIN_ROLE_PERMISSIONS = [
        'inquiries.read',
        'inquiries.update',
        'inquiries.notes.read',
        'inquiries.notes.create',
        'inquiries.stats.read',
        // Meeting read access for the regular ADMIN role (spec §3: admins view
        // the calendar). WRITE actions (status, cancel, delete, availability
        // config, blocks) stay SUPER_ADMIN — tighten later if needed.
        'meetings.read',
    ];

    /**
     * MANAGER — full operational control EXCEPT user administration.
     * Everything SUPER_ADMIN has, minus minting, deleting or re-roling admin
     * accounts (role changes grant equivalent authority — escalation guard).
     */
    private const MANAGER_EXCLUDED = [
        'admins.create',
        'admins.delete',
        'admins.role.update',
    ];

    /** @return string[] */
    public static function forRole(string $role): array
    {
        return match (true) {
            $role === 'SUPER_ADMIN' => self::ALL,
            $role === 'MANAGER' => array_values(array_diff(self::ALL, self::MANAGER_EXCLUDED)),
            default => self::ADMIN_ROLE_PERMISSIONS,
        };
    }

    public static function has(string $role, string $permission): bool
    {
        return in_array($permission, self::forRole($role), true);
    }
}
