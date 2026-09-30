<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\BusinessInquiry;
use App\Services\ApiResponse;
use App\Services\MeetingAvailability;
use App\Services\MeetingConflictException;

/**
 * PUBLIC INQUIRY SUBMISSION. Contract: valid → 201
 * {id, status:"NEW", createdAt} · invalid → 422 {fields} · malformed → 400 ·
 * rate-limited → 429 (via throttle:inquiry) · failure → 500.
 *
 * MEETINGS (spec §1/§5): when the payload carries meeting.booking=true with a
 * startsAt, the inquiry AND meeting are created in ONE transaction —
 * availability is re-checked server-side inside it, and the DB unique
 * active-slot guard closes any race. Slot conflicts → 409 with a clear message;
 * the inquiry is rolled back too (atomic — no inquiry without its meeting).
 */
class InquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Malformed JSON → 400 (row 20); non-record payloads (null/42/"x"/[..])
        // → 422 with a `form` error, never a 500 (row 8).
        $content = $request->getContent();
        $body = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }
        if (! is_array($body) || array_is_list($body)) {
            return ApiResponse::validation(['form' => 'Invalid request payload.']);
        }

        try {
            // Manual FormRequest resolution so the non-record guard runs first
            // and validation errors surface as our 422 envelope (G7).
            $formRequest = StoreInquiryRequest::createFromBase($request);
            $formRequest->setContainer(app());
            $formRequest->validateResolved();
            $canonical = $formRequest->canonicalPayload();

            // Optional meeting booking (spec §1): defaults to NO meeting.
            $meetingWanted = false;
            $meetingStartsAt = null;
            if (is_array($body['meeting'] ?? null)) {
                $booking = $body['meeting'];
                $meetingWanted = ($booking['booking'] ?? null) === true
                    || ($booking['booking'] ?? null) === 1
                    || ($booking['booking'] ?? null) === 'true';
                $startsAt = $booking['startsAt'] ?? null;
                if ($meetingWanted && is_string($startsAt)
                    && ($normalized = \App\Support\MeetingTime::normalize($startsAt)) !== null) {
                    $meetingStartsAt = $normalized; // storage format (UTC)
                } elseif ($meetingWanted) {
                    return ApiResponse::validation(['meeting' => 'Please choose a meeting time, or continue without booking.']);
                }
            }

            $now = \App\Support\Clock::now();
            $availability = MeetingAvailability::make();

            // ATOMIC: inquiry + meeting commit together; conflict rolls back both.
            $result = DB::transaction(function () use ($canonical, $now, $availability, $meetingWanted, $meetingStartsAt) {
                $inquiry = BusinessInquiry::create(array_merge($canonical, [
                    'id'         => (string) Str::uuid(),
                    'status'     => 'NEW',
                    'priority'   => 'MEDIUM', // DEFAULT_PRIORITY
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));

                if ($meetingWanted && $meetingStartsAt !== null) {
                    $settings = \App\Models\MeetingSetting::current();
                    $availability->bookForInquiry($inquiry->id, $meetingStartsAt, (int) $settings->slot_duration_minutes);

                    return ['inquiry' => $inquiry, 'meeting' => \App\Support\MeetingTime::toWire(\Illuminate\Support\Carbon::parse($meetingStartsAt, \App\Support\MeetingTime::TZ))];
                }

                return ['inquiry' => $inquiry, 'meeting' => null];
            });

            $inquiry = $result['inquiry'];

            return ApiResponse::ok([
                'id'        => $inquiry->id,
                'status'    => $inquiry->status,
                'createdAt' => $inquiry->created_at,
                'meeting'   => $result['meeting'] !== null
                    ? ['startsAt' => $result['meeting'], 'status' => 'BOOKED']
                    : null,
            ], 201);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e; // validation envelope — pass through untouched
        } catch (MeetingConflictException $e) {
            // Slot taken / not bookable (spec §5): clear conflict, nothing created.
            return ApiResponse::error(409, 'SLOT_CONFLICT', $e->getMessage());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // The unique_slot_guard caught a concurrent booking (spec §5).
            return ApiResponse::error(409, 'SLOT_CONFLICT', 'That time slot has just been taken. Please choose another available slot.');
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::server();
        }
    }
}
