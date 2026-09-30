<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateInquiryRequest;
use App\Models\BusinessInquiry;
use App\Services\ApiResponse;

/**
 * ADMIN INQUIRIES. List: search + status filter +
 * honest pagination (10/page, newest first). Show: full original submission.
 * Update: status/priority only — the original message is never overwritten.
 */
class InquiryController extends Controller
{
    public const PAGE_SIZE = 10;

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $statusParam = (string) $request->query('status', '');
        $page = max(1, (int) $request->query('page', '1'));

        $status = null;
        if ($statusParam !== '') {
            if (! in_array($statusParam, UpdateInquiryRequest::STATUSES, true)) {
                return ApiResponse::validation(['status' => 'Unknown status filter.']);
            }
            $status = $statusParam;
        }

        $query = BusinessInquiry::query();
        if ($search !== '') {
            $query->search($search);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * self::PAGE_SIZE)
            ->take(self::PAGE_SIZE)
            ->get();

        // Meeting state per inquiry (spec §8: list shows No Meeting / BOOKED /
        // COMPLETED / CANCELLED / NO_SHOW). One query for the page.
        $meetings = \App\Models\Meeting::query()
            ->whereIn('inquiry_id', $items->pluck('id')->all())
            ->get()
            ->keyBy('inquiry_id');

        return ApiResponse::ok([
            'inquiries'  => $items->map(function ($i) use ($meetings) {
                $row = $i->toArray();
                $m = $meetings->get($i->id);
                $row['meeting'] = $m
                    ? ['id' => $m->id, 'status' => $m->status, 'startsAt' => \App\Support\MeetingTime::toWire($m->starts_at)]
                    : null;

                return $row;
            })->all(),
            'pagination' => [
                'page'       => $page,
                'pageSize'   => self::PAGE_SIZE,
                'total'      => $total,
                'totalPages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ]);
    }

    public function createBlocked(): JsonResponse
    {
        return ApiResponse::badRequest('Inquiries are created by the public form: POST /api/inquiries.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $inquiry = BusinessInquiry::find($id);
        if (! $inquiry) {
            return ApiResponse::notFound('Inquiry not found.');
        }

        $row = $inquiry->toArray();
        $m = \App\Models\Meeting::query()->where('inquiry_id', $id)->first();
        $row['meeting'] = $m
            ? ['id' => $m->id, 'status' => $m->status, 'startsAt' => \App\Support\MeetingTime::toWire($m->starts_at), 'durationMinutes' => (int) $m->duration_minutes, 'cancelledReason' => $m->cancelled_reason]
            : null;

        return ApiResponse::ok(['inquiry' => $row]);
    }

    public function update(Request $request, string $id, UpdateInquiryRequest $formRequest): JsonResponse
    {
        $inquiry = BusinessInquiry::find($id);
        if (! $inquiry) {
            return ApiResponse::notFound('Inquiry not found.');
        }

        $v = $formRequest->validated();
        if (isset($v['status'])) {
            $inquiry->status = $v['status'];
        }
        if (isset($v['priority'])) {
            $inquiry->priority = $v['priority'];
        }
        $inquiry->updated_at = \App\Support\Clock::now();
        $inquiry->save();

        \App\Services\AuditLog::record($request, 'inquiry.update', 'inquiry', $id, [
            'status' => $v['status'] ?? null, 'priority' => $v['priority'] ?? null,
        ]);

        return ApiResponse::ok(['inquiry' => $inquiry->fresh()->toArray()]);
    }

    /**
     * DELETE an inquiry (spec §7): policy enforced in MeetingPolicy — the
     * meeting (any status) is deleted in the same transaction (a meeting
     * cannot outlive its inquiry); never orphans a meeting, slot is freed.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $inquiry = BusinessInquiry::find($id);
        if (! $inquiry) {
            return ApiResponse::notFound('Inquiry not found.');
        }

        $admin = $request->attributes->get('admin');

        try {
            $outcome = \App\Services\MeetingPolicy::deleteInquiry($inquiry, $admin->id);
        } catch (\App\Services\MeetingConflictException $e) {
            return ApiResponse::conflict($e->getMessage());
        }

        \App\Services\AuditLog::record($request, 'inquiry.delete', 'inquiry', $id, [
            'meeting' => $outcome, // 'none' | 'deleted' (policy string)
        ]);

        return ApiResponse::ok(['deleted' => true, 'id' => $id, 'meeting' => $outcome]);
    }
}
