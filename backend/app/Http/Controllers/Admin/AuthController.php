<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Services\AdminAuthService;
use App\Services\ApiResponse;
use App\Services\CsrfToken;
use App\Services\Permissions;

/**
 * ADMIN AUTH — session machine. Lockout ·
 * IP rate-limited (throttle:login) · lockout · generic errors · bootstrap.
 * Sets the HttpOnly admin_session cookie + readable admin_csrf double-submit
 * cookie; the CSRF token is also returned in the body (as today).
 */
class AuthController extends Controller
{
    public function __construct(private AdminAuthService $auth)
    {
    }

    /**
     * F2: the `Secure` flag on the admin cookies follows the ACTUAL serving
     * scheme, not the environment name. A misconfigured `APP_ENV=local` over
     * HTTPS must not silently drop the flag (and leak the session on plain
     * HTTP); equally, plain-HTTP local dev must not ship a `Secure` cookie
     * the browser refuses to send. The request scheme is authoritative when
     * the app is served over HTTPS; otherwise APP_URL's scheme decides (a
     * deployment that termininates TLS on a proxy still wants Secure — the
     * app itself is reached over HTTP from the LB). Path/terminate-time
     * schemes (HTTP → HTTPS handoff) are outside this derivation.
     */
    private function secureCookies(Request $request): bool
    {
        return $request->isSecure() || str_starts_with(config('app.url', ''), 'https://');
    }

    public function login(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (! is_array($body)) {
            return ApiResponse::badRequest('Request body must be valid JSON.');
        }

        $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';

        $fields = [];
        if ($email === '') {
            $fields['email'] = 'Email is required.';
        }
        if ($password === '') {
            $fields['password'] = 'Password is required.';
        }
        if ($fields !== []) {
            return ApiResponse::validation($fields);
        }

        $outcome = $this->auth->authenticate($email, $password);
        if (! $outcome['ok']) {
            return ApiResponse::unauthorized('Invalid email or password.');
        }

        $admin = $outcome['admin'];

        // Session cookie: HttpOnly, SameSite=Strict, Secure per scheme (F2).
        $token = $this->auth->createSession($admin->id);
        $secure = $this->secureCookies($request);
        $sessionCookie = cookie('admin_session', $token, 24 * 60, '/', null, $secure, true, false, 'Strict');

        // Double-submit CSRF cookie (F3): a nonce SIGNED with the session
        // token hash — a tossed/known cookie value cannot align cookie and
        // header without the HttpOnly session token.
        $csrf = new CsrfToken();
        $csrfToken = $csrf->issue(hash('sha256', $token));
        $csrfCookie = cookie('admin_csrf', $csrfToken, 24 * 60, '/', null, $secure, false, false, 'Strict');

        return ApiResponse::ok([
            'admin' => [
                'email' => $admin->email,
                'name'  => $admin->name,
                'role'  => $admin->role,
            ],
            'permissions' => Permissions::forRole($admin->role),
            'csrfToken'   => $csrfToken,
        ])->withCookie($sessionCookie)->withCookie($csrfCookie);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->cookie('admin_session');
        if (is_string($token) && $token !== '') {
            $this->auth->revokeSession($token);
        }

        // Clearing cookies MUST mirror the setting cookies' attributes (F2):
        // a browser only replaces a cookie whose name+path+secure match, so a
        // Secure=false clear under a Secure=true login leaves the live cookie
        // stranded — logout would appear to work while the session survives.
        $secure = $this->secureCookies($request);

        return response()
            ->json(['success' => true, 'data' => ['loggedOut' => true]])
            ->withCookie(cookie('admin_session', null, -1, '/', null, $secure, true, false, 'Strict'))
            ->withCookie(cookie('admin_csrf', null, -1, '/', null, $secure, false, false, 'Strict'));
    }

    public function session(Request $request): JsonResponse
    {
        // HEAD probes answer 204 with no body (G8) — enforced here as well as
        // on the dedicated HEAD route, because the router resolves HEAD to the
        // GET|HEAD registration when routes are NOT cached and to the HEAD-only
        // registration when they ARE cached (`php artisan route:cache`). This
        // makes the contract identical in both modes.
        if ($request->isMethod('HEAD')) {
            return new JsonResponse(null, 204);
        }

        $admin = $request->attributes->get('admin');

        $csrfToken = $request->cookie('admin_csrf');

        return ApiResponse::ok([
            'admin' => [
                'email' => $admin->email,
                'name'  => $admin->name,
                'role'  => $admin->role,
            ],
            'permissions' => Permissions::forRole($admin->role),
            'csrfToken'   => is_string($csrfToken) ? $csrfToken : null,
        ]);
    }

    public function sessionHead(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * POST /api/admin/auth/refresh (admin.guard).
     *
     * Session rotation: revokes the CURRENT session server-side and mints a
     * fresh session + CSRF cookie pair in the same response. The old token is
     * dead the instant this returns — a stolen token cannot be replayed after
     * a legitimate refresh, and the sliding idle window restarts cleanly.
     *
     * This route previously pointed at a missing method and 500'd with the
     * framework error page (docs/SECURITY_FINDINGS_CARRYOVER.md, finding 2).
     */
    public function refresh(Request $request): JsonResponse
    {
        // Rotate: kill the current session row, then mint a new one.
        $oldToken = $request->cookie('admin_session');
        if (is_string($oldToken) && $oldToken !== '') {
            $this->auth->revokeSession($oldToken);
        }

        $admin = $request->attributes->get('admin');
        $token = $this->auth->createSession($admin->id);
        $secure = $this->secureCookies($request);
        $sessionCookie = cookie('admin_session', $token, 24 * 60, '/', null, $secure, true, false, 'Strict');

        $csrf = new CsrfToken();
        $csrfToken = $csrf->issue(hash('sha256', $token));
        $csrfCookie = cookie('admin_csrf', $csrfToken, 24 * 60, '/', null, $secure, false, false, 'Strict');

        return ApiResponse::ok([
            'admin' => [
                'email' => $admin->email,
                'name'  => $admin->name,
                'role'  => $admin->role,
            ],
            'permissions' => Permissions::forRole($admin->role),
            'csrfToken'   => $csrfToken,
        ])->withCookie($sessionCookie)->withCookie($csrfCookie);
    }

    /** POST /session is a verb mismatch — the API contract answers 400 (parity G9). */
    public function sessionPost(): JsonResponse
    {
        return ApiResponse::badRequest('Use POST /api/admin/auth/logout to end the session.');
    }
}
