<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 002 — admin_users: admin accounts with bcrypt password hashes + role CHECK.
 *
 * role CHECK narrows the vocabulary to ('SUPER_ADMIN','ADMIN'). The MANAGER
 * role is added in 2026_09_30_000001 (widening). CHECK constraints are
 * enforced by PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 254)->unique();
            $table->string('name');
            $table->string('password_hash', 255);
            $table->string('role')->default('ADMIN');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        SchemaChecks::add('admin_users', [
            'chk_admins_role' => "role IN ('SUPER_ADMIN','ADMIN')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
