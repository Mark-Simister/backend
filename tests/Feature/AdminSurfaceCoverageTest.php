<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * FU-6 — proof of coverage.
 *
 * The baseline gate closing 31 loose routes is worthless if it silently misses a group
 * declared elsewhere. routes/web.php has SEVEN separate prefix('admin') groups. Instead of
 * trusting that all seven were handled, this proves coverage by construction:
 *
 *   (1) the `web` middleware group contains EnsureAdminAccess, and
 *   (2) every admin/* route carries the `web` group.
 *
 * Together: every admin route is in `web`, and `web` contains the gate, so every admin
 * route is gated. An eighth prefix('admin') group added without the `web` group — or a
 * refactor that drops the gate from `web` — fails this test.
 *
 * (gatherMiddleware() returns the unexpanded `web` alias rather than the group's contents,
 * which is why coverage is asserted as the conjunction of the two facts above.)
 */
class AdminSurfaceCoverageTest extends TestCase
{
    private function adminRoutes(): array
    {
        return array_filter(
            Route::getRoutes()->getRoutes(),
            fn ($r) => $r->uri() === 'admin' || str_starts_with($r->uri(), 'admin/')
        );
    }

    public function test_the_web_group_contains_the_admin_gate(): void
    {
        $web = Route::getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(EnsureAdminAccess::class, $web,
            'EnsureAdminAccess must be in the web middleware group, or admin/* routes are ungated');
    }

    public function test_every_admin_route_is_in_the_web_group(): void
    {
        $admin = $this->adminRoutes();

        // Guard against a vacuous pass if the filter ever matches nothing.
        $this->assertGreaterThan(30, count($admin), 'expected the full admin surface');

        $ungated = [];
        foreach ($admin as $route) {
            if (! in_array('web', $route->gatherMiddleware(), true)) {
                $ungated[] = $route->methods()[0] . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $ungated,
            "these admin/* routes are not in the web group, so the baseline gate does not reach them:\n" . implode("\n", $ungated));
    }
}
