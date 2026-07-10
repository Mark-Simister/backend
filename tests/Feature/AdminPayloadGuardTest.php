<?php

namespace Tests\Feature;

use App\Models\PublishedReviewPayload;
use App\Support\Reviews\AdminPayloadGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * P-5 — a seeder must not silently desync an admin-published review page.
 *
 * ContentSeeder / FlagshipReviewSeeder / WebsiteReviewSeeder rewrite `videos` and the
 * `video_region` pivot. Against the shared staging database that changes which regions a
 * live public page serves, with no error. `reviews:sync-payload-regions` repairs it, but
 * relying on someone remembering to run it is not a safeguard.
 */
class AdminPayloadGuardTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $source): void
    {
        (new PublishedReviewPayload())->forceFill([
            'published_review_id' => $source . '__1',
            'review_slug' => 'a-slug-' . $source,
            'publish_status' => 'published',
            'source' => $source,
            'review_page_json' => [],
        ])->save();
    }

    public function test_it_permits_seeding_a_database_with_no_admin_payloads(): void
    {
        $this->payload('pipeline');

        AdminPayloadGuard::assertSafe(null, 'DatabaseSeeder');   // no exception

        $this->assertSame(0, AdminPayloadGuard::adminPayloadCount());
    }

    public function test_it_refuses_when_an_admin_payload_exists_and_nobody_can_confirm(): void
    {
        $this->payload('admin');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('admin-published review page');

        AdminPayloadGuard::assertSafe(null, 'DatabaseSeeder');
    }

    /**
     * The guard is wired into the tracked DatabaseSeeder, which is what a bare `db:seed`
     * runs. Under --no-interaction it refuses BEFORE seeding anything.
     *
     * (There is no matching "db:seed succeeds" test: the working-tree DatabaseSeeder calls
     * the untracked content seeders, so a full run would pass or fail depending on which
     * files happen to exist locally. The guard itself is covered directly, above.)
     */
    public function test_a_non_interactive_db_seed_refuses_when_admin_payloads_exist(): void
    {
        $this->payload('admin');

        // The guard throws out of the seeder; the console kernel turns that into a
        // non-zero exit on a real CLI. `fail()` stays outside the try — PHPUnit's
        // AssertionFailedError is itself a RuntimeException.
        $caught = null;
        try {
            $this->artisan('db:seed --no-interaction')->run();
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'a non-interactive db:seed must refuse, not proceed');
        $this->assertStringContainsString('admin-published review page', $caught->getMessage());
        $this->assertSame(0, \App\Models\User::count(), 'it must refuse before seeding anything');
    }

    /** The table may not exist yet (fresh install, or the renderer's own database). */
    public function test_it_is_a_noop_when_the_payload_table_is_absent(): void
    {
        \Illuminate\Support\Facades\Schema::drop('published_review_payloads');

        AdminPayloadGuard::assertSafe(null, 'DatabaseSeeder');

        $this->assertSame(0, AdminPayloadGuard::adminPayloadCount());
    }
}
