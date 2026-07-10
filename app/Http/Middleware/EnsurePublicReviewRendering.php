<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R2 — only a host that has opted in may render public review pages.
 *
 * `PUBLIC_REVIEW_RENDERING_ENABLED` defaults to false, so the admin backend — which is
 * directly reachable and carries the same renderer code — 404s /review/* and /sitemap.xml
 * instead of becoming a second, indexable copy of the public site.
 *
 * This is a middleware rather than a conditional Route::get() on purpose. Conditional
 * registration is evaluated when routes are compiled, so `php artisan route:cache` (which
 * CI runs) would bake the decision in, and changing the env var afterwards would have no
 * effect until someone remembered `route:clear`. Reading config per request means
 * `config:clear` alone is sufficient — the same lifecycle `reviews.indexable` already has.
 *
 * /robots.txt is deliberately NOT behind this middleware: the static public/robots.txt is
 * gone, so a disabled host must still answer "Disallow: /" rather than 404.
 */
class EnsurePublicReviewRendering
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('reviews.rendering_enabled', false), 404);

        return $next($request);
    }
}
