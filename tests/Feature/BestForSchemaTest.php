<?php

namespace Tests\Feature;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Best For invariants that the database itself refuses to break.
 *
 * These are the ones worth putting in schema rather than code, because they
 * hold even if a future service, seeder or console command forgets them. The
 * rules a unique index cannot express live in BestForImmutabilityTest.
 */
class BestForSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function subject(string $categoryName = 'Dog Beds', int $year = 2026): BestForSubject
    {
        $category = Category::create(['name' => $categoryName]);

        return BestForSubject::create([
            'category_id' => $category->id,
            'year' => $year,
        ]);
    }

    private function draftEdition(BestForSubject $subject, int $sequence = 1): BestForEdition
    {
        return BestForEdition::create([
            'public_edition_key' => 'best-for-' . $subject->id . '-' . $sequence,
            'subject_id' => $subject->id,
            'edition_sequence' => $sequence,
            'category_name_as_published' => 'Dog Beds',
            'year_as_published' => 2026,
            'methodology_text' => 'Five products, scored on the published BeastieScore method.',
            'video_asset_id' => 'vimeo:10000' . $sequence,
        ]);
    }

    private function publishedEdition(BestForSubject $subject, int $sequence = 1): BestForEdition
    {
        $edition = $this->draftEdition($subject, $sequence);
        $edition->published_at = now();
        $edition->save();

        return $edition->refresh();
    }

    private function selection(BestForEdition $edition, int $position, array $overrides = []): BestForEditionSelection
    {
        return BestForEditionSelection::create(array_merge([
            'edition_id' => $edition->id,
            'position' => $position,
            'published_review_id' => 'PR-' . $edition->id . '-' . $position,
            'review_slug_as_published' => 'review-' . $edition->id . '-' . $position,
            'product_name_as_published' => 'Product ' . $position,
            'superlative' => 'Best overall',
            'selection_reason' => 'Scored highest on the published method.',
            'beastie_score_as_published' => 8.5,
            'public_rating_as_published' => 4.4,
            'public_rating_count_as_published' => 1200,
            'public_signal_as_published' => 'Strong',
        ], $overrides));
    }

    public function test_a_category_and_year_identify_exactly_one_subject(): void
    {
        $subject = $this->subject('Dog Beds', 2026);

        $this->expectException(QueryException::class);

        BestForSubject::create([
            'category_id' => $subject->category_id,
            'year' => 2026,
        ]);
    }

    public function test_the_same_category_may_have_a_subject_per_year(): void
    {
        $subject = $this->subject('Dog Beds', 2026);

        $next = BestForSubject::create([
            'category_id' => $subject->category_id,
            'year' => 2027,
        ]);

        $this->assertNotSame($subject->id, $next->id);
        $this->assertSame(2, BestForSubject::count());
    }

    public function test_edition_sequence_is_unique_within_a_subject(): void
    {
        $subject = $this->subject();
        $this->draftEdition($subject, 1);

        $this->expectException(QueryException::class);

        BestForEdition::create([
            'public_edition_key' => 'a-different-key',
            'subject_id' => $subject->id,
            'edition_sequence' => 1,      // the collision
            'category_name_as_published' => 'Dog Beds',
            'year_as_published' => 2026,
            'methodology_text' => 'Five products.',
            'video_asset_id' => 'vimeo:222222',
        ]);
    }

    public function test_the_public_edition_key_is_unique_across_every_subject(): void
    {
        $first = $this->subject('Dog Beds', 2026);
        $second = $this->subject('Cat Trees', 2026);

        $this->draftEdition($first, 1);

        $this->expectException(QueryException::class);

        BestForEdition::create([
            'public_edition_key' => 'best-for-' . $first->id . '-1',   // already taken
            'subject_id' => $second->id,
            'edition_sequence' => 1,
            'category_name_as_published' => 'Cat Trees',
            'year_as_published' => 2026,
            'methodology_text' => 'Five products.',
            'video_asset_id' => 'vimeo:333333',
        ]);
    }

    public function test_a_selection_position_is_unique_within_an_edition(): void
    {
        $edition = $this->draftEdition($this->subject());
        $this->selection($edition, 1);

        $this->expectException(QueryException::class);

        $this->selection($edition, 1, ['published_review_id' => 'PR-DIFFERENT']);
    }

    public function test_the_same_review_cannot_appear_twice_in_one_edition(): void
    {
        $edition = $this->draftEdition($this->subject());
        $this->selection($edition, 1, ['published_review_id' => 'PR-SHARED']);

        $this->expectException(QueryException::class);

        $this->selection($edition, 2, ['published_review_id' => 'PR-SHARED']);
    }

    public function test_the_same_review_may_appear_in_different_editions(): void
    {
        $first = $this->draftEdition($this->subject('Dog Beds', 2026), 1);
        $second = $this->draftEdition($this->subject('Cat Trees', 2026), 1);

        $this->selection($first, 1, ['published_review_id' => 'PR-SHARED']);
        $this->selection($second, 1, ['published_review_id' => 'PR-SHARED']);

        $this->assertSame(2, BestForEditionSelection::where('published_review_id', 'PR-SHARED')->count());
    }

    public function test_a_subject_can_have_at_most_one_current_edition(): void
    {
        $subject = $this->subject();
        $first = $this->publishedEdition($subject, 1);
        $second = $this->publishedEdition($subject, 2);

        $first->current_for_subject_id = $subject->id;
        $first->save();

        $second->current_for_subject_id = $subject->id;

        $this->expectException(QueryException::class);

        $second->save();
    }

    public function test_the_current_edition_can_be_replaced_by_clearing_the_previous_one(): void
    {
        $subject = $this->subject();
        $first = $this->publishedEdition($subject, 1);
        $second = $this->publishedEdition($subject, 2);

        $first->current_for_subject_id = $subject->id;
        $first->save();

        $first->current_for_subject_id = null;
        $first->save();

        $second->current_for_subject_id = $subject->id;
        $second->save();

        $this->assertSame($second->id, $subject->fresh()->currentEdition->id);
    }

    public function test_a_subject_may_have_no_current_edition(): void
    {
        $subject = $this->subject();
        $this->publishedEdition($subject, 1);

        $this->assertNull($subject->fresh()->currentEdition);
        $this->assertFalse($subject->fresh()->hasCurrentEdition());
    }

    public function test_two_subjects_may_each_have_their_own_current_edition(): void
    {
        $dogs = $this->subject('Dog Beds', 2026);
        $cats = $this->subject('Cat Trees', 2026);

        $dogEdition = $this->publishedEdition($dogs, 1);
        $catEdition = $this->publishedEdition($cats, 1);

        $dogEdition->current_for_subject_id = $dogs->id;
        $dogEdition->save();

        $catEdition->current_for_subject_id = $cats->id;
        $catEdition->save();

        $this->assertSame($dogEdition->id, $dogs->fresh()->currentEdition->id);
        $this->assertSame($catEdition->id, $cats->fresh()->currentEdition->id);
    }
}
