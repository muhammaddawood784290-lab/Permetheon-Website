<?php

namespace App\Console\Commands;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Support\Clock;
use Illuminate\Console\Command;

/**
 * ADMIN ACCOUNT LISTING — operator visibility without raw SQL access.
 * Shows every account with role, activation state, lockout counters and live
 * session count (the inputs the other admin:* commands act on).
 */
final class AdminList extends Command
{
    protected $signature = 'admin:list';

    protected $description = 'List all admin accounts with role, status and live session count';

    public function handle(): int
    {
        $now = Clock::now();
        $admins = AdminUser::orderBy('email')->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin accounts exist. Use the one-time bootstrap login or admin:create.');

            return self::SUCCESS;
        }

        // Line-per-account (not a wide table): stays readable on narrow
        // terminals where a 7-column table would truncate columns.
        foreach ($admins as $a) {
            $live = AdminSession::where('admin_id', $a->id)
                ->whereNull('revoked_at')
                ->where('absolute_expires_at', '>', $now)
                ->count();
            $lock = $a->locked_until && $a->locked_until > $now
                ? 'LOCKED until '.$a->locked_until
                : 'none';

            $this->line(sprintf('%s | %s (%s)', $a->email, $a->role, $a->name));
            $this->line(sprintf(
                '    active: %s | failed logins: %d | lockout: %s | live sessions: %d',
                $a->isActive() ? 'yes' : 'NO',
                $a->failed_login_count,
                $lock,
                $live
            ));
        }

        return self::SUCCESS;
    }
}
