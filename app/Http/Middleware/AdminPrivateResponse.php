<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Content & Media routes are private operational surfaces (design record section
 * 15, item 13): never indexed and never cached - including the authenticated review
 * preview, which renders the public review template. Applied to the Sprint-A admin route
 * group only; EnsureAdminAccess remains the authorization baseline.
 */
class AdminPrivateResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
