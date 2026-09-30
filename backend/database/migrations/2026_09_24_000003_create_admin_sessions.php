<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 003 — admin_sessions: opaque-token custom-guard sessions. The id is
 * the SHA-256 hash of the cookie token — a DB leak yields no usable credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('admin_id', 36);
            $table->dateTime('created_at', 3);
            $table->dateTime('last_seen_at', 3);
            $table->dateTime('absolute_expires_at', 3);
            $table->dateTime('revoked_at', 3)->nullable();
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};
