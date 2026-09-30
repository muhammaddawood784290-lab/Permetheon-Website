<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/**
 * Adds the MANAGER role to admin_users.
 *
 * MANAGER = full operational control (inquiries, meetings, availability,
 * blocks) WITHOUT user administration (no minting, deleting or re-roling
 * admin accounts). Mirrors Permissions::MANAGER_EXCLUDED in
 * app/Services/Permissions.php — the DB-level CHECK keeps the schema
 * authoritative so an out-of-band INSERT cannot mint an unknown role.
 *
 * NOTE (down): reverting fails if any MANAGER rows exist — demote them first.
 */
return new class extends Migration
{
    private const WIDENED = "`role` IN ('SUPER_ADMIN','MANAGER','ADMIN')";

    public function up(): void
    {
        if (! Schema::hasTable('admin_users')) {
            return;
        }

        DB::statement('ALTER TABLE `admin_users` DROP CONSTRAINT `chk_admins_role`');
        SchemaChecks::add('admin_users', [
            'chk_admins_role' => self::WIDENED,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_users')) {
            return;
        }

        DB::statement('ALTER TABLE `admin_users` DROP CONSTRAINT `chk_admins_role`');
        SchemaChecks::add('admin_users', [
            'chk_admins_role' => "`role` IN ('SUPER_ADMIN','ADMIN')",
        ]);
    }
};
