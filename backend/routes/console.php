<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| Artisan command registrations (Laravel 11/12 style: this file is loaded by
| bootstrap/app.php withRouting(commands: ...)).
|
| SCHEDULING: `php artisan schedule:work` (or a cron'd schedule:run) must run
| in production for the F8 session pruning below to happen automatically.
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F8 — admin_sessions hygiene: rows are only ever revoked (replay-safety),
// never deleted, so the table grows forever without this daily prune.
// Retention window: SESSIONS_PRUNE_DAYS env (default 30 days). Every live
// session is untouched regardless of age.
Schedule::command('admin:prune-sessions')->dailyAt('03:20');
