<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessInquiry;
use App\Services\ApiResponse;

/**
 * ADMIN STATS. Real database counts only (spec §39/§40).
 * byStatus carries the complete status vocabulary; last30Days uses the same
 * 30×24h cutoff.
 */
class StatsController extends Controller
{
    public const STATUSES = ['NEW', 'REVIEWING', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'WON', 'LOST'];

    public function index(): JsonResponse
    {
        // MongoDB has no `SELECT status, COUNT(*) … GROUP BY status`. The driver's
        // ->groupBy() compiles to a $group stage whose key lands in `_id`
        // (as {_id: {status: …}}) and whose accumulator is emitted under the
        // literal field name `aggregate` (see Query\Builder::aggregate()).
        // Unknown statuses are ignored, exactly as the SQL version did.
        $byStatus = array_fill_keys(self::STATUSES, 0);
        $rows = BusinessInquiry::query()
            ->aggregateByGroup('count')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $bucket = $row->_id ?? null;
            $status = is_object($bucket) ? ($bucket->status ?? null) : (is_array($bucket) ? ($bucket['status'] ?? null) : $bucket);
            $count = $row->aggregate ?? null;
            if (is_string($status) && array_key_exists($status, $byStatus) && $count !== null) {
                $byStatus[$status] = (int) $count;
            }
        }

        $total = BusinessInquiry::count();
        $cutoff = \App\Support\Clock::offset(-30 * 24 * 60 * 60);
        $last30Days = BusinessInquiry::where('created_at', '>=', $cutoff)->count();

        return ApiResponse::ok([
            'stats' => [
                'total'      => $total,
                'byStatus'   => $byStatus,
                'last30Days' => $last30Days,
            ],
        ]);
    }

    public function store(): JsonResponse
    {
        return ApiResponse::badRequest('Stats are read-only.');
    }
}
