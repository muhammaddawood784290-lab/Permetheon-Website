<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Support\Clock;
use Illuminate\Console\Command;

/**
 * LOCKOUT RECOVERY (F4) — the failed-login lockout is a deliberate DoS lever
 * against NAMED accounts: anyone who knows an admin's email can keep it
 * locked with 5 wrong passwords (per-IP throttles bound the rate, not the
 * total). This is the operator's recovery tool: clear the lock + failure
 * counter for one account. It does NOT change the password — that is
 * admin:reset-password.
 */
final class AdminUnlock extends Command
{
    protected $signature = 'admin:unlock {email : The locked admin account}';

    protected $description = 'Clear the failed-login lockout for an admin account (lockout recovery, F4)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $admin = AdminUser::where('email', $email)->first();

        if ($admin === null) {
            $this->error("No admin account found for {$email}.");

            return self::FAILURE;
        }

        if ($admin->locked_until === null && (int) $admin->failed_login_count === 0) {
            $this->info("{$admin->email} is not locked (no lock timestamp, no failed attempts).");

            return self::SUCCESS;
        }

        $wasLocked = $admin->locked_until !== null && strtotime($admin->locked_until) > time();

        $admin->failed_login_count = 0;
        $admin->locked_until = null;
        $admin->updated_at = Clock::now();
        $admin->save();

        // Mirror to the log so lockout DoS attempts and operator responses
        // leave a trace (F4 recommendation: make the DoS visible).
        \Illuminate\Support\Facades\Log::warning('admin.lockout.cleared', [
            'admin_id' => $admin->id,
            'email'    => $admin->email,
            'was_locked' => $wasLocked,
        ]);

        $this->info($wasLocked
            ? "Lock cleared for {$admin->email} — the account can log in again now."
            : "Failed-attempt counter cleared for {$admin->email}.");

        return self::SUCCESS;
    }
}
