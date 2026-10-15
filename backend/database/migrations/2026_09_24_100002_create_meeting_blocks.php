<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 100002 — meeting_blocks: admin-blocked dates (whole day) and individual slots.
 *
 * A block on a date suppresses every slot that day; a block on a specific
 * slot (starts_at) suppresses just that slot. Blocks are independent admin
 * intent — cancelling a meeting does NOT remove a block.
 *
 * CHECK constraint enforces exactly-one-target: either blocked_date set and
 * starts_at NULL, or starts_at set and blocked_date NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_blocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('blocked_date')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->string('reason', 255)->default('');
            $table->unsignedBigInteger('blocker_id')->nullable();
            $table->timestamp('created_at');
            $table->foreign('blocker_id')->references('id')->on('admin_users')->onDelete('set null');
            $table->index('blocked_date');
            $table->index('starts_at');
        });

        SchemaChecks::add('meeting_blocks', [
            'chk_blocks_target' => '(blocked_date IS NOT NULL AND starts_at IS NULL) OR (blocked_date IS NULL AND starts_at IS NOT NULL)',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_blocks');
    }
};
