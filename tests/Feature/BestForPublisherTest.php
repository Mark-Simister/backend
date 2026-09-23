<?php

namespace Tests\Feature;

use App\Exceptions\BestForPublishGateException;
use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Services\BestForPublisher;
use App\Support\BestFor\PublicationConfirmations;
use App\Support\BestFor\SelectionSnapshotBuilder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publication as one committed unit.
 *
 * The load-bearing test here is the snapshot overwrite: the drafts deliberately carry
 * wrong values, so a publisher that merely validated non-null would pass every other
 * assertion and still publish a lie.
 */
class BestForPublisherTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $reviewIds = ['PR-1', 'PR-2', 'PR-3', 'PR-4', 'PR-5'];

    /** A test-only region fixture. It does not assert that a real GLOBAL row exists anywhere. */
    private function region(string $code = 'AU'): Region
    {
        return Region::firstOrCreate(
            ['region_code' => $code],
            ['region_name' => $code . ' region', 'currency' => 'AUD', 'is_active' => true]
        );
    }

    /**
     * @param  string[]  $regionCodes  explicit regional evidence; Best For refuses an empty set
     */
    private function payload(string $id, array $overrides = [], array $regionCodes = ['AU']): PublishedReviewPayload
    {
        $payload = new PublishedReviewPayload();
        $payload->forceFill(array_merge([
            'published_review_id' => $id,
            'review_slug' => 'slug-' . strtolower($id),
            'publish_status' => 'published',
            'source' => 'pipeline',
            'product_uid' => 'ASIN_' . strtoupper(str_replace('-', '', $id)),
            'final_beastie_score' => 8.5,
            'public_score' => 4.4,
            'public_rating_count' => 1200,
            'review_page_json' => [
                'payload_schema_version' => SelectionSnapshotBuilder::REQUIRED_PAYLOAD_SCHEMA_VERSION,
                'product' => ['product_name' => 'Product ' . $id, 'hero_image_url' => 'https://example.test/' . $id . '.jpg'],
                'review_facts' => ['public_signal' => 'Strong'],
            ],
        ], $overrides))->save();

        $payload->regions()->sync(
            array_map(fn (string $code) => $this->region($code)->id, $regionCodes)
        );

        return $payload;
    }

    private function subject(string $categoryName = 'Automatic Dog Feeders', int $year = 2026, string $regionCode = 'AU'): BestForSubject
    {
        return BestForSubject::create([
            'category_id' => Category::create(['name' => $categoryName])->id,
            'year' => $year,
            'region_id' => $this->region($regionCode)->id,
        ]);
    }

    private function draft(BestForSubject $subject, bool $createPayloads = true): BestForEdition
    {
        $edition = BestForEdition::create([
            'subject_id' => $subject->id,
            // Deliberately wrong. Publication must replace both from source.
            'category_name_as_published' => 'WRONG CATEGORY',
            'year_as_published' => 1999,
            'methodology_text' => 'Five products, scored on the published BeastieScore method.',
            'video_asset_id' => 'vimeo:100001',
        ]);

        $superlatives = ['Best overall', 'Best value', 'Best for large dogs', 'Best budget', 'Best tech'];

        foreach ($this->reviewIds as $i => $reviewId) {
            if ($createPayloads) {
                $this->payload($reviewId);
            }

            BestForEditionSelection::create([
                'edition_id' => $edition->id,
                'position' => $i + 1,
                'published_review_id' => $reviewId,
                // Every derived value below is deliberately wrong.
                'review_slug_as_published' => 'stale-slug',
                'product_name_as_published' => 'Stale product name',
                'product_image_ref_as_published' => 'https://example.test/stale.jpg',
                'source_product_identifier' => 'STALE',
                'superlative' => $superlatives[$i],
                'selection_reason' => 'Scored highest on the published method.',
                'beastie_score_as_published' => 1.1,
                'public_rating_as_published' => 1.2,
                'public_rating_count_as_published' => 7,
                'public_signal_as_published' => 'Limited',
            ]);
        }

        return $edition;
    }

    private function confirmations(): PublicationConfirmations
    {
        return new PublicationConfirmations(
            notDivergenceHeld: $this->reviewIds,
            categoryConfirmed: $this->reviewIds,
            productDistinctnessConfirmed: true,
            videoConsistencyConfirmed: true,
        );
    }

    public function test_it_allocates_an_opaque_ulid_key_and_a_sequence_at_creation(): void
    {
        $subject = $this->subject();
        $first = $this->draft($subject, false);

        $this->assertSame(26, strlen($first->public_edition_key));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $first->public_edition_key);
        $this->assertSame(1, $first->edition_sequence);

        $second = BestForEdition::create([
            'subject_id' => $subject->id,
            'category_name_as_published' => 'x',
            'year_as_published' => 2026,
            'methodology_text' => 'x',
            'video_asset_id' => 'vimeo:2',
        ]);

        $this->assertSame(2, $second->edition_sequence);
        $this->assertNotSame($first->public_edition_key, $second->public_edition_key);
    }

    public function test_an_explicit_key_or_sequence_still_wins(): void
    {
        $subject = $this->subject();

        $edition = BestForEdition::create([
            'public_edition_key' => 'explicit-key',
            'subject_id' => $subject->id,
            'edition_sequence' => 9,
            'category_name_as_published' => 'x',
            'year_as_published' => 2026,
            'methodology_text' => 'x',
            'video_asset_id' => 'vimeo:1',
        ]);

        $this->assertSame('explicit-key', $edition->public_edition_key);
        $this->assertSame(9, $edition->edition_sequence);
    }

    public function test_publication_refuses_a_non_ulid_permanent_key(): void
    {
        $edition = $this->draft($this->subject());
        $edition->forceFill(['public_edition_key' => 'best-automatic-dog-feeders-2026'])->save();

        try {
            (new BestForPublisher())->publish($edition, $this->confirmations());
            $this->fail('Expected publication to be refused for a non-ULID permanent key.');
        } catch (BestForPublishGateException $e) {
            $this->assertStringContainsString('is not a valid ULID', $e->getMessage());
        }

        $this->assertNull($edition->fresh()->published_at);
    }

    public function test_a_gate_failure_throws_and_writes_nothing(): void
    {
        $edition = $this->draft($this->subject());

        try {
            (new BestForPublisher())->publish($edition, PublicationConfirmations::none());
            $this->fail('Expected publication to be refused.');
        } catch (BestForPublishGateException $e) {
            $this->assertNotEmpty($e->errors);
        }

        $stored = $edition->fresh();
        $this->assertNull($stored->published_at);
        $this->assertSame('WRONG CATEGORY', $stored->category_name_as_published);
        $this->assertSame('stale-slug', $stored->selections()->first()->review_slug_as_published);
        $this->assertNull($stored->current_for_subject_id);
    }

    public function test_publication_overwrites_every_derived_value_from_its_authoritative_source(): void
    {
        $subject = $this->subject('Automatic Dog Feeders', 2026);
        $edition = $this->draft($subject);

        $published = (new BestForPublisher())->publish($edition, $this->confirmations());

        $this->assertNotNull($published->published_at);
        $this->assertSame('Automatic Dog Feeders', $published->category_name_as_published);
        $this->assertSame(2026, $published->year_as_published);

        $selection = $published->selections()->where('published_review_id', 'PR-1')->first();
        $this->assertSame('slug-pr-1', $selection->review_slug_as_published);
        $this->assertSame('Product PR-1', $selection->product_name_as_published);
        $this->assertSame('https://example.test/PR-1.jpg', $selection->product_image_ref_as_published);
        $this->assertSame('ASIN_PR1', $selection->source_product_identifier);
        $this->assertSame(8.5, $selection->beastie_score_as_published);
        $this->assertSame(4.4, $selection->public_rating_as_published);
        $this->assertSame(1200, $selection->public_rating_count_as_published);
        $this->assertSame('Strong', $selection->public_signal_as_published);

        // Authored values are validated, never replaced.
        $this->assertSame('Best overall', $selection->superlative);
        $this->assertSame('Scored highest on the published method.', $selection->selection_reason);
    }

    public function test_it_stamps_the_durable_video_consistency_record(): void
    {
        $edition = $this->draft($this->subject());

        $published = (new BestForPublisher())->publish($edition, $this->confirmations());

        $this->assertNotNull($published->video_consistency_verified_at);
    }

    public function test_publication_makes_the_edition_current(): void
    {
        $subject = $this->subject();
        $published = (new BestForPublisher())->publish($this->draft($subject), $this->confirmations());

        $this->assertSame($published->id, $subject->fresh()->currentEdition->id);
    }

    public function test_republishing_moves_the_pointer_and_leaves_exactly_one_current_edition(): void
    {
        $subject = $this->subject();
        $first = (new BestForPublisher())->publish($this->draft($subject), $this->confirmations());

        $second = BestForEdition::create([
            'subject_id' => $subject->id,
            'category_name_as_published' => 'x',
            'year_as_published' => 1999,
            'methodology_text' => 'A second edition.',
            'video_asset_id' => 'vimeo:200002',
        ]);
        $superlatives = ['Best overall', 'Best value', 'Best for large dogs', 'Best budget', 'Best tech'];
        foreach ($this->reviewIds as $i => $reviewId) {
            BestForEditionSelection::create([
                'edition_id' => $second->id,
                'position' => $i + 1,
                'published_review_id' => $reviewId,
                'review_slug_as_published' => 'x',
                'product_name_as_published' => 'x',
                'superlative' => $superlatives[$i],
                'selection_reason' => 'x',
                'beastie_score_as_published' => 0.0,
                'public_rating_as_published' => 0.0,
                'public_rating_count_as_published' => 0,
                'public_signal_as_published' => 'Limited',
            ]);
        }

        $secondPublished = (new BestForPublisher())->publish($second, $this->confirmations());

        $this->assertNull($first->fresh()->current_for_subject_id);
        $this->assertSame($secondPublished->id, $subject->fresh()->currentEdition->id);
        $this->assertSame(1, BestForEdition::whereNotNull('current_for_subject_id')->count());
    }

    public function test_editorial_fields_are_immutable_once_published(): void
    {
        $published = (new BestForPublisher())->publish($this->draft($this->subject()), $this->confirmations());

        $this->expectException(DomainException::class);

        $published->methodology_text = 'rewritten after publication';
        $published->save();
    }

    public function test_severe_unpublication_removes_and_leaves_the_subject_with_no_current_edition(): void
    {
        $subject = $this->subject();
        $published = (new BestForPublisher())->publish($this->draft($subject), $this->confirmations());

        $removed = (new BestForPublisher())->unpublishSevere($published, 'Safety recall on the top pick.');

        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $removed->publication_state);
        $this->assertSame(BestForEdition::RECOMMENDATION_SUSPENDED, $removed->recommendation_state);
        $this->assertSame(BestForEdition::INDEXING_NOINDEX, $removed->indexing_state);

        // No automatic fallback: the subject simply has no current edition.
        $this->assertNull($subject->fresh()->currentEdition);
        $this->assertFalse($subject->fresh()->hasCurrentEdition());
    }

    public function test_severe_unpublication_does_not_touch_the_editorial_snapshot(): void
    {
        $subject = $this->subject();
        $published = (new BestForPublisher())->publish($this->draft($subject), $this->confirmations());
        $before = $published->selections()->get()->map->only([
            'published_review_id', 'product_name_as_published', 'beastie_score_as_published',
            'public_rating_count_as_published', 'public_signal_as_published',
        ])->toArray();

        (new BestForPublisher())->unpublishSevere($published, 'Safety recall.');

        $after = $published->fresh()->selections()->get()->map->only([
            'published_review_id', 'product_name_as_published', 'beastie_score_as_published',
            'public_rating_count_as_published', 'public_signal_as_published',
        ])->toArray();

        $this->assertSame($before, $after);
    }
}
