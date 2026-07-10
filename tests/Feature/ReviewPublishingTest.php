<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use App\Support\Reviews\ReviewSchemaBuilder;
use App\Support\Reviews\ReviewSlugGenerator;
use App\Support\Reviews\VideoReviewMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 publishing engine: slug generator, mapper, schema builder, publisher.
 *
 * FIXTURES: the "rich" path is tested against the REAL videos.review JSON exported
 * from videos #122 (pipeline import) and #123 (flagship seed) — the only two real
 * review payloads in the system. The "thin" path uses a CLEARLY-SYNTHETIC fixture
 * (product + score, review = null), because no real admin video currently has a
 * product_name and therefore none can reach any path today.
 */
class ReviewPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function realReview(int $id = 122): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/video_review_{$id}.json")), true);
    }

    private function region(string $code): Region
    {
        $r = Region::firstOrNew(['region_code' => $code]);
        $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$']);
        $r->save();
        return $r;
    }

    /** videos.character_id / channel_id / category_id are NOT NULL. */
    private function scaffold(): array
    {
        $char = Character::firstOrNew(['name' => 'Hound Dog Hank']);
        $char->forceFill(['name' => 'Hound Dog Hank'])->save();

        $chan = Channel::firstOrNew(['name' => 'BarkTastic']);
        $chan->forceFill(['name' => 'BarkTastic'])->save();

        $cat = Category::firstOrNew(['name' => 'Dog Apparel']);
        $cat->forceFill(['name' => 'Dog Apparel', 'slug' => 'dog-apparel'])->save();

        return [$char->id, $chan->id, $cat->id];
    }

    private function makeVideo(array $attrs = [], array $regions = ['AU']): Video
    {
        [$charId, $chanId, $catId] = $this->scaffold();

        $v = new Video();
        $v->forceFill(array_merge([
            'title' => 'Vecomfy Fleece Dog Hoodie — Review',
            'type' => 'youtube',
            'video_url' => '',
            'character_id' => $charId,
            'channel_id' => $chanId,
            'category_id' => $catId,
            'product_name' => 'Vecomfy Fleece Dog Hoodie',
            'product_asin_sku' => 'B07HCB1JPS',
            'review_type' => 'review',
            'status' => 'published',
            'final_beastie_score' => 4.7,
            'public_rating' => 4.5,
            'affiliate_link' => 'https://www.amazon.com/dp/B07HCB1JPS',
            'review' => null,   // thin by default
        ], $attrs))->save();

        $v->regions()->sync(collect($regions)->map(fn ($c) => $this->region($c)->id)->all());

        return $v->fresh();
    }

    private function publisher(): ReviewPayloadPublisher
    {
        return app(ReviewPayloadPublisher::class);
    }

    // ───────────────────────── ReviewSlugGenerator ─────────────────────────

    public function test_slug_uses_product_name_with_asin_suffix(): void
    {
        $v = $this->makeVideo();
        $this->assertSame('vecomfy-fleece-dog-hoodie-b07hcb1jps', ReviewSlugGenerator::forVideo($v));
    }

    public function test_slug_without_asin_has_no_suffix(): void
    {
        $v = $this->makeVideo(['product_asin_sku' => null]);
        $this->assertSame('vecomfy-fleece-dog-hoodie', ReviewSlugGenerator::forVideo($v));
    }

    public function test_slug_is_immutable_after_title_or_product_edit(): void
    {
        $v = $this->makeVideo();
        $v->forceFill(['review_slug' => 'original-slug-b07hcb1jps'])->save();

        $v->forceFill(['title' => 'Totally Different Title', 'product_name' => 'Renamed Product'])->save();

        $this->assertSame('original-slug-b07hcb1jps', ReviewSlugGenerator::forVideo($v->fresh()));
    }

    public function test_slug_collision_falls_back_to_numeric_suffix(): void
    {
        // occupy the base slug and its -2 variant
        $this->makeVideo()->forceFill(['review_slug' => 'vecomfy-fleece-dog-hoodie-b07hcb1jps'])->save();
        $this->makeVideo(['product_asin_sku' => 'B07HCB1JPS'])->forceFill(['review_slug' => 'vecomfy-fleece-dog-hoodie-b07hcb1jps-2'])->save();

        $fresh = $this->makeVideo();   // no slug yet → must land on -3
        $this->assertSame('vecomfy-fleece-dog-hoodie-b07hcb1jps-3', ReviewSlugGenerator::forVideo($fresh));
    }

    // ───────────────────────── VideoReviewMapper ─────────────────────────

    public function test_content_tier_rich_for_real_review_and_thin_without(): void
    {
        $mapper = new VideoReviewMapper();

        $this->assertSame('rich', $mapper->contentTier($this->makeVideo(['review' => $this->realReview(122)])));
        $this->assertSame('rich', $mapper->contentTier($this->makeVideo(['review' => $this->realReview(123)])));
        $this->assertSame('thin', $mapper->contentTier($this->makeVideo(['review' => null])));
        $this->assertSame('thin', $mapper->contentTier($this->makeVideo(['review' => []])));
    }

    public function test_mapper_rich_restructures_real_review_into_payload_shape(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $rp = (new VideoReviewMapper())->map($v, 'slug-x', 'rich');
        $src = $this->realReview(122);

        // string → object restructures
        $this->assertSame($src['owner_consensus'], $rp['owner_consensus']['summary']);
        $this->assertSame($src['disclosure'], $rp['disclosure']['affiliate_disclosure_text']);

        // field renames
        $this->assertSame($src['character_take']['text'], $rp['character_take']['summary']);
        $this->assertSame($src['character_take']['trait_chips'], $rp['character_take']['highlights']);

        // flat → nested
        $this->assertSame($src['pros'], $rp['pros_cons']['pros']);
        $this->assertSame($src['cons'], $rp['pros_cons']['cons']);
        $this->assertSame($src['full_report'], $rp['full_character_review']['sections']);

        // the single 1:1 mapping
        $this->assertSame($src['qa'], $rp['qa']);

        // synthesized from columns/relations
        $this->assertSame('Vecomfy Fleece Dog Hoodie', $rp['product']['product_name']);
        $this->assertSame(4.7, (float) $rp['beastiescore']['final_score']);
        $this->assertSame($src['retailers'], $rp['commerce']['retailers']);
        $this->assertArrayHasKey('review_facts', $rp);
    }

    public function test_mapper_thin_omits_all_review_sections(): void
    {
        // SYNTHETIC fixture: no real admin video has a product_name today.
        $v = $this->makeVideo(['review' => null]);
        $rp = (new VideoReviewMapper())->map($v, 'slug-x', 'thin');

        // present: genuinely known
        $this->assertSame('Vecomfy Fleece Dog Hoodie', $rp['product']['product_name']);
        $this->assertArrayHasKey('hero', $rp);
        $this->assertArrayHasKey('beastiescore', $rp);
        $this->assertArrayHasKey('seo', $rp);
        $this->assertArrayHasKey('disclosure', $rp);
        $this->assertSame('https://www.amazon.com/dp/B07HCB1JPS', $rp['commerce']['primary_affiliate_url']);

        // absent: never fabricated
        foreach (['quick_verdict', 'pros_cons', 'qa', 'review_facts', 'owner_consensus', 'full_character_review', 'sources', 'safety'] as $key) {
            $this->assertArrayNotHasKey($key, $rp, "thin payload must not contain '{$key}'");
        }
    }

    // ───────────────────────── ReviewSchemaBuilder ─────────────────────────

    private function types(array $graph): array
    {
        return array_column($graph['@graph'], '@type');
    }

    public function test_schema_rich_emits_review_and_faq(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $g = (new ReviewSchemaBuilder())->build($v, 'slug-x', 'rich');
        $types = $this->types($g);

        $this->assertContains('Organization', $types);
        $this->assertContains('WebPage', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertContains('Product', $types);
        $this->assertContains('Review', $types);
        $this->assertContains('FAQPage', $types);

        // valid JSON-LD
        $this->assertIsString(json_encode($g, JSON_THROW_ON_ERROR));
    }

    public function test_schema_thin_never_emits_review_aggregaterating_or_faq(): void
    {
        $v = $this->makeVideo(['review' => null]);
        $g = (new ReviewSchemaBuilder())->build($v, 'slug-x', 'thin');
        $types = $this->types($g);

        $this->assertContains('Product', $types);
        $this->assertContains('WebPage', $types);
        $this->assertContains('BreadcrumbList', $types);

        $this->assertNotContains('Review', $types, 'thin must not emit Review schema');
        $this->assertNotContains('FAQPage', $types, 'thin must not emit FAQPage');

        $product = collect($g['@graph'])->firstWhere('@type', 'Product');
        $this->assertArrayNotHasKey('aggregateRating', $product, 'thin must not emit AggregateRating');
        $this->assertArrayNotHasKey('review', $product);
        $this->assertStringNotContainsString('AggregateRating', json_encode($g));
    }

    public function test_schema_rich_omits_aggregaterating_without_a_real_rating_count(): void
    {
        $review = $this->realReview(122);
        unset($review['reviews_analysed']);          // no real count → no aggregateRating
        $v = $this->makeVideo(['review' => $review]);

        $g = (new ReviewSchemaBuilder())->build($v, 'slug-x', 'rich');
        $product = collect($g['@graph'])->firstWhere('@type', 'Product');

        $this->assertArrayNotHasKey('aggregateRating', $product);
        $this->assertContains('Review', $this->types($g));   // Review still valid (real content exists)
    }

    // ───────────────────────── ReviewPayloadPublisher ─────────────────────────

    public function test_publish_rich_creates_admin_payload_marked_published(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)], ['AU', 'US']);
        $payload = $this->publisher()->publish($v);

        $this->assertNotNull($payload);
        $this->assertSame('admin', $payload->source);
        $this->assertSame('published', $payload->publish_status, "admin rows must be 'published', never 'ready_for_review'");
        $this->assertSame('rich', $payload->content_tier);
        $this->assertSame($v->id, $payload->video_id);
        $this->assertSame('vecomfy-fleece-dog-hoodie-b07hcb1jps', $payload->review_slug);
        $this->assertEqualsCanonicalizing(['AU', 'US'], $payload->regions()->pluck('region_code')->all());

        $v->refresh();
        $this->assertSame('published', $v->seo_publish_status);
        $this->assertNotNull($v->seo_published_at);
        $this->assertNotNull($v->seo_last_published_at);
        $this->assertNull($v->seo_publish_error);
    }

    public function test_publish_thin_sets_content_tier_thin(): void
    {
        $payload = $this->publisher()->publish($this->makeVideo(['review' => null]));

        $this->assertNotNull($payload);
        $this->assertSame('thin', $payload->content_tier);
        $this->assertSame('published', $payload->publish_status);
    }

    public function test_publish_refused_without_product_name(): void
    {
        $v = $this->makeVideo(['product_name' => null]);

        $this->assertNull($this->publisher()->publish($v));
        $this->assertSame(0, PublishedReviewPayload::count());

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('product_name', $v->seo_publish_error);
    }

    public function test_publish_refused_without_regions(): void
    {
        $v = $this->makeVideo([], []);   // no regions → an empty pivot would mean ALL regions

        $this->assertNull($this->publisher()->publish($v));
        $this->assertSame(0, PublishedReviewPayload::count());

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('region', $v->seo_publish_error);
    }

    public function test_forced_mapper_failure_records_error_and_leaves_existing_payload_untouched(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $original = $this->publisher()->publish($v);
        $this->assertSame('rich', $original->content_tier);

        // now make the mapper explode on the next run
        $this->app->bind(VideoReviewMapper::class, fn () => new class extends VideoReviewMapper {
            public function map(Video $video, string $slug, string $tier): array
            {
                throw new \RuntimeException('mapper exploded');
            }
        });

        $this->assertNull($this->publisher()->publish($v->fresh()));

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('mapper exploded', $v->seo_publish_error);

        // the live payload is untouched
        $payload = PublishedReviewPayload::where('video_id', $v->id)->first();
        $this->assertSame('published', $payload->publish_status);
        $this->assertSame('rich', $payload->content_tier);
    }

    /** Amendment 2(a): content_tier must be RE-EVALUATED on refresh, not frozen at first publish. */
    public function test_refresh_transitions_content_tier_thin_to_rich_when_review_added(): void
    {
        $v = $this->makeVideo(['review' => null]);
        $this->assertSame('thin', $this->publisher()->publish($v)->content_tier);

        // an admin later adds real review content
        $v->fresh()->forceFill(['review' => $this->realReview(122)])->save();

        $payload = $this->publisher()->refresh($v->fresh());

        $this->assertNotNull($payload);
        $this->assertSame('rich', $payload->content_tier, 'content_tier must transition thin → rich on refresh');
        $this->assertArrayHasKey('pros_cons', $payload->review_page_json);
    }

    /** Amendment 2(b): a gate failure at REFRESH time must not withdraw the live page. */
    public function test_refresh_gate_failure_leaves_live_payload_untouched_and_errors_on_video(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        // product_name later cleared, then an edit triggers a refresh
        $v->fresh()->forceFill(['product_name' => null])->save();
        $this->assertNull($this->publisher()->refresh($v->fresh()));

        $payload = PublishedReviewPayload::where('video_id', $v->id)->first();
        $this->assertSame('published', $payload->publish_status, 'live payload must NOT be withdrawn by a gate failure');
        $this->assertSame('rich', $payload->content_tier);

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('product_name', $v->seo_publish_error);
    }

    public function test_refresh_is_a_noop_until_explicitly_published(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);

        $this->assertNull($this->publisher()->refresh($v), 'refresh must not create the first payload');
        $this->assertSame(0, PublishedReviewPayload::count());
    }

    public function test_refresh_withdraws_when_all_regions_removed(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $v->regions()->sync([]);   // all eligible regions removed
        $this->assertNull($this->publisher()->refresh($v->fresh()));

        $payload = PublishedReviewPayload::where('video_id', $v->id)->first();
        $this->assertSame('withdrawn', $payload->publish_status);
        $this->assertSame('withdrawn', $v->fresh()->seo_publish_status);
    }

    public function test_withdrawn_payload_is_not_publicly_visible(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);
        $this->publisher()->withdraw($v->fresh());

        config(['reviews.public_statuses' => ['published']]);
        $this->assertNull(
            PublishedReviewPayload::where('review_slug', $v->fresh()->review_slug)->publicForRegion('AU')->first()
        );
    }
}
