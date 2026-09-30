<?php

/**
 * F8 — DEAD ADMIN SESSIONS ARE PRUNED (docs/ADMIN_AUTH_REVIEW.md, F8).
 *
 * admin_sessions rows are only ever REVOKED (replay-safety, G2) — the table
 * grew unbounded. `admin:prune-sessions` deletes rows that were already dead
 * N days ago (measured from the revocation/absolute-expiry moment):
 *
 *   - keeps every LIVE row regardless of age;
 *   - keeps rows that died WITHIN the window;
 *   - prunes revoked rows past the window;
 *   - prunes unrevoked rows whose absolute lifetime passed the window;
 *   - --days overrides the retention (default SESSIONS_PRUNE_DAYS / 30);
 *   - --dry-run reports without deleting;
 *   - the daily schedule entry is registered.
 */

const F8_ADMIN_ID = 'f8000000-0000-0000-0000-000000000001';

function f8SeedAdmin(): void
{
    if (App\Models\AdminUser::where('id', F8_ADMIN_ID)->exists()) {
        return;
    }
    App\Models\AdminUser::create([
        'id'                 => F8_ADMIN_ID,
        'email'              => 'f8@permetheon.test',
        'name'               => 'F8 Fixture',
        'password_hash'      => Illuminate\Support\Facades\Hash::make('not-a-login'),
        'role'               => 'SUPER_ADMIN',
        'is_active'          => 1,
        'failed_login_count' => 0,
        'created_at'         => App\Support\Clock::now(),
        'updated_at'         => App\Support\Clock::now(),
    ]);
}

/** Seed one session row with explicit death moments (days ago). */
function f8Session(string $id, ?int $revokedDaysAgo, ?int $expiredDaysAgo, int $seenDaysAgo = 0): void
{
    App\Models\AdminSession::create([
        'id'                  => hash('sha256', $id),
        'admin_id'            => F8_ADMIN_ID,
        'created_at'          => App\Support\Clock::format(time() - 40 * 24 * 3600),
        'last_seen_at'        => App\Support\Clock::format(time() - $seenDaysAgo * 24 * 3600),
        'absolute_expires_at' => App\Support\Clock::format(
            time() + ($expiredDaysAgo === null ? 24 * 3600 : -$expiredDaysAgo * 24 * 3600)
        ),
        'revoked_at'          => $revokedDaysAgo === null ? null : App\Support\Clock::format(time() - $revokedDaysAgo * 24 * 3600),
    ]);
}

function f8Exists(string $id): bool
{
    return App\Models\AdminSession::find(hash('sha256', $id)) !== null;
}

it('F8: prunes long-revoked rows and long-expired rows, keeps live and recent ones', function () {
    f8SeedAdmin();
    f8Session('f8-long-revoked', revokedDaysAgo: 40, expiredDaysAgo: null, seenDaysAgo: 40);
    f8Session('f8-long-expired', revokedDaysAgo: null, expiredDaysAgo: 40, seenDaysAgo: 40);
    f8Session('f8-recent-revoked', revokedDaysAgo: 5, expiredDaysAgo: null, seenDaysAgo: 5);
    f8Session('f8-live', revokedDaysAgo: null, expiredDaysAgo: null, seenDaysAgo: 0);

    $this->artisan('admin:prune-sessions')->assertSuccessful();

    expect(f8Exists('f8-long-revoked'))->toBeFalse()
        ->and(f8Exists('f8-long-expired'))->toBeFalse()
        ->and(f8Exists('f8-recent-revoked'))->toBeTrue()
        ->and(f8Exists('f8-live'))->toBeTrue();
});

it('F8: --days overrides the retention window', function () {
    f8SeedAdmin();
    f8Session('f8-dead-10d', revokedDaysAgo: 10, expiredDaysAgo: null, seenDaysAgo: 10);
    f8Session('f8-dead-3d', revokedDaysAgo: 3, expiredDaysAgo: null, seenDaysAgo: 3);

    // Default window (30d) keeps both; a 7-day window drops only the older one.
    $this->artisan('admin:prune-sessions')->assertSuccessful();
    expect(f8Exists('f8-dead-10d'))->toBeTrue();

    $this->artisan('admin:prune-sessions', ['--days' => '7'])->assertSuccessful();
    expect(f8Exists('f8-dead-10d'))->toBeFalse()
        ->and(f8Exists('f8-dead-3d'))->toBeTrue();
});

it('F8: --dry-run reports the count and deletes NOTHING', function () {
    f8SeedAdmin();
    f8Session('f8-dry-1', revokedDaysAgo: 90, expiredDaysAgo: null, seenDaysAgo: 90);
    f8Session('f8-dry-2', revokedDaysAgo: null, expiredDaysAgo: 90, seenDaysAgo: 90);

    $this->artisan('admin:prune-sessions', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN: 2')
        ->assertSuccessful();

    expect(f8Exists('f8-dry-1'))->toBeTrue()
        ->and(f8Exists('f8-dry-2'))->toBeTrue();
});

it('F8: a never-dead row is never pruned no matter how old its activity is', function () {
    f8SeedAdmin();
    // Created 40 days ago, last activity 20 days ago, but still inside its
    // 24h absolute lifetime and unrevoked — LIVE by definition.
    App\Models\AdminSession::create([
        'id'                  => hash('sha256', 'f8-ancient-live'),
        'admin_id'            => F8_ADMIN_ID,
        'created_at'          => App\Support\Clock::format(time() - 40 * 24 * 3600),
        'last_seen_at'        => App\Support\Clock::format(time() - 20 * 24 * 3600),
        'absolute_expires_at' => App\Support\Clock::format(time() + 3600),
        'revoked_at'          => null,
    ]);

    $this->artisan('admin:prune-sessions', ['--days' => '1'])->assertSuccessful();

    expect(f8Exists('f8-ancient-live'))->toBeTrue();
});

it('F8: a non-numeric --days option fails cleanly', function () {
    $this->artisan('admin:prune-sessions', ['--days' => 'soon'])
        ->expectsOutputToContain('positive integer')
        ->assertFailed();
});

it('F8: the daily schedule entry is registered', function () {
    $events = Illuminate\Support\Facades\Schedule::events();
    $prune = collect($events)->first(fn ($e) => str_contains((string) $e->command, 'admin:prune-sessions'));

    expect($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('20 3 * * *');
});
