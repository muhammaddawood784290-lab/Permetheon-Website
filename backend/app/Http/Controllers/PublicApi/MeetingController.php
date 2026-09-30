<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Services\MeetingAvailability;
use App\Services\ApiResponse;

/**
 * PUBLIC MEETINGS — read-only availability (spec §2/§10). No internal admin
 * data is exposed: only dates, slots and their public statuses.
 */
class MeetingController extends Controller
{
    public function availability(): JsonResponse
    {
        try {
            return ApiResponse::ok(['availability' => MeetingAvailability::make()->calendar()]);
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::server();
        }
    }
}
