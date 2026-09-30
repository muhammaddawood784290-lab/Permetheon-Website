<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\SchemaChecks;

/**
 * 100001 — meeting_settings: the availability configuration row (single row,
 * id = 1). Working days + slot grid define what "available" means; everything
 * else (blocks, bookings) subtracts from it. Business timezone lives in
 * config/meetings.php (environment-driven, §12 of the meetings spec) — NOT here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_settings', function (Blueprint $table) {
            $table->string('id', 36)->primary(); // single row: 'singleton'
            // Working days as CSV of ISO day numbers 0=Sun..6=Sat (e.g. "1,2,3,4,5").
            $table->string('working_days', 20)->default('1,2,3,4,5');
            // Slot grid: first slot start / last slot start (minutes from midnight, business tz).
            $table->unsignedSmallInteger('day_start_minutes')->default(9 * 60);   // 09:00
            $table->unsignedSmallInteger('day_end_minutes')->default(17 * 60);    // 17:00 (last slot must END by this)
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            // How many days ahead the public calendar shows.
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            // Lead time: a slot must start at least this many minutes in the future.
            $table->unsignedSmallInteger('min_lead_time_minutes')->default(120);
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at', 3);
            $table->dateTime('updated_at', 3);
        });

        SchemaChecks::add('meeting_settings', [
            'chk_ms_days' => "`working_days` REGEXP '^[0-6](,[0-6])*$'",
            'chk_ms_slot' => '`slot_duration_minutes` BETWEEN 10 AND 240',
            'chk_ms_window' => "`day_start_minutes` < `day_end_minutes` AND `day_end_minutes` <= 1440",
        ]);

        // Seed the singleton row.
        DB::table('meeting_settings')->insert([
            'id' => 'singleton',
            'working_days' => '1,2,3,4,5',
            'day_start_minutes' => 9 * 60,
            'day_end_minutes' => 17 * 60,
            'slot_duration_minutes' => 30,
            'booking_window_days' => 30,
            'min_lead_time_minutes' => 120,
            'enabled' => true,
            'created_at' => '2026-09-25 00:00:00.000',
            'updated_at' => '2026-09-25 00:00:00.000',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_settings');
    }
};
