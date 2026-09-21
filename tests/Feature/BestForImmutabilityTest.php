<?php

namespace Tests\Feature;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publication is the freeze.
 *
 * Everything here is a rule a unique index cannot express, so it is enforced
 * by the models: a published edition's editorial record cannot be rewritten,
 * its five selections cannot be changed, added to or removed from, and a
 * current-edition pointer cannot name the wrong subject or a draft.
 *
 * The point is not that a careful caller avoids these writes. It is that a
 * careless one cannot make them.
 */
class BestForImmutabilityTest extends TestCase
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

    private function selection(BestForEdition $edition, int $position = 1, array $overrides = []): BestForEditionSelection
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

    // ---- Current-edition guard (the rules UNIQUE cannot express) ----------

    public function test_a_draft_edition_cannot_be_marked_current(): void
    {
        $subject = $this->subject();
        $draft = $this->draftEdition($subject);

        $draft->current_for_subject_id = $subject->id;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('draft');

        $draft->save();
    }

    public function test_an_edition_cannot_be_marked_current_for_another_subject(): void
    {
        $dogs = $this->subject('Dog Beds', 2026);
        $cats = $this->subject('Cat Trees', 2026);

        $dogEdition = $this->publishedEdition($dogs, 1);
        $dogEdition->current_for_subject_id = $cats->id;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('its own subject');

        $dogEdition->save();
    }

    public function test_an_edition_can_be_published_and_marked_current_in_one_save(): void
    {
        $subject = $this->subject();
        $edition = $this->draftEdition($subject);

        $edition->published_at = now();
        $edition->current_for_subject_id = $subject->id;
        $edition->save();

        $this->assertSame($edition->id, $subject->fresh()->currentEdition->id);
    }

    // ---- Edition immutability ---------------------------------------------

    public function test_a_draft_edition_is_freely_editable(): void
    {
        $edition = $this->draftEdition($this->subject());

        $edition->methodology_text = 'A revised method, still in draft.';
        $edition->video_asset_id = 'vimeo:rewritten';
        $edition->save();

        $this->assertSame('A revised method, still in draft.', $edition->fresh()->methodology_text);
        $this->assertSame('vimeo:rewritten', $edition->fresh()->video_asset_id);
    }

    public function test_a_published_edition_refuses_every_editorial_change(): void
    {
        $subject = $this->subject('Dog Beds', 2026);
        $other = $this->subject('Cat Trees', 2026);
        $edition = $this->publishedEdition($subject);

        $attempts = [
            'public_edition_key' => 'a-rewritten-key',
            'subject_id' => $other->id,
            'edition_sequence' => 9,
            'category_name_as_published' => 'Renamed Later',
            'year_as_published' => 2030,
            'published_at' => now()->addDay(),
            'methodology_text' => 'A rewritten method.',
            'methodology_version' => 'v2',
            'video_asset_id' => 'vimeo:replaced',
        ];

        foreach ($attempts as $field => $value) {
            $fresh = BestForEdition::findOrFail($edition->id);
            $fresh->{$field} = $value;

            try {
                $fresh->save();
                $this->fail("Expected \"{$field}\" to be immutable once the edition is published.");
            } catch (DomainException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }

        // Nothing leaked through to the row.
        $stored = BestForEdition::findOrFail($edition->id);
        $this->assertSame($edition->public_edition_key, $stored->public_edition_key);
        $this->assertSame($subject->id, $stored->subject_id);
        $this->assertSame('Dog Beds', $stored->category_name_as_published);
        $this->assertSame(2026, $stored->year_as_published);
        $this->assertSame('vimeo:100001', $stored->video_asset_id);
    }

    public function test_a_published_edition_still_accepts_operational_changes(): void
    {
        $editor = User::factory()->create();
        $edition = $this->publishedEdition($this->subject());

        $edition->publication_state = BestForEdition::PUBLICATION_REMOVED;
        $edition->recommendation_state = BestForEdition::RECOMMENDATION_SUSPENDED;
        $edition->indexing_state = BestForEdition::INDEXING_NOINDEX;
        $edition->operational_reason = 'Manufacturer recall on the top pick.';
        $edition->operational_at = now();
        $edition->operational_by = $editor->id;
        $edition->video_consistency_verified_at = now();
        $edition->save();

        $stored = $edition->fresh();
        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $stored->publication_state);
        $this->assertSame(BestForEdition::RECOMMENDATION_SUSPENDED, $stored->recommendation_state);
        $this->assertSame(BestForEdition::INDEXING_NOINDEX, $stored->indexing_state);
        $this->assertSame('Manufacturer recall on the top pick.', $stored->operational_reason);
        $this->assertSame($editor->id, $stored->operational_by);
        $this->assertNotNull($stored->operational_at);
        $this->assertNotNull($stored->video_consistency_verified_at);
    }

    public function test_a_published_edition_cannot_be_deleted(): void
    {
        $edition = $this->publishedEdition($this->subject());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $edition->delete();
    }

    public function test_a_draft_edition_can_be_deleted(): void
    {
        $edition = $this->draftEdition($this->subject());
        $this->selection($edition, 1);

        $edition->delete();

        $this->assertSame(0, BestForEdition::count());
        $this->assertSame(0, BestForEditionSelection::count());
    }

    // ---- Selection immutability -------------------------------------------

    public function test_a_draft_editions_selections_are_freely_editable(): void
    {
        $edition = $this->draftEdition($this->subject());
        $selection = $this->selection($edition, 1);

        $selection->product_name_as_published = 'A different product';
        $selection->save();

        $this->assertSame('A different product', $selection->fresh()->product_name_as_published);

        $selection->delete();
        $this->assertSame(0, BestForEditionSelection::count());
    }

    public function test_a_published_editions_selection_refuses_every_editorial_change(): void
    {
        $edition = $this->draftEdition($this->subject());
        $selection = $this->selection($edition, 1);

        $edition->published_at = now();
        $edition->save();

        $attempts = [
            'position' => 4,
            'published_review_id' => 'PR-SWAPPED',
            'review_slug_as_published' => 'a-different-slug',
            'product_name_as_published' => 'A different product',
            'product_image_ref_as_published' => 'https://example.test/other.jpg',
            'source_product_identifier' => 'B00DIFFERENT',
            'superlative' => 'Best value',
            'selection_reason' => 'A rewritten reason.',
            'beastie_score_as_published' => 9.1,
            'public_rating_as_published' => 4.9,
            'public_rating_count_as_published' => 99999,
            'public_signal_as_published' => 'Very strong',
        ];

        foreach ($attempts as $field => $value) {
            $fresh = BestForEditionSelection::findOrFail($selection->id);
            $fresh->{$field} = $value;

            try {
                $fresh->save();
                $this->fail("Expected selection \"{$field}\" to be immutable once its edition is published.");
            } catch (DomainException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }

        $stored = BestForEditionSelection::findOrFail($selection->id);
        $this->assertSame('Product 1', $stored->product_name_as_published);
        $this->assertSame(1, $stored->position);
        $this->assertSame('Strong', $stored->public_signal_as_published);
    }

    public function test_a_selection_cannot_be_added_to_a_published_edition(): void
    {
        $edition = $this->publishedEdition($this->subject());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot add a selection');

        $this->selection($edition, 1);
    }

    public function test_a_selection_cannot_be_deleted_from_a_published_edition(): void
    {
        $edition = $this->draftEdition($this->subject());
        $selection = $this->selection($edition, 1);

        $edition->published_at = now();
        $edition->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot delete a selection');

        $selection->delete();
    }

    public function test_a_selection_cannot_be_moved_into_a_published_edition(): void
    {
        $subject = $this->subject();
        $published = $this->publishedEdition($subject, 1);
        $draft = $this->draftEdition($subject, 2);
        $selection = $this->selection($draft, 1);

        $selection->edition_id = $published->id;

        $this->expectException(DomainException::class);

        $selection->save();
    }

    public function test_the_guard_is_not_bypassed_by_force_filling(): void
    {
        $edition = $this->publishedEdition($this->subject());

        $this->expectException(DomainException::class);

        $edition->forceFill(['methodology_text' => 'Smuggled in.'])->save();
    }
}
