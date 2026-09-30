<?php

use App\Services\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // -------------------------------------------------------------------
        // API MIDDLEWARE STACK — Laravel's web/session
        // machinery is deliberately NOT used on the API: statelessness is the
        // contract (no session cookies, no Laravel CSRF tokens, no redirects).
        // -------------------------------------------------------------------
        $middleware->api(prepend: [
            \App\Http\Middleware\RejectOversizedBody::class,
        ]);

        // F9: security headers on EVERY response, web + api.
        $middleware->append([\App\Http\Middleware\SecurityHeaders::class]);

        $middleware->alias([
            'admin.guard' => \App\Http\Middleware\AuthenticateAdmin::class,
            'same.origin' => \App\Http\Middleware\RequireSameOrigin::class,
            'csrf.double' => \App\Http\Middleware\CsrfDoubleSubmit::class,
            'can'         => \App\Http\Middleware\RequirePermission::class,
        ]);

        // -----------------------------------------------------------------
        // MIDDLEWARE PRIORITY — the kernel's default priority list floats
        // ThrottleRequests to the FRONT of every route pipeline: it outranks
        // any middleware NOT listed here, so `throttle:admin-read` was
        // jumping ahead of `admin.guard` and keying by the socket IP instead
        // of the resolved admin (all admins shared one bucket — F6 test
        // caught it). Pin the DEFAULT list verbatim with AuthenticateAdmin
        // inserted before the throttles: the guard resolves `admin` onto the
        // request attributes, and the admin-read limiter keys by it.
        // -----------------------------------------------------------------
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\AuthenticateAdmin::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        // Web group (SPA host + static assets) — map §2B-5 says Laravel's
        // session machinery stays OFF everywhere: the SPA host serves static
        // files and prerendered HTML only; the API is stateless with its own
        // admin guard + double-submit CSRF. Strip every session-dependent
        // middleware from the web group (SESSION_DRIVER=database would query
        // a sessions table that intentionally does not exist).
        $middleware->web(remove: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // -----------------------------------------------------------------
        // CATCH-ALL EXCEPTION RENDERER — every unhandled failure on api/*
        // routes answers the standard JSON envelope, never a framework HTML
        // page. The browser client's fetch() cannot parse Laravel's HTML
        // error pages, and they leak framework internals (pentest findings:
        // 405 HTML pages, refresh-endpoint 500 HTML). Unhandled throwables
        // map to a generic 500 INTERNAL_ERROR — details go to storage/logs,
        // never the client, in every environment.
        // -----------------------------------------------------------------
        $exceptions->render(function (Throwable $e, $request) {
            return ApiResponse::forException($e, $request);
        });
    })
    ->create();
