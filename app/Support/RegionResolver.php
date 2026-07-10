<?php

namespace App\Support;

/**
 * Resolves a request's regional Region.region_code from its host — the SAME
 * host the review controller uses for the canonical URL ($request->getHost(),
 * which returns the trusted X-Forwarded-Host under our ProxyPreserveHost Off
 * proxy). Region-gating and canonical therefore derive from one value and
 * cannot diverge.
 *
 * Unknown/non-regional hosts (origin, local, CLI, an un-mapped region) resolve
 * to null → only all-region payloads serve (fail-safe: a region-restricted
 * payload is hidden, never leaked to every region).
 */
class RegionResolver
{
    /** @return string|null e.g. 'AU', or null when the host isn't a known regional host. */
    public static function fromHost(?string $host): ?string
    {
        if (! $host) {
            return null;
        }
        $label = strtolower(strtok($host, '.'));   // "au" from "au.fstg.beastierated.com"

        return config('reviews.region_hosts')[$label] ?? null;
    }
}
