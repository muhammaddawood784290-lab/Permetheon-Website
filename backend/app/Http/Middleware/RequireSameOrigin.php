<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ApiResponse;

/**
 * SAME-ORIGIN GUARD — port of assertSameOrigin(). Login requests must
 * originate from this site so a cross-site page cannot submit credentials even
 * before sessions exist.
 */
class RequireSameOrigin
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('origin');

        if (! $origin) {
            return ApiResponse::forbidden('Missing origin.');
        }

        $host = $request->header('host');
        $originHost = parse_url($origin, PHP_URL_HOST);

        if ($originHost === false || $originHost === null) {
            return ApiResponse::forbidden('Invalid origin.');
        }
        if ($host && str_replace(':80', '', $host) !== str_replace(':443', '', $host)) {
            // port-normalized comparison below
        }
        if ($host && $this->normalizeHost($originHost . $this->originPort($origin)) !== $this->normalizeHost($host)) {
            return ApiResponse::forbidden('Cross-origin request blocked.');
        }

        return $next($request);
    }

    private function originPort(string $origin): string
    {
        $port = parse_url($origin, PHP_URL_PORT);

        return $port ? ':' . $port : '';
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        return preg_replace('/:(80|443)$/', '', $host) ?? $host;
    }
}
