<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline authorization for the entire /admin surface (FU-6).
 *
 * The defect this closes is structural: `auth` — or, in one group, nothing at all — was
 * treated as sufficient to reach /admin. routes/web.php declares SEVEN separate
 * `prefix('admin')` groups; individual privileged routes were each expected to carry their
 * own `permission:`, and ~31 loose routes plus one entire group (top-categories, which had
 * no middleware whatsoever) did not. Because ApiUser::$table = 'users', a public frontend
 * member authenticates at the admin /login and, holding no web-guard permission, could
 * reach every one of them — including blocking a super_admin.
 *
 * This middleware is keyed on the request PATH, not on a route group, so it cannot be
 * missed by a group declared elsewhere or added later. AdminSurfaceCoverageTest asserts
 * that every admin/* route actually resolves it.
 *
 * It is a BASELINE, not the whole control. A sub_admin holding only faq.view passes this
 * and must still be stopped from user/video/etc. writes by each route's own permission —
 * that is the second half of the fix. Do not read "the gate is in place" as "authorized".
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin') && ! $request->is('admin/*')) {
            return $next($request);
        }

        $user = $request->user();

        // Parity with the `auth` middleware: guests are sent to login, not 403'd. This also
        // supplies the authentication that group @83 (top-categories) never had.
        if (! $user) {
            return $request->expectsJson()
                ? abort(401)
                : redirect()->guest(route('login'));
        }

        // A frontend member (api-guard `user`) has zero web-guard permissions. Any admin —
        // super_admin (all) or a sub_admin (a subset) — has at least one. Guard-filtered by
        // Spatie, verified empirically before this was written.
        abort_unless($user->getAllPermissions()->isNotEmpty(), 403);

        return $next($request);
    }
}
