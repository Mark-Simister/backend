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
 * Sprint A changes no public behaviour: the new channel/character columns never
 * serialise, the public review page is unchanged, and the seeder is routed through the
 * supported ingestion service without writing payloads itself.
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

    public function test_the_public_review_page_ignores_editorial_state_in_sprint_a(): void
    {
        config(['reviews.rendering_enabled' => true, 'reviews.cache_ttl' => 0]);
        $this->associations();

        $unreleased = $this->ingest($this->reviewRow('pr_unreleased'));
        $held = $this->releasedReview('pr_held');
        $this->editorial()->hold($held, 'Legal check', null);
        $notPublished = $this->ingest($this->reviewRow('pr_draft', ['publish_status' => 'ready_for_review']));

        $this->get('http://example.test/review/' . $unreleased->review_slug)->assertOk();
        $this->get('http://example.test/review/' . $held->review_slug)->assertOk();
        $this->get('http://example.test/review/' . $notPublished->review_slug)->assertNotFound();
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
