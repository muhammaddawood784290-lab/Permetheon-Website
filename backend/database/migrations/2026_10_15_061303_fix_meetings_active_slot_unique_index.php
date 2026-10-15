<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS uq_meetings_active_slot'
        );

        DB::statement(
            "CREATE UNIQUE INDEX uq_meetings_active_slot
             ON meetings (starts_at)
             WHERE status IN ('BOOKED', 'COMPLETED')"
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS uq_meetings_active_slot'
        );

        DB::statement(
            "CREATE UNIQUE INDEX uq_meetings_active_slot
             ON meetings (status, starts_at)
             WHERE status IN ('BOOKED', 'COMPLETED')"
        );
    }
};