<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Proxies allowed to supply X-Forwarded-For (CSV of IPs/CIDRs). EMPTY by
     * default: only requests from these addresses may set the rate-limit key,
     * so a client cannot rotate its own key by spoofing the header. Behind a
     * trusted reverse proxy (Hostinger load balancer, nginx, Cloudflare), add
     * that proxy's address here, e.g. TRUSTED_PROXIES=10.0.0.0/8,173.245.48.0/20.
     */
    public const TRUSTED_PROXIES = 'TRUSTED_PROXIES';

    private function trustedProxies(): array
    {
        return array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));
    }

    private function isTrustedProxy(string $ip): bool
    {
        $proxies = $this->trustedProxies();
        if ($proxies === []) {
            return false;
        }
        foreach ($proxies as $cidr) {
            if ($cidr === $ip) {
                return true;
            }
            if (str_contains($cidr, '/')) {
                [$subnet, $mask] = explode('/', $cidr, 2);
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $maskLong = (int) $mask === 0 ? null : -1 << (32 - (int) $mask);
                if ($ipLong !== false && $subnetLong !== false && $maskLong !== null
                    && ($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                    return true;
                }
                continue;
            }
        }

        return false;
    }

    /**
     * Rate-limit key. The TCP peer decides whether X-Forwarded-For counts:
     * untrusted senders get their socket IP (the header they send is
     * irrelevant); a trusted proxy's forwarded chain (rightmost untrusted
     * entry) sets the key.
     */
    private function clientKey(Request $request): string
    {
        $remote = $request->ip() ?? 'unknown';

        if ($this->isTrustedProxy($remote)) {
            $forwarded = $request->header('x-forwarded-for');
            if (is_string($forwarded) && $forwarded !== '') {
                $parts = array_map('trim', explode(',', $forwarded));
                $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
                if ($parts !== []) {
                    $rightmost = end($parts);
                    if (filter_var($rightmost, FILTER_VALIDATE_IP)) {
                        return $rightmost;
                    }
                }
            }
        }

        return $remote;
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Rate limiter windows:
     * inquiry 5/10min per IP (env-tunable), login 10/10min, admin reads 120/min.
     * Registered here (not bootstrap/app.php) because facades are unavailable
     * inside the middleware configuration closure.
     */
    public function boot(): void
    {
        RateLimiter::for('inquiry', function (Request $request) {
            $max = max(1, (int) env('INQUIRY_RATE_LIMIT_MAX', 5));

            // The socket peer is authoritative; X-Forwarded-For only counts
            // from TRUSTED_PROXIES (deny-by-default, see clientKey()).
            $ip = $this->clientKey($request);

            // 429 carries the pinned envelope, not Laravel's
            // default HTML error page. The throttle headers (Retry-After,
            // Ratelimit-*) are MERGED so clients can honor them.
            return Limit::perMinutes(10, $max)
                ->by('inquiry:' . $ip)
                ->response(fn ($request, $headers) => \App\Services\ApiResponse::rateLimited()->withHeaders($headers));
        });

        RateLimiter::for('login', function (Request $request) {
            // Same keying as `inquiry` (finding F1): the socket peer decides
            // whether X-Forwarded-For counts. Behind a reverse proxy every
            // visitor would otherwise share ONE bucket keyed by the proxy IP —
            // global lockout of all admin logins by a single attacker.
            $ip = $this->clientKey($request);

            return Limit::perMinutes(10, 10)
                ->by('login:' . $ip)
                ->response(fn ($request, $headers) => \App\Services\ApiResponse::rateLimited()->withHeaders($headers));
        });

        RateLimiter::for('admin-read', function (Request $request) {
            $admin = $request->attributes->get('admin');

            return Limit::perMinute(120)->by('admin-read:' . ($admin?->id ?? $request->ip()));
        });

        // Public availability is a READ: it must NOT share the inquiry form's
        // 5/10min write bucket. Sharing it meant 5 calendar fetches consumed
        // the contact form's budget (and vice versa) — and behind a reverse
        // proxy one shared per-IP bucket let a single visitor (or one curl
        // loop) blank the booking calendar AND the form for EVERYONE. 60/min
        // per client is far above any real browsing pattern, still bounded.
        RateLimiter::for('availability', function (Request $request) {
            $ip = $this->clientKey($request);

            return Limit::perMinute(60)
                ->by('availability:' . $ip)
                ->response(fn ($request, $headers) => \App\Services\ApiResponse::rateLimited()->withHeaders($headers));
        });
    }
}
