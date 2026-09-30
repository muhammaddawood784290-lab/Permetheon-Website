<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WEB ROUTES — SPA host
|--------------------------------------------------------------------------
| Production serves the BUILT Vite SPA from frontend/dist as static files.
| `php artisan serve` (PHP's built-in server) only routes PHP through Laravel,
| so static assets from dist are served by a catch-all READ-ONLY file handler
| below (path-validated, never directory-listed). In a real deployment
| (nginx/Apache) the web server would serve these directly and this handler
| would simply never fire — the route map stays correct for both.
|
| Laravel's job here is what a static file server can't do:
|   1. pretty-URL fallback — any non-API GET without a file hit serves the
|      matching prerendered *.html (e.g. /contact → dist/contact.html), else
|      the SPA shell (index.html) so client-only routes (/admin, /system)
|      work and the router renders its own 404.
|   2. never touch /api/* (the API route map stays authoritative, §2A).
| Unknown paths get a real 404 status with the shell body — matching the Next
| not-found behavior ("a real 404, never the homepage").
*/

$dist = realpath(dirname(__DIR__, 2) . '/frontend/dist');

Route::get('/{any?}', function (?string $any = null) use ($dist) {
    if ($dist === false) {
        abort(500, 'frontend/dist is not built. Run: cd frontend && npm run build');
    }

    $path = rtrim((string) $any, '/');
    $path = $path === '' ? '' : $path;

    // 1. Static asset hit (assets/, screenshots/, og/, favicon, robots…).
    if ($path !== '' && ! str_contains($path, '..') && ! str_contains($path, '\\')) {
        $file = $dist . '/' . $path;
        if (is_file($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $types = [
                'html' => 'text/html; charset=UTF-8',
                'css' => 'text/css; charset=UTF-8',
                'js' => 'text/javascript; charset=UTF-8',
                'mjs' => 'text/javascript; charset=UTF-8',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'txt' => 'text/plain; charset=UTF-8',
                'xml' => 'application/xml',
            ];
            $mime = $types[$ext] ?? 'application/octet-stream';

            // file_get_contents, NOT readfile: readfile() echoes the bytes AND
            // returns an int, so response(readfile(...)) double-emits the body
            // (echoed copy + the int) and PHP's default text/html header wins.
            return response(file_get_contents($file), 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string) filesize($file),
                'Cache-Control' => str_starts_with($path, 'assets/') ? 'public, max-age=31536000, immutable' : 'public, max-age=0, must-revalidate',
            ]);
        }
    }

    // 2. Prerendered page hit: /contact → dist/contact.html,
    // /case-studies/pct → dist/case-studies/pct.html (status 200 — these are
    // real pages, not fallbacks).
    if ($path !== '' && ! str_contains($path, '..')) {
        $prerendered = $dist . '/' . $path . '.html';
        if (is_file($prerendered)) {
            return response(file_get_contents($prerendered), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'public, max-age=0, must-revalidate',
            ]);
        }
    }

    // 3. SPA shell. Client-only routes (/admin*, /system) get the EMPTY shell
    // (admin-shell.html — no prerendered root content, no hydration data) with
    // a 200: they are real, bookmarkable application pages. Unknown paths get
    // the index.html shell with a real 404 status so crawlers and curl see the
    // correct code.
    // CATCH-ALL GUARD — unknown api/* paths (any verb) must NEVER fall through
    // to the SPA shell: the browser client's fetch() cannot parse HTML, and a
    // prerendered shell body with a 404 status would be a contract break. The
    // API route map stays authoritative; this is only the last-resort JSON 404
    // (the same envelope the exception renderer produces).
    if (str_starts_with($path, 'api/') || $path === 'api') {
        return \App\Services\ApiResponse::notFound('Not found.');
    }

    $clientOnly = ['admin', 'system'];
    $isClientOnly = collect($clientOnly)->contains(fn ($p) => $path === $p || str_starts_with($path, $p . '/'));
    $shellFile = $isClientOnly ? $dist . '/admin-shell.html' : $dist . '/index.html';
    $shell = file_get_contents($shellFile);
    $status = ($path === '' || $isClientOnly) ? 200 : 404;

    return response($shell, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
})->where('any', '.*');
