<?php

namespace Tests\Feature;

use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Support\BestFor\SelectionSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The snapshot must be TRUTHFUL, not merely populated.
 *
 * The tests that matter most here are the ones proving which source wins, because the
 * payload offers more than one plausible-looking value for the same idea and two of them
 * are wrong.
 */
class SelectionSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $id, array $overrides = [], array $pageOverrides = []): PublishedReviewPayload
    {
        $page = array_replace_recursive([
            'payload_schema_version' => SelectionSnapshotBuilder::REQUIRED_PAYLOAD_SCHEMA_VERSION,
            'product' => [
                'product_name' => 'Acme Auto Feeder',
                'hero_image_url' => 'https://example.test/hero.jpg',
            ],
            'review_facts' => [
                'public_signal' => 'Strong',
                'product' => 'Acme Auto Feeder (facts)',
            ],
        ], $pageOverrides);

        $payload = new PublishedReviewPayload();
        $payload->forceFill(array_merge([
            'published_review_id' => $id,
            'review_slug' => 'acme-auto-feeder-' . $id,
            'publish_status' => 'published',
            'source' => 'pipeline',
            'product_uid' => 'ASIN_B000' . $id,
            'final_beastie_score' => 8.5,
            'public_score' => 4.4,
            'public_rating_count' => 1200,
            'hero_image_url' => 'https://example.test/column-hero.jpg',
            'og_image_url' => 'https://example.test/og.jpg',
            'review_page_json' => $page,
        ], $overrides))->save();

        return $payload;
    }

    private function builder(): SelectionSnapshotBuilder
    {
        return new SelectionSnapshotBuilder();
    }

    public function test_it_resolves_every_derived_value_from_its_authoritative_source(): void
    {
        $this->payload('1');

        $result = $this->builder()->forSelection('1');

        $this->assertSame([], $result['errors']);
        $this->assertSame('acme-auto-feeder-1', $result['values']['review_slug_as_published']);
        $this->assertSame('Acme Auto Feeder', $result['values']['product_name_as_published']);
        $this->assertSame('https://example.test/hero.jpg', $result['values']['product_image_ref_as_published']);
        $this->assertSame('ASIN_B0001', $result['values']['source_product_identifier']);
        $this->assertSame(8.5, $result['values']['beastie_score_as_published']);
        $this->assertSame(4.4, $result['values']['public_rating_as_published']);
        $this->assertSame(1200, $result['values']['public_rating_count_as_published']);
        $this->assertSame('Strong', $result['values']['public_signal_as_published']);
    }

    public function test_ratings_count_comes_from_the_column_not_the_misleading_review_facts_key(): void
    {
        // review_facts.public_rating_count is populated from `reviews_analysed` — the
        // retailer reviews ANALYSED. Publishing it would look truthful and be wrong.
        $this->payload('2', ['public_rating_count' => 1200], [
            'review_facts' => ['public_rating_count' => 37],
        ]);

        $result = $this->builder()->forSelection('2');

        $this->assertSame(1200, $result['values']['public_rating_count_as_published']);
        $this->assertNotSame(37, $result['values']['public_rating_count_as_published']);
    }

    public function test_confidence_tier_is_never_returned_as_display_data(): void
    {
        $this->payload('3', ['confidence_tier' => 'Very strong'], [
            'review_facts' => ['confidence_tier' => 'Very strong'],
        ]);

        $result = $this->builder()->forSelection('3');

        $this->assertNotContains('Very strong', $result['values']);
        $this->assertArrayNotHasKey('confidence_tier', $result['values']);
    }

    public function test_it_does_not_mistake_the_review_facts_best_for_list_for_the_collection(): void
    {
        $this->payload('4', [], [
            'review_facts' => ['best_for' => ['multi-pet homes', 'wet food']],
        ]);

        $result = $this->builder()->forSelection('4');

        $this->assertSame([], $result['errors']);
        $this->assertArrayNotHasKey('best_for', $result['values']);
    }

    public function test_a_missing_payload_is_an_error_not_an_empty_snapshot(): void
    {
        $result = $this->builder()->forSelection('does-not-exist');

        $this->assertSame([], $result['values']);
        $this->assertStringContainsString('no published review payload', $result['errors'][0]);
    }

    public function test_the_v6_schema_version_is_required(): void
    {
        $this->payload('5', [], ['payload_schema_version' => 'something_else']);

        $result = $this->builder()->forSelection('5');

        $this->assertSame([], $result['values']);
        $this->assertStringContainsString('payload_schema_version', implode(' ', $result['errors']));
    }

    public function test_an_absent_public_signal_refuses(): void
    {
        $payload = $this->payload('6');
        $page = $payload->review_page_json;
        unset($page['review_facts']['public_signal']);
        $payload->forceFill(['review_page_json' => $page])->save();

        $result = $this->builder()->forSelection('6');

        $this->assertSame([], $result['values']);
        $this->assertStringContainsString('public_signal is missing', implode(' ', $result['errors']));
    }

    public function test_a_public_signal_outside_the_approved_domain_refuses(): void
    {
        $this->payload('7', [], ['review_facts' => ['public_signal' => 'Excellent']]);

        $result = $this->builder()->forSelection('7');

        $this->assertSame([], $result['values']);
        $this->assertStringContainsString('outside the approved domain', implode(' ', $result['errors']));
    }

    public function test_missing_score_rating_or_count_each_refuse(): void
    {
        $this->payload('8', [
            'final_beastie_score' => null,
            'public_score' => null,
            'public_rating_count' => null,
        ]);

        $errors = implode(' ', $this->builder()->forSelection('8')['errors']);

        $this->assertStringContainsString('final_beastie_score is missing', $errors);
        $this->assertStringContainsString('public_score is missing', $errors);
        $this->assertStringContainsString('public_rating_count is missing', $errors);
    }

    public function test_the_image_falls_back_through_product_then_columns_and_may_be_null(): void
    {
        $this->payload('9', [], ['product' => ['hero_image_url' => null]]);
        $this->assertSame(
            'https://example.test/column-hero.jpg',
            $this->builder()->forSelection('9')['values']['product_image_ref_as_published']
        );

        $this->payload('10', ['hero_image_url' => null], ['product' => ['hero_image_url' => null]]);
        $this->assertSame(
            'https://example.test/og.jpg',
            $this->builder()->forSelection('10')['values']['product_image_ref_as_published']
        );

        $this->payload('11', ['hero_image_url' => null, 'og_image_url' => null], ['product' => ['hero_image_url' => null]]);
        $this->assertNull($this->builder()->forSelection('11')['values']['product_image_ref_as_published']);
    }

    public function test_a_missing_source_product_identifier_is_permitted_and_null(): void
    {
        $this->payload('12', ['product_uid' => null]);

        $result = $this->builder()->forSelection('12');

        $this->assertSame([], $result['errors']);
        $this->assertNull($result['values']['source_product_identifier']);
    }

    public function test_the_product_name_falls_back_to_review_facts(): void
    {
        $this->payload('13', [], ['product' => ['product_name' => null]]);

        $this->assertSame(
            'Acme Auto Feeder (facts)',
            $this->builder()->forSelection('13')['values']['product_name_as_published']
        );
    }

    public function test_edition_values_come_from_the_category_and_subject(): void
    {
        $category = Category::create(['name' => 'Automatic Dog Feeders']);
        $region = \App\Models\Region::firstOrCreate(
            ['region_code' => 'AU'],
            ['region_name' => 'AU region', 'currency' => 'AUD', 'is_active' => true]
        );
        $subject = BestForSubject::create([
            'category_id' => $category->id,
            'year' => 2026,
            'region_id' => $region->id,
        ]);

        $result = $this->builder()->forEdition($subject);

        $this->assertSame([], $result['errors']);
        $this->assertSame('Automatic Dog Feeders', $result['values']['category_name_as_published']);
        $this->assertSame(2026, $result['values']['year_as_published']);
    }
}
