<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meeting_settings')) {
            return;
        }

        if (!Schema::hasColumn('meeting_settings', 'slot_interval_minutes')) {
            Schema::table('meeting_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('slot_interval_minutes')->default(60);
            });
        }

        if (!Schema::hasColumn('meeting_settings', 'meeting_duration_minutes')) {
            Schema::table('meeting_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('meeting_duration_minutes')->default(30);
            });
        }

        if (Schema::hasColumn('meeting_settings', 'slot_duration_minutes')) {
            DB::statement(
                'UPDATE meeting_settings
                 SET slot_interval_minutes = slot_duration_minutes
                 WHERE slot_duration_minutes IS NOT NULL'
            );

            Schema::table('meeting_settings', function (Blueprint $table) {
                $table->dropColumn('slot_duration_minutes');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty.
        // This compatibility migration must not remove columns
        // that may belong to the original meeting_settings schema.
    }
};