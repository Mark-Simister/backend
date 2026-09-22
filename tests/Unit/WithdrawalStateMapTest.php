<?php

namespace Tests\Unit;

use App\Models\BestForEdition;
use App\Models\ReviewPublicationDisposition;
use App\Support\BestFor\WithdrawalStateMap;
use PHPUnit\Framework\TestCase;

/**
 * The mapping is pure, so it is tested pure — no database, no models persisted.
 *
 * The important assertion is the empty array. "Change nothing" and "re-assert live,
 * active, indexable" look the same from outside but are not: only the first leaves an
 * edition a human has deliberately suspended for some other reason alone.
 */
class WithdrawalStateMapTest extends TestCase
{
    public function test_no_withdrawal_event_changes_nothing(): void
    {
        $this->assertSame([], WithdrawalStateMap::forEffectiveClass(null));
    }

    public function test_ordinary_editorial_leaves_the_edition_untouched(): void
    {
        // Option A. The withdrawn entry renders as historical; the collection keeps
        // recommending. Ordinary editorial withdrawal is not a truth problem.
        $this->assertSame([], WithdrawalStateMap::forEffectiveClass(
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL
        ));
    }

    public function test_temporary_technical_leaves_the_edition_untouched(): void
    {
        $this->assertSame([], WithdrawalStateMap::forEffectiveClass(
            ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL
        ));
    }

    public function test_material_suspends_recommendation_and_noindexes(): void
    {
        $this->assertSame(
            [
                'recommendation_state' => BestForEdition::RECOMMENDATION_SUSPENDED,
                'indexing_state' => BestForEdition::INDEXING_NOINDEX,
            ],
            WithdrawalStateMap::forEffectiveClass(ReviewPublicationDisposition::CLASS_MATERIAL)
        );
    }

    public function test_material_does_not_remove_publication_without_explicit_severity(): void
    {
        $this->assertArrayNotHasKey(
            'publication_state',
            WithdrawalStateMap::forEffectiveClass(ReviewPublicationDisposition::CLASS_MATERIAL)
        );
    }

    public function test_severe_material_also_removes_publication(): void
    {
        $map = WithdrawalStateMap::forEffectiveClass(ReviewPublicationDisposition::CLASS_MATERIAL, true);

        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $map['publication_state']);
        $this->assertSame(BestForEdition::RECOMMENDATION_SUSPENDED, $map['recommendation_state']);
        $this->assertSame(BestForEdition::INDEXING_NOINDEX, $map['indexing_state']);
    }

    public function test_severity_cannot_escalate_a_non_material_class(): void
    {
        $this->assertSame([], WithdrawalStateMap::forEffectiveClass(
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL,
            true
        ));
    }

    public function test_an_absent_disposition_requires_no_material_treatment(): void
    {
        $this->assertFalse(WithdrawalStateMap::requiresMaterialTreatment(null));
    }

    public function test_an_edition_with_no_blocking_selection_carries_no_restriction(): void
    {
        $this->assertSame([], WithdrawalStateMap::forSelections([]));
        $this->assertSame([], WithdrawalStateMap::forSelections([null, null, null, null, null]));
    }

    // forDisposition(), forSelections() over real dispositions, and the
    // unconfirmed-class-reads-as-material rule inherited from
    // ReviewPublicationDisposition — is covered in ReviewDispositionServiceTest, where the
    // application is booted. Instantiating an Eloquent model here would need a database
    // connection for date casting, which would make this test not a unit test.
}
