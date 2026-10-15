<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * This migration aligns the meeting_settings schema to the updated project spec.
 * It renames 'slot_duration_minutes' to 'slot_interval_minutes' and adds 'meeting_duration_minutes'.
 *
 * Backward Compatibility:
 * - Preserves existing values by copying 'slot_duration_minutes' to 'slot_interval_minutes'.
 * - Provides a safe default for 'meeting_duration_minutes'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_settings', function (Blueprint $table) {
            // 1. Add new columns first to avoid data loss during rename
            if (!Schema::hasColumn('meeting_settings', 'slot_interval_minutes')) {
                $table->unsignedSmallInteger('slot_interval_minutes')->default(60)->after('day_end_minutes');
            }
            if (!Schema::hasColumn('meeting_settings', 'meeting_duration_minutes')) {
                $table->unsignedSmallInteger('meeting_duration_minutes')->default(30)->after('slot_interval_minutes');
            }
        });

        // 2. Migrate data from old column to new interval column if not already done
        if (Schema::hasColumn('meeting_settings', 'slot_duration_minutes')) {
            DB::statement("UPDATE meeting_settings SET slot_interval_minutes = slot_duration_minutes");

            Schema::table('meeting_settings', function (Blueprint $table) {
                $table->dropColumn('slot_duration_minutes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30)->after('day_end_minutes');
        });

        DB::statement("UPDATE meeting_settings SET slot_duration_minutes = slot_interval_minutes");

        Schema::table('meeting_settings', function (Blueprint $table) {
            $table->dropColumn(['slot_interval_minutes', 'meeting_duration_minutes']);
        });
    }
};
