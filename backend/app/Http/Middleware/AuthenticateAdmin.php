<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AdminAuthService;
use App\Services\ApiResponse;

/**
 * ADMIN AUTH MIDDLEWARE — custom guard. Laravel's
 * session machinery is NOT engaged: the opaque admin_session cookie is resolved
 * directly against the admin_sessions table. Returns 401 with the standard
 * envelope when absent/expired/revoked.
 */
class AuthenticateAdmin
{
    public const COOKIE = 'admin_session';

    public function __construct(private AdminAuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return ApiResponse::unauthorized();
        }

        $resolved = $this->auth->resolveSession($token);
        if ($resolved === null) {
            return ApiResponse::unauthorized();
        }

        $request->attributes->set('admin', $resolved['admin']);

        return $next($request);
    }
}
