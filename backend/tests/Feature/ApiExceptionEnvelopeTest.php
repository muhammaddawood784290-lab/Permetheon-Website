<?php

/*
|--------------------------------------------------------------------------
| EXCEPTION ENVELOPE REGRESSION (security carryover findings 2 & 3)
|--------------------------------------------------------------------------
| Every unhandled failure on an api/* route must answer the standard JSON
| envelope — never a Laravel HTML error page (the browser client's fetch()
| cannot parse those, and the pages leak framework internals). The original
| assessment caught two live instances: PATCH /api/inquiries answering a 405
| HTML page and the missing refresh handler answering a 500 HTML page.
|
| Unknown-path routes are registered here (not in routes/api.php) so the
| application ships no debug surface; this file exercises the full HTTP
| kernel: middleware, routing and the catch-all exception renderer.
*/

use App\Models\BusinessInquiry;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // A route that dies with an unhandled exception — the "unknown bug" case.
    Route::post('api/__test/boom', function () {
        throw new RuntimeException('sentinel-do-not-leak-4f1c');
    });

    // A route that dies with an unhandled model-not-found (404 branch).
    // POST, not GET: the web SPA-host catch-all (registered at boot) serves
    // every unmatched GET, so test-registered GET routes are unreachable —
    // the unknown-api-path behavior is covered separately below.
    Route::post('api/__test/missing', function () {
        return BusinessInquiry::query()->findOrFail('00000000-0000-0000-0000-000000000000');
    });

    // A route behind the GENERIC throttle (no custom response → the limiter
    // throws ThrottleRequestsException, which the catch-all must envelope).
    Route::post('api/__test/throttled', fn () => response()->json(['success' => true]))
        ->middleware('throttle:2,1');
});

it('unknown api paths return the 404 envelope, never a router HTML page', function () {
    $response = $this->getJson('/api/definitely/not/a/route');

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'NOT_FOUND',
                'message' => 'Not found.',
            ],
        ]);
});

it('verb mismatches on api paths return the true 405 envelope with Allow preserved', function () {
    // PATCH /api/inquiries (only POST exists): Laravel raises
    // MethodNotAllowedHttpException, the catch-all renderer answers the JSON
    // envelope and preserves the Allow header. The original finding was the
    // bare 405 HTML disclosure page.
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->patch('/api/inquiries', []);

    $response->assertStatus(405)
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Allow')
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'METHOD_NOT_ALLOWED',
                'message' => 'Method not allowed for this endpoint.',
            ],
        ]);

    // Same contract for DELETE on the availability route.
    $this->deleteJson('/api/meetings/availability')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
});

it('unhandled exceptions return a generic 500 envelope without internals — even in debug mode', function () {
    // Guard the premise: this suite runs with debug enabled, which is the
    // config where Laravel WOULD otherwise leak exception/file/trace details.
    expect(config('app.debug'))->toBeTrue();

    $response = $this->postJson('/api/__test/boom');

    $response->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Something went wrong. Please try again.',
            ],
        ]);

    // No framework internals anywhere in the body: not the exception class,
    // not the message, not file paths, not the debug trace block.
    $body = $response->getContent();
    expect($body)->not->toContain('sentinel-do-not-leak-4f1c')
        ->and($body)->not->toContain('RuntimeException')
        ->and($body)->not->toContain('"trace"')
        ->and($body)->not->toContain('"/vendor/')
        ->and($response->json('exception'))->toBeNull();
});

it('unhandled model-not-found returns the 404 envelope', function () {
    $response = $this->postJson('/api/__test/missing');

    $response->assertStatus(404)
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'NOT_FOUND',
                'message' => 'Resource not found.',
            ],
        ]);
});

it('generic throttle rejections return the 429 envelope', function () {
    // 2 allowed by throttle:2,1 — the third must be enveloped by the
    // catch-all (the generic limiter has no custom response of its own).
    $this->postJson('/api/__test/throttled')->assertStatus(200);
    $this->postJson('/api/__test/throttled')->assertStatus(200);

    $response = $this->postJson('/api/__test/throttled');

    $response->assertStatus(429)
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'RATE_LIMITED',
                'message' => 'Too many requests. Please try again shortly.',
            ],
        ]);
});

it('web routes keep framework behavior — no envelope on non-api paths', function () {
    // Unknown WEB path: Laravel's own 404 (HTML), not the JSON envelope.
    $response = $this->get('/definitely/not/a/page');
    $response->assertStatus(404);
    expect($response->headers->get('Content-Type'))->toContain('text/html');

    // The homepage still serves normally.
    $this->get('/')->assertStatus(200);
});

it('protected admin endpoints stay envelope-shaped: 401 without a session', function () {
    $response = $this->getJson('/api/admin/inquiries');

    $response->assertStatus(401)
        ->assertExactJson([
            'success' => false,
            'error'   => [
                'code'    => 'UNAUTHORIZED',
                'message' => 'Authentication required.',
            ],
        ]);
});
