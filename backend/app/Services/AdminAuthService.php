<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\AdminSession;
use App\Support\Clock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ADMIN AUTH SERVICE — login business logic. Lockout, bootstrap-on-first-login, generic error
 * policy. Mirrors src/lib/admin/adminAuthService.ts.
 */
class AdminAuthService
{
    public const MAX_FAILURES = 5;
    public const LOCK_MINUTES = 15;
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * @return array{ok: bool, admin?: AdminUser}
     */
    public function authenticate(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        // First-run bootstrap: mint the initial SUPER_ADMIN, then normal login.
        if (AdminUser::count() === 0) {
            $bootstrapped = $this->bootstrapSuperAdmin($email, $password);
            if (! $bootstrapped) {
                return ['ok' => false];
            }
        }

        $admin = AdminUser::where('email', $email)->first();

        if ($admin && $admin->locked_until !== null && strtotime($admin->locked_until) > time()) {
            return ['ok' => false]; // locked — generic error, never an enumeration signal
        }

        if (! $admin || ! $admin->isActive() || ! Hash::check($password, $admin->password_hash)) {
            if ($admin) {
                $this->recordFailedLogin($admin);
            }

            return ['ok' => false];
        }

        $this->recordSuccessfulLogin($admin);

        return ['ok' => true, 'admin' => $admin];
    }

    /**
     * Only exact bootstrap credentials create the first SUPER_ADMIN; the
     * password is wiped so it can never mint a second account (G5).
     */
    private function bootstrapSuperAdmin(string $email, string $password): bool
    {
        if ($this->bootstrapDisabled()) {
            return false;
        }

        // config() first so the value survives `php artisan config:cache`
        // (env() reads are null once the config is cached); env() fallback
        // keeps the G5 test's runtime putenv() seeding working.
        $bootstrapEmail = strtolower(config('admin.bootstrap_email', 'admin@permetheon.com') ?: env('ADMIN_BOOTSTRAP_EMAIL', 'admin@permetheon.com'));
        $bootstrapPassword = $this->bootstrapPassword();

        if (! $bootstrapPassword) {
            return false;
        }
        if (! \App\Support\PasswordPolicy::isValid($password)) {
            return false;
        }
        if ($email !== $bootstrapEmail || $password !== $bootstrapPassword) {
            return false;
        }

        $now = Clock::now();

        AdminUser::create([
            'id'            => (string) Str::uuid(),
            'email'         => $bootstrapEmail,
            'name'          => 'Administrator',
            'password_hash' => Hash::make($password),
            'role'          => 'SUPER_ADMIN',
            'is_active'     => 1,
            'failed_login_count' => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Wipe so the credentials can never mint a second account later.
        $this->wipeBootstrapPassword();

        return true;
    }

    /**
     * The bootstrap password, read once per process. After the first successful
     * bootstrap the credential is invalidated so a second bootstrap is
     * impossible (G5):
     *  - testing: in-process wipe (putenv + GLOBALS flag) — the G5 test's
     *    expected semantics, no filesystem writes;
     *  - production/other: a persistent marker file in storage/app, because
     *    per-process wipes do not survive the per-request process model and
     *    `config:cache` keeps the value readable in cached config.
     */
    private function bootstrapDisabled(): bool
    {
        if (app()->environment('testing')) {
            return array_key_exists('permetheon.bootstrap_wiped', $GLOBALS);
        }

        return file_exists($this->bootstrapMarkerPath());
    }

    private function bootstrapMarkerPath(): string
    {
        return storage_path('app/admin-bootstrap.completed');
    }

    private function bootstrapPassword(): ?string
    {
        if ($this->bootstrapDisabled()) {
            return null;
        }

        return env('ADMIN_BOOTSTRAP_PASSWORD')
            ?: config('admin.bootstrap_password')
            ?: null;
    }

    private function wipeBootstrapPassword(): void
    {
        $GLOBALS['permetheon.bootstrap_wiped'] = true;

        if (function_exists('putenv')) {
            putenv('ADMIN_BOOTSTRAP_PASSWORD');
        }
        unset($_ENV['ADMIN_BOOTSTRAP_PASSWORD'], $_SERVER['ADMIN_BOOTSTRAP_PASSWORD']);

        if (! app()->environment('testing')) {
            @file_put_contents($this->bootstrapMarkerPath(), \App\Support\Clock::now());
        }
    }

    private function recordFailedLogin(AdminUser $admin): void
    {
        $admin->failed_login_count = $admin->failed_login_count + 1;
        if ($admin->failed_login_count >= self::MAX_FAILURES && $admin->locked_until === null) {
            $admin->locked_until = Clock::offset(self::LOCK_MINUTES * 60);

            // F4: make lockout events VISIBLE — repeated lockouts of a named
            // account are a DoS signal, and the trace pairs with the
            // `admin:unlock` recovery command's own log line.
            \Illuminate\Support\Facades\Log::warning('admin.lockout.engaged', [
                'admin_id'          => $admin->id,
                'email'             => $admin->email,
                'lock_minutes'       => self::LOCK_MINUTES,
                'failed_login_count' => $admin->failed_login_count,
            ]);
        }
        $admin->updated_at = Clock::now();
        $admin->save();
    }

    private function recordSuccessfulLogin(AdminUser $admin): void
    {
        $admin->failed_login_count = 0;
        $admin->locked_until = null;
        $admin->updated_at = Clock::now();
        $admin->save();
    }

    /**
     * Create a session row: opaque token, DB stores only the SHA-256 hash
     *. 30-min sliding idle window + 24-h absolute lifetime.
     *
     * @return string the raw token (goes into the cookie; never persisted)
     */
    public function createSession(string $adminId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        AdminSession::create([
            'id'                  => hash('sha256', $token),
            'admin_id'            => $adminId,
            'created_at'          => Clock::now(),
            'last_seen_at'        => Clock::now(),
            'absolute_expires_at' => Clock::offset(24 * 60 * 60),
        ]);

        return $token;
    }

    /**
     * Resolve the current session or null. Slides the idle window on success;
     * idle-expired tokens are revoked so they cannot be replayed (G2).
     *
     * @return array{admin: AdminUser, session: AdminSession}|null
     */
    public function resolveSession(string $token): ?array
    {
        $session = AdminSession::find(hash('sha256', $token));

        if (! $session || $session->isRevoked() || $session->isAbsolutelyExpired()) {
            return null;
        }
        if ($session->isIdleExpired()) {
            $session->revoked_at = Clock::now();
            $session->save();

            return null;
        }

        $admin = AdminUser::find($session->admin_id);
        if (! $admin || ! $admin->isActive()) {
            return null;
        }

        // Touch the sliding window.
        $session->last_seen_at = Clock::now();
        $session->save();

        return ['admin' => $admin, 'session' => $session];
    }

    /** Revoke by token (logout) — server-side row invalidation. */
    public function revokeSession(string $token): void
    {
        AdminSession::where('id', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Clock::now()]);
    }
}
