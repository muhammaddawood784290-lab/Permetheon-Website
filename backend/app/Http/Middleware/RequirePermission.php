<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ApiResponse;
use App\Services\Permissions;

/**
 * PERMISSION MIDDLEWARE — port of requirePermission(). Reads the
 * authenticated admin set by AuthenticateAdmin and checks the role→permission
 * map. 403 with the standard envelope on denial.
 */
class RequirePermission
{
    public function __construct(private ?string $permission = null)
    {
    }

    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $required = $permission ?? $this->permission;

        $admin = $request->attributes->get('admin');

        if (! $admin || ! $required || ! Permissions::has($admin->role, $required)) {
            return ApiResponse::forbidden();
        }

        return $next($request);
    }
}
