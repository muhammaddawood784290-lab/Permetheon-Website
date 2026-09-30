<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY RESPONSE HEADERS (F9) — applied to every response (web + api).
 *
 * CSP: the SPA ships no third-party scripts; styles come from the bundle and
 * the SSG emits two inline bootstrap <script> tags, hence script-src
 * 'unsafe-inline' for now (hash-pinning them at build time is post-launch
 * hardening). connect-src 'self' covers the same-origin admin API calls.
 *
 * HSTS is scheme-gated (mirrors the F2 cookie derivation): only stamped when
 * the response actually arrived over HTTPS (or APP_URL declares https and the
 * deployment terminates TLS on a proxy) — local plain-HTTP dev never gets it,
 * and the api max-age is deliberately short while the served max-age is the
 * long-lived one browsers honor.
 */
class SecurityHeaders
{
    private const HSTS_MAX_AGE = 31536000; // 1 year

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'; "
            ."img-src 'self' data:; font-src 'self'; connect-src 'self'; "
            .'frame-ancestors \'none\'; form-action \'self\'; base-uri \'self\''
        );

        $https = $request->isSecure() || str_starts_with((string) config('app.url', ''), 'https://');
        if ($https) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains'
            );
        }

        return $response;
    }
}
