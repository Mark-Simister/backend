<?php

namespace Tests\Feature;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Support\BestFor\CurrentMembershipResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D2 — current active Best For membership on the public review page.
 *
 * The rule under test is a precedence rule, not a filter: for any one
 * category/year, exact host region wins, GLOBAL is the fallback, and the two
 * are NEVER shown together. Most of the risk lives in the negative cases, so
 * each excluding condition is proved on its own rather than in combination —
 * otherwise a single over-broad predicate could pass every test while hiding
 * that only one of the four edition states was actually being enforced.
 */
class BestForCurrentMembershipTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW = 'pr__vecomfy-orthopedic-bed';

    private function region(string $code): Region
    {
        return Region::firstOrCreate(
            ['region_code' => $code],
            ['region_name' => $code . ' region', 'currency' => 'AUD', 'is_active' => true]
        );
    }

    private function category(string $slug): Category
    {
        return Category::firstOrCreate(['slug' => $slug], ['name' => ucfirst(str_replace('-', ' ', $slug))]);
    }

    private function subject(string $categorySlug, int $year, string $regionCode): BestForSubject
    {
        return BestForSubject::create([
            'category_id' => $this->category($categorySlug)->id,
            'year' => $year,
            'region_id' => $this->region($regionCode)->id,
        ]);
    }

    /**
     * Selections cannot be added to a published edition — the model refuses it —
     * so every edition is built as a draft, populated, and only then published.
     * That is also the real publication order, so the fixture exercises the same
     * path production would.
     */
    private function publishedEditionWithReview(
        BestForSubject $subject,
        string $publishedReviewId,
        array $editionOverrides = [],
        array $selectionOverrides = [],
        bool $makeCurrent = true,
        bool $publish = true
    ): BestForEdition {
        $edition = BestForEdition::create(array_merge([
            'subject_id' => $subject->id,
            'category_name_as_published' => $subject->category->name,
            'year_as_published' => $subject->year,
            'methodology_text' => 'How this edition was chosen.',
            'video_asset_id' => 'vimeo-000',
        ], $editionOverrides));

        BestForEditionSelection::create(array_merge([
            'edition_id' => $edition->id,
            'position' => 1,
            'published_review_id' => $publishedReviewId,
            'review_slug_as_published' => 'vecomfy-orthopedic-bed',
            'product_name_as_published' => 'Vecomfy Orthopedic Bed',
            'superlative' => 'Best for Senior Dogs',
            'selection_reason' => 'Highest owner-reported joint relief in the set.',
            'beastie_score_as_published' => 4.60,
            'public_rating_as_published' => 4.50,
            'public_rating_count_as_published' => 1200,
            'public_signal_as_published' => 'Strong',
        ], $selectionOverrides));

        if ($publish) {
            $edition->published_at = now();
            if ($makeCurrent) {
                $edition->current_for_subject_id = $subject->id;
            }
            $edition->save();
        }

        return $edition->refresh();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolve(?string $hostRegionCode, ?string $reviewId = self::REVIEW): array
    {
        return (new CurrentMembershipResolver())->forReview($reviewId, $hostRegionCode)->all();
    }

    // ------------------------------------------------------------ zero results

    public function test_a_review_with_no_best_for_membership_returns_nothing(): void
    {
        $this->assertSame([], $this->resolve('AU'));
    }

    public function test_a_blank_published_review_id_returns_nothing_without_querying(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);

        $this->assertSame([], $this->resolve('AU', null));
        $this->assertSame([], $this->resolve('AU', ''));
    }

    // ------------------------------------------------------- region precedence

    public function test_an_exact_host_region_membership_is_returned(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);

        $found = $this->resolve('AU');

        $this->assertCount(1, $found);
        $this->assertSame('AU', $found[0]['region_code']);
        $this->assertFalse($found[0]['is_global_fallback']);
        $this->assertSame('Best for Senior Dogs', $found[0]['superlative']);
    }

    public function test_global_is_used_as_the_fallback_when_no_exact_region_subject_exists(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'GLOBAL'), self::REVIEW);

        $found = $this->resolve('AU');

        $this->assertCount(1, $found);
        $this->assertSame('GLOBAL', $found[0]['region_code']);
        $this->assertTrue($found[0]['is_global_fallback']);
    }

    public function test_an_exact_region_subject_suppresses_global_for_the_same_category_and_year(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'GLOBAL'), self::REVIEW);

        $found = $this->resolve('AU');

        $this->assertCount(1, $found, 'exact and GLOBAL must never be unioned for one category/year');
        $this->assertSame('AU', $found[0]['region_code']);
    }

    public function test_a_null_host_region_falls_through_to_global(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'GLOBAL'), self::REVIEW);

        $found = $this->resolve(null);

        $this->assertCount(1, $found);
        $this->assertSame('GLOBAL', $found[0]['region_code']);
    }

    public function test_a_host_region_with_neither_an_exact_nor_a_global_subject_returns_nothing(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);

        $this->assertSame([], $this->resolve('US'));
    }

    public function test_exact_and_global_may_both_appear_for_different_category_year_groups(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $this->publishedEditionWithReview($this->subject('dog-toys', 2025, 'GLOBAL'), self::REVIEW, [], [
            'superlative' => 'Best for Heavy Chewers',
        ]);

        $found = $this->resolve('AU');

        $this->assertCount(2, $found);
        $this->assertEqualsCanonicalizing(['AU', 'GLOBAL'], array_column($found, 'region_code'));
        $this->assertEqualsCanonicalizing([2026, 2025], array_column($found, 'year'));
    }

    public function test_multiple_legitimate_memberships_across_distinct_groups_are_all_returned(): void
    {
        $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $this->publishedEditionWithReview($this->subject('dog-beds', 2025, 'AU'), self::REVIEW);
        $this->publishedEditionWithReview($this->subject('dog-toys', 2026, 'AU'), self::REVIEW);

        $this->assertCount(3, $this->resolve('AU'));
    }

    // ----------------------------------------------------- edition-state gates

    public function test_a_non_current_edition_is_excluded(): void
    {
        $this->publishedEditionWithReview(
            $this->subject('dog-beds', 2026, 'AU'),
            self::REVIEW,
            [],
            [],
            makeCurrent: false
        );

        $this->assertSame([], $this->resolve('AU'));
    }

    public function test_an_unpublished_draft_edition_is_excluded(): void
    {
        $this->publishedEditionWithReview(
            $this->subject('dog-beds', 2026, 'AU'),
            self::REVIEW,
            [],
            [],
            makeCurrent: false,
            publish: false
        );

        $this->assertSame([], $this->resolve('AU'));
    }

    public function test_a_removed_publication_state_is_excluded(): void
    {
        $edition = $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $edition->update(['publication_state' => BestForEdition::PUBLICATION_REMOVED]);

        $this->assertSame([], $this->resolve('AU'));
    }

    public function test_a_suspended_recommendation_state_is_excluded(): void
    {
        $edition = $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $edition->update(['recommendation_state' => BestForEdition::RECOMMENDATION_SUSPENDED]);

        $this->assertSame([], $this->resolve('AU'));
    }

    public function test_noindex_alone_does_not_remove_an_inbound_membership(): void
    {
        $edition = $this->publishedEditionWithReview($this->subject('dog-beds', 2026, 'AU'), self::REVIEW);
        $edition->update(['indexing_state' => BestForEdition::INDEXING_NOINDEX]);

        $found = $this->resolve('AU');

        $this->assertCount(1, $found, 'indexing_state governs the edition page, not inbound membership');
        $this->assertSame('AU', $found[0]['region_code']);
    }

    // --------------------------------------------------------- no numeric rank

    public function test_no_numeric_rank_is_exposed_by_the_resolver(): void
    {
        $this->publishedEditionWithReview(
            $this->subject('dog-beds', 2026, 'AU'),
            self::REVIEW,
            [],
            ['position' => 4]
        );

        $found = $this->resolve('AU');

        $this->assertCount(1, $found);
        $this->assertArrayNotHasKey('position', $found[0]);
        $this->assertArrayNotHasKey('rank', $found[0]);
        $this->assertNotContains(4, array_values($found[0]), 'the approved position must not leak in any field');
    }

    public function test_the_rendered_page_shows_the_membership_without_a_numeric_rank(): void
    {
        config([
            'reviews.rendering_enabled' => true,
            'reviews.public_statuses' => ['published'],
        ]);

        PublishedReviewPayload::create([
            'published_review_id' => self::REVIEW,
            'review_slug' => 'vecomfy-orthopedic-bed',
            'publish_status' => 'published',
            'h1' => 'Vecomfy Orthopedic Bed',
            'review_page_json' => ['hero' => ['title' => 'Vecomfy Orthopedic Bed']],
        ]);

        $this->publishedEditionWithReview(
            $this->subject('dog-beds', 2026, 'GLOBAL'),
            self::REVIEW,
            [],
            ['position' => 3]
        );

        $response = $this->get('/review/vecomfy-orthopedic-bed');

        $response->assertOk();
        $response->assertSee('Currently Recommended In');
        $response->assertSee('Best for Senior Dogs');
        $response->assertSee('2026');
        $response->assertDontSee('#3');
    }

    public function test_a_page_with_no_membership_renders_no_best_for_section(): void
    {
        config([
            'reviews.rendering_enabled' => true,
            'reviews.public_statuses' => ['published'],
        ]);

        PublishedReviewPayload::create([
            'published_review_id' => 'pr__no-membership',
            'review_slug' => 'no-membership',
            'publish_status' => 'published',
            'h1' => 'No Membership',
            'review_page_json' => ['hero' => ['title' => 'No Membership']],
        ]);

        $response = $this->get('/review/no-membership');

        $response->assertOk();
        $response->assertDontSee('Currently Recommended In');
    }
}
