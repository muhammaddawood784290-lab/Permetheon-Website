<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/**
 * 100002 — meeting_blocks: admin-blocked dates (whole day) and individual slots.
 * A block on a date suppresses every slot that day; a block on a specific slot
 * (starts_at) suppresses just that slot. Blocks are independent admin intent —
 * cancelling a meeting does NOT remove a block. blocker_id is nullable because
 * blocks may be seeded by configuration scripts; FK still enforced when set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_blocks', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            // Whole-day block (Y-m-d in the business timezone)…
            $table->date('blocked_date')->nullable();
            // …or a specific slot start (DATETIME(3), business wall time).
            $table->dateTime('starts_at', 3)->nullable();
            $table->string('reason', 255)->default('');
            $table->string('blocker_id', 36)->nullable();
            $table->dateTime('created_at', 3);

            $table->foreign('blocker_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->index('blocked_date');
            $table->index('starts_at');
        });

        SchemaChecks::add('meeting_blocks', [
            'chk_blocks_target' => '(`blocked_date` IS NOT NULL AND `starts_at` IS NULL) OR (`blocked_date` IS NULL AND `starts_at` IS NOT NULL)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_blocks');
    }
};
