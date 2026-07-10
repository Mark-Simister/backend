<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

/**
 * R1 — environment-controlled reverse-proxy trust, failing closed.
 *
 * WHY A PROVIDER, and not bootstrap/app.php. `Middleware::trustProxies()` calls the
 * static `TrustProxies::at()` immediately, inside the `afterResolving(HttpKernel::class)`
 * callback — which fires when public/index.php resolves the kernel, BEFORE the
 * LoadEnvironmentVariables and LoadConfiguration bootstrappers. There, `config()` throws
 * and `env()` returns null; and `at()` takes `array|string`, so there is no closure to
 * defer with. Writing `trustProxies(at: config(...))` compiles, runs, and silently trusts
 * nothing — or, if someone "fixes" it back to `at: '*'`, everything. A provider's boot()
 * runs after config is loaded and before any middleware handles a request, and survives
 * `php artisan config:cache` (CI runs it).
 *
 * FAIL CLOSED. With no TRUSTED_PROXIES set, this returns early and TrustProxies — still in
 * the global stack — trusts no proxy. That is the default in every environment, including
 * production. Nothing about the request can change it: not a header, not a host, not the
 * environment name. The admin backend at stg.beastierated.com is reachable directly, so a
 * client that could forge X-Forwarded-Host would be able to select a region and pull an
 * AU-only review page onto any host.
 *
 * MINIMAL HEADERS. Only HOST and PROTO. PORT is redundant once PROTO is trusted, and FOR
 * is not trusted at all — the renderer does no IP-based logic (no rate limiting, no geo,
 * no auth), so the cost is that its access logs show the proxy's address rather than the
 * client's. That is a deliberate trade, not an oversight.
 */
class ProxyTrustServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $proxies = config('reviews.trusted_proxies', []);

        if ($proxies === [] || $proxies === null) {
            return;   // trust nobody
        }

        TrustProxies::at($proxies);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
