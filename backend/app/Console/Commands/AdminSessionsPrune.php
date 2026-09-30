<?php

namespace App\Console\Commands;

use App\Models\AdminSession;
use App\Support\Clock;
use Illuminate\Console\Command;

/**
 * ADMIN SESSION PRUNING (F8) — `admin_sessions` grows forever otherwise:
 * idle-expired rows are REVOKED (never deleted, replay-safety G2) and revoked
 * rows are never cleaned up. This command deletes rows that were already
 * dead N days ago:
 *
 *   - revoked (manual logout, rotation, idle-expiry, admin deletion), or
 *   - absolutely expired (24h lifetime), with the REVOCATION/EXPIRY moment —
 *     not the last activity — older than the cutoff, so the retention window
 *     is measured from when the row became unusable.
 *
 * LIVE rows are never touched: anything still valid (unrevoked, not expired)
 * survives regardless of age. Run via the scheduler (daily) or manually.
 * The cutoff is env-tunable: SESSIONS_PRUNE_DAYS (default 30).
 */
final class AdminSessionsPrune extends Command
{
    protected $signature = 'admin:prune-sessions
                            {--days= : Retention window in days (default: SESSIONS_PRUNE_DAYS env, else 30)}
                            {--dry-run : Count what would be deleted without deleting}';

    protected $description = 'Delete revoked/expired admin sessions older than the retention window (F8)';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        if ($daysOption !== null && (! ctype_digit((string) $daysOption) || (int) $daysOption < 1)) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $days = $daysOption !== null
            ? (int) $daysOption
            : max(1, (int) env('SESSIONS_PRUNE_DAYS', 30));
        // Cutoff in seconds; Clock works in epoch floats, so seconds are fine.
        $cutoff = Clock::format(time() - $days * 24 * 60 * 60);

        $query = AdminSession::query()
            ->where(function ($q) use ($cutoff) {
                $q->where(fn ($qq) => $qq->whereNotNull('revoked_at')->where('revoked_at', '<', $cutoff))
                    ->orWhere(fn ($qq) => $qq->whereNull('revoked_at')->where('absolute_expires_at', '<', $cutoff));
            });

        if ($this->option('dry-run')) {
            $count = (clone $query)->count();
            $this->info("DRY RUN: {$count} dead session(s) older than {$days} day(s) would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} dead session(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
