<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\BusinessInquiry;
use App\Support\Clock;
use Illuminate\Support\Facades\DB;

/**
 * MEETING POLICY — status transitions + the inquiry-deletion rule (spec §6/§7).
 *
 * Deletion policy (documented, backend-enforced): a meeting cannot outlive its
 * inquiry (spec §4 — the FK is RESTRICT), so deleting an inquiry DELETES its
 * meeting row (any status) in the same transaction — no orphans, and the slot
 * is freed. config('meetings.inquiry_delete_policy') controls the guarded
 * variant: 'delete_meeting' (default) always permits; 'prevent' refuses while
 * the meeting is still BOOKED (cancel it first via the normal operational
 * path — cancellation preserves history while the inquiry lives).
 */
class MeetingPolicy
{
    /** Allowed transitions. CANCELLED is terminal for re-booking; COMPLETED too. */
    private const TRANSITIONS = [
        'BOOKED' => ['COMPLETED', 'CANCELLED', 'NO_SHOW'],
        'COMPLETED' => [],
        'CANCELLED' => [],
        'NO_SHOW' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Cancel (normal operational path) — preserves the row, frees the slot. */
    public static function cancel(Meeting $meeting, string $byAdminId, string $reason = ''): Meeting
    {
        if (! self::canTransition($meeting->status, 'CANCELLED')) {
            throw new MeetingConflictException("A {$meeting->status} meeting cannot be cancelled.");
        }

        $meeting->status = 'CANCELLED';
        $meeting->cancelled_reason = mb_substr($reason, 0, 255);
        $meeting->cancelled_by = $byAdminId;
        $meeting->cancelled_at = Clock::now();
        $meeting->updated_at = Clock::now();
        $meeting->save();

        return $meeting;
    }

    /**
     * Delete an inquiry under the documented policy. Returns what happened to
     * the meeting: 'deleted' | 'none'.
     */
    public static function deleteInquiry(BusinessInquiry $inquiry, string $byAdminId): string
    {
        return DB::transaction(function () use ($inquiry, $byAdminId) {
            $meeting = Meeting::query()->where('inquiry_id', $inquiry->id)->first();

            $outcome = 'none';
            if ($meeting !== null) {
                $policy = (string) config('meetings.inquiry_delete_policy', 'delete_meeting');

                if ($policy === 'prevent' && $meeting->status === 'BOOKED') {
                    throw new MeetingConflictException(
                        'This inquiry has a booked meeting. Cancel the meeting first, then delete the inquiry.'
                    );
                }

                // The meeting cannot outlive its inquiry (RESTRICT FK) — it is
                // removed in the same transaction. Its slot is freed.
                $meeting->delete();
                $outcome = 'deleted';
            }

            // Notes cascade via FK; the meeting is already gone (above).
            $inquiry->delete();

            return $outcome;
        });
    }
}
