<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SESSIONS TABLE — present for framework completeness (SESSION_DRIVER=database).
 *
 * The admin authentication system does NOT use it: admin sessions live in the
 * dedicated admin_sessions table with SHA-256-hashed opaque tokens, a sliding
 * 30-minute idle window and a 24-hour absolute lifetime
 * (App\Services\AdminAuthService). Laravel's session machinery is disabled on
 * both the web and API middleware stacks (bootstrap/app.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
