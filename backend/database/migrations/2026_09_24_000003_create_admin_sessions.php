<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 003 — admin_sessions: opaque-token custom-guard sessions.
 *
 * Tokens are stored SHA-256 hashed; the sliding 30-min idle window +
 * 24-h absolute lifetime live here. admin_id FK references admin_users.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token_hash', 64)->index()->comment('SHA-256 hex of the session token');
            $table->unsignedBigInteger('admin_id')->index();
            $table->timestamp('created_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('absolute_expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreign('admin_id')->references('id')->on('admin_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};
