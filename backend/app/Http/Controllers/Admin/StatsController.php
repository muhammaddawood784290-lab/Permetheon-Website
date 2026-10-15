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
        // PostgreSQL-compatible aggregation; preserve the complete status vocabulary.
        $byStatus = array_fill_keys(self::STATUSES, 0);
        $rows = BusinessInquiry::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            $count = $row->aggregate ?? null;
            if (array_key_exists($status, $byStatus) && $count !== null) {
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
