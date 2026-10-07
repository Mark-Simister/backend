<?php

namespace Tests\Feature\AdminContent;

use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Services\Reviews\ReviewPayloadIngestor;
use Database\Seeders\PublishedReviewPayloadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * Public-contract guards carried from Sprint A: the new channel/character columns never
 * serialise, and the seeder is routed through the supported ingestion service without
 * writing payloads itself. Sprint A's "the public review page ignores editorial state"
 * expectation is intentionally SUPERSEDED by Sprint B: the page now renders a review only
 * when its release binding verifies (see PublicEnforcementTest for the full E1-E8 suite).
 */
class NoPublicChangeTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
    }

    public function test_new_channel_and_character_columns_are_never_serialised(): void
    {
        $channel = Channel::query()->forceCreate([
            'name' => 'Pet Channel', 'pipeline_code' => 'CH_PET', 'bio' => 'Internal draft bio', 'category_label' => 'Pets',
        ]);
        $character = Character::query()->forceCreate([
            'name' => 'Rex', 'pipeline_code' => 'CHR_REX',
            'insights' => ['quote' => 'Woof'], 'production_profile' => ['voice' => 'gravel'],
        ]);

        foreach (['bio', 'category_label', 'pipeline_code'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $channel->fresh()->toArray());
        }
        foreach (['insights', 'production_profile', 'pipeline_code'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $character->fresh()->toArray());
        }
        $this->assertStringNotContainsString('gravel', $character->fresh()->toJson());
        $this->assertSame(['voice' => 'gravel'], $character->fresh()->production_profile, 'still readable internally');
    }

    public function test_the_public_review_page_now_requires_a_verified_release(): void
    {
        config(['reviews.rendering_enabled' => true, 'reviews.cache_ttl' => 0]);
        $this->associations();

        $unreleased = $this->ingest($this->reviewRow('pr_unreleased'));
        $held = $this->releasedReview('pr_held');
        $this->editorial()->hold($held, 'Legal check', null);
        $released = $this->releasedReview('pr_released');
        $notPublished = $this->ingest($this->reviewRow('pr_draft', ['publish_status' => 'ready_for_review']));

        $this->get('http://example.test/review/' . $unreleased->review_slug)->assertNotFound();
        $this->get('http://example.test/review/' . $held->review_slug)->assertNotFound();
        $this->get('http://example.test/review/' . $notPublished->review_slug)->assertNotFound();
        $this->get('http://example.test/review/' . $released->review_slug)->assertOk();
    }

    public function test_the_seeder_routes_every_row_through_the_ingestion_service(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/published_review_payloads.json')), true) ?: [];
        $expected = count(array_filter($rows, fn ($r) => ! empty($r['published_review_id']) && ! empty($r['review_slug'])));

        $spy = new class () {
            public array $calls = [];

            public function upsert(array $row, bool $force = false): array
            {
                $this->calls[] = [$row['published_review_id'], $force];

                return ['status' => ReviewPayloadIngestor::IMPORTED, 'payload' => null, 'invalidation' => null];
            }
        };
        $this->app->instance(ReviewPayloadIngestor::class, $spy);

        app(PublishedReviewPayloadSeeder::class)->run();

        $this->assertCount($expected, $spy->calls);
        $this->assertSame(0, PublishedReviewPayload::query()->count(), 'the seeder itself writes no payload rows');
        $this->assertNotContains(true, array_column($spy->calls, 1), 'no --force unless asked');

        $source = file_get_contents(database_path('seeders/PublishedReviewPayloadSeeder.php'));
        $this->assertStringNotContainsString('updateOrCreate', $source);
        $this->assertStringNotContainsString('PublishedReviewPayload::', $source);
    }
}
