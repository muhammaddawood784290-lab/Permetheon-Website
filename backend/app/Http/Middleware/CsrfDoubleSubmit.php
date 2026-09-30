<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AdminAuthService;
use App\Services\ApiResponse;
use App\Services\CsrfToken;

/**
 * CSRF DOUBLE-SUBMIT, SESSION-BOUND (F3).
 *
 * The x-csrf-token header must equal the admin_csrf cookie AND carry a valid
 * HMAC tag under the CURRENT session's token hash (see App\Services\CsrfToken).
 * Compared with hash_equals; 403 (NOT Laravel's default 419) — the status
 * code and generic message are part of the pinned contract.
 *
 * The session hash comes from the HttpOnly admin_session cookie resolved via
 * the same lookup the admin guard uses — an attacker who can toss/choose the
 * readable CSRF cookie still cannot mint a tag without the session secret.
 */
class CsrfDoubleSubmit
{
    public const COOKIE = 'admin_csrf';
    public const HEADER = 'x-csrf-token';

    public function __construct(private AdminAuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $cookieToken = $request->cookie(self::COOKIE);
        $headerToken = $request->header(self::HEADER);

        $sessionHash = null;
        $sessionToken = $request->cookie(AuthenticateAdmin::COOKIE);
        if (is_string($sessionToken) && $sessionToken !== '') {
            $sessionHash = hash('sha256', $sessionToken);
        }

        $valid = $sessionHash !== null
            && app(CsrfToken::class)->validate($cookieToken, $headerToken, $sessionHash);

        if (! $valid) {
            return ApiResponse::forbidden('Invalid CSRF token.');
        }

        return $next($request);
    }
}
