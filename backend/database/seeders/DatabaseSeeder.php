<?php

namespace Database\Seeders;

use App\Support\Clock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * APPLICATION SEEDER — MySQL project.
 *
 * Seeds the meeting_settings singleton (id = 'singleton'). The meeting
 * availability engine requires exactly this row; `php artisan migrate`
 * creates the table but the row's canonical home here is the seeder so a
 * fresh database can be rebuilt with migrate:fresh --seed.
 *
 * The first admin account is NOT seeded: it is minted by the one-time
 * bootstrap login (ADMIN_BOOTSTRAP_EMAIL + ADMIN_BOOTSTRAP_PASSWORD env vars)
 * which permanently wipes the bootstrap password after first use — see
 * App\Services\AdminAuthService and README step 6.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::table('meeting_settings')->updateOrInsert(
            ['id' => 'singleton'],
            [
                'working_days' => '1,2,3,4,5',
                'day_start_minutes' => 9 * 60,   // 09:00 UTC
                'day_end_minutes' => 17 * 60,    // 17:00 UTC (last slot must END by this)
                'slot_duration_minutes' => 30,
                'booking_window_days' => 30,
                'min_lead_time_minutes' => 120,
                'enabled' => true,
                'created_at' => Clock::now(),
                'updated_at' => Clock::now(),
            ],
        );
    }
}
