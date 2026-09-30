<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicApi\InquiryController;
use App\Http\Controllers\PublicApi\MeetingController as PublicMeetingController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\MeetingController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;

/*
|--------------------------------------------------------------------------
| API ROUTES — the public + admin JSON API (contract-identical with the
|--------------------------------------------------------------------------
| Vite SPA and Laravel API share one origin; same paths, verbs, status codes
| and JSON envelope everywhere. CSRF double-submit + custom guard middleware
| — Laravel's default web/session machinery is deliberately NOT used.
|
| Meetings extension (spec §10): the inquiry routes are untouched — the
| meeting feature only ADDS routes, never modifies the existing contract.
*/

// ---------------------------------------------------------------------------
// PUBLIC — inquiry API (existing contract, untouched)
// ---------------------------------------------------------------------------
Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware(['throttle:inquiry']);

// ---------------------------------------------------------------------------
// PUBLIC — meeting availability (read-only; booking happens through
// POST /api/inquiries so the inquiry + meeting commit atomically)
// READ-APPROPRIATE LIMIT: 60/min per client, NOT the inquiry form's 5/10min
// write bucket — sharing it starved the calendar (and the form) after five
// fetches. Keying follows the F1 clientKey rule (XFF only from trusted
// proxies).
// ---------------------------------------------------------------------------
Route::get('/meetings/availability', [PublicMeetingController::class, 'availability'])
    ->middleware(['throttle:availability']);

// ---------------------------------------------------------------------------
// ADMIN — session machine (existing)
// ---------------------------------------------------------------------------
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['throttle:login']);
    // NOTE: `same.origin` stays UNWIRED by design: the
    // SPA is served same-origin by Laravel in production and by the Vite dev
    // proxy in development, so the strict Origin-vs-Host check is unnecessary
    // here and would 403 every dev/test client that omits an Origin header.
    // CSRF is enforced by the double-submit middleware on mutating routes.
    Route::middleware(['admin.guard'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        // HEAD /session → 204 no body (G8): health probes use HEAD. Registered
        // BEFORE the GET route so the response is 204 both with and without
        // `php artisan route:cache` (the cached router matches in registration
        // order; the auto GET|HEAD route would otherwise win and answer 200).
        Route::match(['HEAD'], '/session', [AuthController::class, 'sessionHead']);
        Route::get('/session', [AuthController::class, 'session']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// ---------------------------------------------------------------------------
// ADMIN — everything below requires a live session
// ---------------------------------------------------------------------------
Route::prefix('admin')->middleware(['admin.guard'])->group(function () {

    // --- inquiries (existing, untouched) ---
    // F6: read routes carry the admin-read limiter (120/min per admin) —
    // it runs AFTER admin.guard, so the bucket keys by the resolved admin id.
    Route::get('/inquiries', [AdminInquiryController::class, 'index'])
        ->middleware(['throttle:admin-read']);
    // --- stats (nested under /inquiries; registered before /inquiries/{id}) ---
    // Registered BEFORE /inquiries/{id}: otherwise 'stats' matches the {id}
    // wildcard and the detail controller 404s it (route-matching order).
    Route::get('/inquiries/stats', [StatsController::class, 'index'])
        ->middleware(['can:inquiries.stats.read', 'throttle:admin-read']);

    Route::get('/inquiries/{id}', [AdminInquiryController::class, 'show'])
        ->middleware(['throttle:admin-read']);
    Route::patch('/inquiries/{id}', [AdminInquiryController::class, 'update'])
        ->middleware(['can:inquiries.update', 'csrf.double']);

    // Spec §7 — admin inquiry deletion. Backend-enforced policy: an inquiry
    // may be deleted with or without a meeting; the meeting is removed in the
    // same transaction (never orphaned) or deletion is refused while BOOKED
    // under the 'prevent' policy. See AppServicesMeetingPolicy.
    Route::delete('/inquiries/{id}', [AdminInquiryController::class, 'destroy'])
        ->middleware(['can:inquiries.delete', 'csrf.double']);

    // --- notes (existing: list + create) ---
    Route::get('/inquiries/{id}/notes', [NoteController::class, 'index'])
        ->middleware(['can:inquiries.notes.read', 'throttle:admin-read']);
    Route::post('/inquiries/{id}/notes', [NoteController::class, 'store'])
        ->middleware(['can:inquiries.notes.create', 'csrf.double']);

    // =========================================================================
    // MEETINGS (spec §3/§6/§7/§10) — state-changing routes carry the CSRF
    // double-submit middleware; read-only routes do not (same split as the
    // inquiry routes).
    // =========================================================================

    Route::get('/meetings', [MeetingController::class, 'index'])
        ->middleware(['throttle:admin-read']);
    Route::get('/meetings/{id}', [MeetingController::class, 'show'])
        ->middleware(['throttle:admin-read']);

    Route::patch('/meetings/{id}', [MeetingController::class, 'updateStatus'])
        ->middleware(['can:meetings.update', 'csrf.double']);
    Route::delete('/meetings/{id}', [MeetingController::class, 'destroy'])
        ->middleware(['can:meetings.delete', 'csrf.double']);

    // --- availability configuration (read + write split like inquiries) ---
    Route::get('/availability', [MeetingController::class, 'availabilitySettings'])
        ->middleware(['throttle:admin-read']);
    Route::put('/availability', [MeetingController::class, 'updateAvailabilitySettings'])
        ->middleware(['can:availability.write', 'csrf.double']);

    // --- user management (spec §10 authority model) — reads for admins.read,
    // writes gated by the matching admins.* permission + CSRF. MANAGER is
    // denied the writes at the gate (Permissions::MANAGER_EXCLUDED). The
    // controller additionally refuses self-delete/self-demote and protects
    // the last active SUPER_ADMIN. ---
    Route::get('/users', [AdminUsersController::class, 'index'])
        ->middleware(['can:admins.read', 'throttle:admin-read']);
    Route::post('/users', [AdminUsersController::class, 'store'])
        ->middleware(['can:admins.create', 'csrf.double']);
    Route::patch('/users/{id}/role', [AdminUsersController::class, 'updateRole'])
        ->middleware(['can:admins.role.update', 'csrf.double']);
    Route::delete('/users/{id}', [AdminUsersController::class, 'destroy'])
        ->middleware(['can:admins.delete', 'csrf.double']);

    // --- blocks: whole-day and single-slot (read + write split) ---
    Route::get('/blocks', [MeetingController::class, 'blocks'])
        ->middleware(['throttle:admin-read']);
    Route::post('/blocks', [MeetingController::class, 'createBlock'])
        ->middleware(['can:availability.write', 'csrf.double']);
    Route::delete('/blocks/{id}', [MeetingController::class, 'deleteBlock'])
        ->middleware(['can:availability.write', 'csrf.double']);

    // Verb mismatch on a known admin path → 400 BAD_REQUEST (never 405 HTML):
    // the guard runs first, so unauthenticated callers get 401 (row 23) and
    // authenticated ones get the BAD_REQUEST envelope (G9).
    Route::any('/{any}', fn () => \App\Services\ApiResponse::badRequest('Unsupported method or path.'))
        ->where('any', '.*');
});

// NOTE: unknown api/* paths (any verb) are answered by the WEB SPA-host
// catch-all in routes/web.php, which returns the JSON 404 envelope for
// api/* paths — see the CATCH-ALL GUARD there. No competing catch-all is
// registered here: a second `Route::any('/{any}')` would shadow every
// route registered after this file loads and mask genuine 405s.
