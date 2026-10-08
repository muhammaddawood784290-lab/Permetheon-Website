<?php

use App\Services\SchemaChecks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/** 100001 — meeting_settings: the availability configuration row.
 *
 * Working days CSV of ISO day numbers (0=Sun..6=Sat), plus the slot grid
 * that defines what "available" means. CHECK constraints are enforced by
 * PostgreSQL.
 *
 * The singleton row is seeded here so a fresh database can be rebuilt with
 * migrate:fresh --seed. `MeetingSetting::current()` reads id = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('working_days', 20)->default('1,2,3,4,5');
            $table->unsignedSmallInteger('day_start_minutes')->default(9 * 60);
            $table->unsignedSmallInteger('day_end_minutes')->default(17 * 60);
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            $table->unsignedSmallInteger('min_lead_time_minutes')->default(120);
            $table->boolean('enabled')->default(true);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });

        SchemaChecks::add('meeting_settings', [
            'chk_ms_days' => "working_days ~ '^[0-6](,[0-6])*$'",
            'chk_ms_slot' => 'slot_duration_minutes BETWEEN 10 AND 240',
            'chk_ms_window' => 'day_start_minutes < day_end_minutes AND day_end_minutes <= 1440',
        ]);

        DB::table('meeting_settings')->insert([
            'working_days' => '1,2,3,4,5',
            'day_start_minutes' => 9 * 60,
            'day_end_minutes' => 17 * 60,
            'slot_duration_minutes' => 30,
            'booking_window_days' => 30,
            'min_lead_time_minutes' => 120,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_settings');
    }
};
