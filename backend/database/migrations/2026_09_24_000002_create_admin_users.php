<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/** 002 — admin_users: admin accounts with bcrypt password hashes + role CHECK. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('email', 254)->unique();
            $table->string('name');
            $table->string('password_hash', 255);
            $table->string('role')->default('ADMIN');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->dateTime('locked_until', 3)->nullable();
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
        });

        SchemaChecks::add('admin_users', [
            'chk_admins_role' => "`role` IN ('SUPER_ADMIN','ADMIN')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
