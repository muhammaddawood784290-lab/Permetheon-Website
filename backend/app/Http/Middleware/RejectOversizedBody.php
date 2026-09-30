<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ApiResponse;

/**
 * BODY-SIZE GUARD — the canonical inquiry payload is small; reject oversized
 * bodies early with the standard JSON envelope. The E2E suite exercises this.
 */
class RejectOversizedBody
{
    public const MAX_BYTES = 64 * 1024;

    public function handle(Request $request, Closure $next)
    {
        $length = (int) ($request->header('content-length') ?? 0);

        if ($length > self::MAX_BYTES) {
            return ApiResponse::badRequest('Request body too large.');
        }

        return $next($request);
    }
}
