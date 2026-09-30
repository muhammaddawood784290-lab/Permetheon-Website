<?php

/**
 * F2 — ADMIN COOKIE `Secure` FLAG FOLLOWS THE SERVING SCHEME
 * (docs/ADMIN_AUTH_REVIEW.md, F2).
 *
 * Previously the flag was derived from `APP_ENV === 'production'` (a
 * misconfigured env over HTTPS silently shipped an insecure session cookie)
 * and the logout clearing cookies hardcoded `secure=false` — a browser only
 * replaces a cookie whose name+path+secure match, so under Secure login the
 * logout clear would NOT land and the session would survive "logout".
 *
 * New contract: `Secure` = request is HTTPS, OR APP_URL starts with
 * `https://` (TLS terminated on a proxy). APP_ENV is irrelevant. The same
 * derivation governs login, refresh, and the logout clearing pair.
 *
 * HTTPS requests are simulated with an absolute https:// request URI, which
 * MakesHttpRequests passes through to Symfony's Request::create — the server
 * bag then carries HTTPS=on and isSecure() is true.
 */

const F2_EMAIL = 'owner@permetheon.test';
const F2_PASSWORD = 'super-secret-passphrase';

function f2CreateAdmin(): void
{
    if (App\Models\AdminUser::where('email', F2_EMAIL)->exists()) {
        return;
    }
    App\Models\AdminUser::create([
        'id'                 => Illuminate\Support\Str::uuid()->toString(),
        'email'              => strtolower(F2_EMAIL),
        'name'               => 'Owner',
        'password_hash'      => Illuminate\Support\Facades\Hash::make(F2_PASSWORD),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => App\Support\Clock::now(),
        'updated_at'         => App\Support\Clock::now(),
    ]);
}

function f2CookieFlag($response, string $name): bool
{
    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $name);

    return $cookie !== null && $cookie->isSecure();
}

function f2CookieValue($response, string $name): ?string
{
    return collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === $name)?->getValue();
}

function f2Login($test, string $uri = '/api/admin/auth/login')
{
    f2CreateAdmin();
    return $test->postJson($uri, [
        'email'    => F2_EMAIL,
        'password' => F2_PASSWORD,
    ]);
}

it('F2: login over HTTPS sets Secure on BOTH cookies even with APP_ENV=local', function () {
    // Prove APP_ENV is irrelevant NOW (it was the old derivation). The env
    // MUST be restored afterwards: environment('testing') reads the process
    // environment, so a leftover 'local' would disable testing-mode detection
    // for every later test in this PHP process (broke the G5 bootstrap test).
    putenv('APP_ENV=local');
    $_ENV['APP_ENV'] = 'local';
    $_SERVER['APP_ENV'] = 'local';
    config(['app.url' => 'http://localhost']);

    try {
        $response = f2Login($this, 'https://localhost/api/admin/auth/login');

        $response->assertStatus(200);
        expect(f2CookieFlag($response, 'admin_session'))->toBeTrue()
            ->and(f2CookieFlag($response, 'admin_csrf'))->toBeTrue();
    } finally {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
    }
});

it('F2: plain-HTTP request falls back to the APP_URL scheme (TLS on the proxy)', function () {
    config(['app.url' => 'https://permetheon.example']);
    f2CreateAdmin();

    $response = $this->postJson('/api/admin/auth/login', [
        'email'    => F2_EMAIL,
        'password' => F2_PASSWORD,
    ]);

    $response->assertStatus(200);
    expect(f2CookieFlag($response, 'admin_session'))->toBeTrue()
        ->and(f2CookieFlag($response, 'admin_csrf'))->toBeTrue();
});

it('F2: plain HTTP + http APP_URL stays Secure=false (local dev must keep working)', function () {
    config(['app.url' => 'http://localhost']);
    f2CreateAdmin();

    $response = f2Login($this);

    $response->assertStatus(200);
    expect(f2CookieFlag($response, 'admin_session'))->toBeFalse()
        ->and(f2CookieFlag($response, 'admin_csrf'))->toBeFalse();
});

it('F2: refresh derives Secure the same way (request scheme wins)', function () {
    config(['app.url' => 'http://localhost']);
    f2CreateAdmin();

    // Login over plain HTTP -> insecure cookies, then refresh over HTTPS.
    $login = f2Login($this);
    $login->assertStatus(200);
    $session = f2CookieValue($login, 'admin_session');
    $csrf = f2CookieValue($login, 'admin_csrf');

    $refresh = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $session,
        'admin_csrf'    => $csrf,
    ])->postJson('https://localhost/api/admin/auth/refresh');

    $refresh->assertStatus(200);
    expect(f2CookieFlag($refresh, 'admin_session'))->toBeTrue()
        ->and(f2CookieFlag($refresh, 'admin_csrf'))->toBeTrue();
});

it('F2: logout clearing cookies mirror the login scheme (secure clears secure)', function () {
    config(['app.url' => 'http://localhost']);
    f2CreateAdmin();

    // HTTPS login -> Secure cookies...
    $login = f2Login($this, 'https://localhost/api/admin/auth/login');
    $login->assertStatus(200);
    $session = f2CookieValue($login, 'admin_session');
    $csrf = f2CookieValue($login, 'admin_csrf');
    expect($session)->not->toBeNull();

    // ...so the logout clear must ALSO be Secure (name+path+secure must match
    // for the browser to actually remove the live cookie).
    $logout = $this->withCredentials()->withUnencryptedCookies([
        'admin_session' => $session,
        'admin_csrf'    => $csrf,
    ])->postJson('https://localhost/api/admin/auth/logout');

    $logout->assertStatus(200);
    $clearedSession = collect($logout->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_session');
    $clearedCsrf = collect($logout->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'admin_csrf');

    expect($clearedSession)->not->toBeNull()
        ->and($clearedSession->getMaxAge())->toBe(0) // expired at send time
        ->and($clearedSession->isSecure())->toBeTrue()
        ->and($clearedCsrf->isSecure())->toBeTrue();
});
