<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — ADMIN AUDIT LOG: append-only record of every successful admin
 * mutation (who did what to which resource, from where). No updates, no
 * deletes by application code — the table exists so actions are answerable
 * after the fact. `action` is a stable machine string (e.g.
 * 'inquiry.update'), `summary` a small JSON snapshot of what was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('admin_id', 36)->index();
            $table->string('admin_email', 254); // denormalized: survives account deletion
            $table->string('action', 64)->index();
            $table->string('resource_type', 32)->nullable()->index();
            $table->string('resource_id', 64)->nullable();
            $table->json('summary')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at', 3)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
    }
};
