<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use Illuminate\Console\Command;

/**
 * AUDIT LOG INSPECTION (F5) — the operator view over admin_audit_log.
 * Filterable by admin email substring and/or action; newest first; the
 * summary JSON is printed compactly. Output is ASCII-safe for Windows
 * consoles (the Pest suites assert literal strings here).
 */
final class AdminAudit extends Command
{
    protected $signature = 'admin:audit
                            {--admin= : Filter by admin email (substring, case-insensitive)}
                            {--action= : Filter by exact action, e.g. inquiry.delete}
                            {--limit=25 : How many entries to show (1-200)}';

    protected $description = 'List recent admin audit-log entries (who did what, F5)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 200) {
            $this->error('The --limit option must be between 1 and 200.');

            return self::FAILURE;
        }

        $query = AdminAuditLog::query()->orderByDesc('id');
        $emailFilter = trim((string) $this->option('admin'));
        $actionFilter = trim((string) $this->option('action'));
        if ($emailFilter !== '') {
            $query->where('admin_email', 'like', '%'.strtolower($emailFilter).'%');
        }
        if ($actionFilter !== '') {
            $query->where('action', $actionFilter);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->info('No audit entries match.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $summary = $row->summary !== null && $row->summary !== ''
                ? (string) $row->summary
                : '-';
            $this->line(sprintf(
                '%s | %s | %s | %s#%s | %s',
                substr((string) $row->created_at, 0, 19),
                $row->admin_email,
                $row->action,
                $row->resource_type ?? '-',
                $row->resource_id ?? '-',
                $summary
            ));
        }

        $this->line(sprintf('%d entr%s shown.', $rows->count(), $rows->count() === 1 ? 'y' : 'ies'));

        return self::SUCCESS;
    }
}
