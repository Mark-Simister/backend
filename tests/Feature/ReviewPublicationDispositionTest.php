<?php

namespace Tests\Feature;

use App\Models\ReviewPublicationDisposition;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Disposition semantics — chiefly, the things that are NOT a withdrawal.
 *
 * The failure this table is shaped to prevent is a product going temporarily
 * out of stock and, through a column default, reading as a material
 * withdrawal. So a withdrawal is one thing only: `withdrawn_at` being set.
 * "Material" is derived when a real withdrawal has not yet been classified and
 * confirmed — fail-safe, and visibly pending rather than quietly decided.
 */
class ReviewPublicationDispositionTest extends TestCase
{
    use RefreshDatabase;

    private function disposition(string $id, array $attributes = []): ReviewPublicationDisposition
    {
        return ReviewPublicationDisposition::create(
            array_merge(['published_review_id' => $id], $attributes)
        )->refresh();
    }

    public function test_a_fresh_row_is_valid_available_and_not_withdrawn(): void
    {
        $disposition = $this->disposition('PR-1');

        $this->assertFalse($disposition->isWithdrawn());
        $this->assertNull($disposition->effectiveWithdrawalClass());
        $this->assertNull($disposition->withdrawal_class);
        $this->assertFalse($disposition->classification_confirmed);
        $this->assertSame(ReviewPublicationDisposition::VALIDITY_VALID, $disposition->validity_state);
        $this->assertSame(ReviewPublicationDisposition::AVAILABILITY_AVAILABLE, $disposition->availability_state);
    }

    public function test_temporary_unavailability_is_not_a_withdrawal(): void
    {
        $disposition = $this->disposition('PR-2', [
            'availability_state' => ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE,
        ]);

        $this->assertTrue($disposition->isTemporarilyUnavailable());

        // The whole point: none of this reads as a withdrawal of any class.
        $this->assertFalse($disposition->isWithdrawn());
        $this->assertNull($disposition->effectiveWithdrawalClass());
        $this->assertFalse($disposition->isEffectivelyMaterial());
        $this->assertFalse($disposition->isPendingDisposition());
        $this->assertNull($disposition->withdrawal_class);
    }

    public function test_material_invalidity_alone_is_not_a_withdrawal(): void
    {
        $disposition = $this->disposition('PR-3', [
            'validity_state' => ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID,
        ]);

        $this->assertTrue($disposition->isMateriallyInvalid());
        $this->assertFalse($disposition->isWithdrawn());
        $this->assertNull($disposition->effectiveWithdrawalClass());
    }

    public function test_a_withdrawal_with_no_class_is_treated_as_material_pending_disposition(): void
    {
        $disposition = $this->disposition('PR-4', ['withdrawn_at' => now()]);

        $this->assertTrue($disposition->isWithdrawn());
        $this->assertNull($disposition->withdrawal_class);           // nothing was written by a default
        $this->assertSame(
            ReviewPublicationDisposition::CLASS_MATERIAL,
            $disposition->effectiveWithdrawalClass()
        );
        $this->assertTrue($disposition->isEffectivelyMaterial());
        $this->assertTrue($disposition->isPendingDisposition());
    }

    public function test_an_unconfirmed_class_is_still_treated_as_material(): void
    {
        $disposition = $this->disposition('PR-5', [
            'withdrawn_at' => now(),
            'withdrawal_class' => ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL,
            'classification_confirmed' => false,
        ]);

        $this->assertSame(
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL,
            $disposition->withdrawal_class
        );
        $this->assertSame(
            ReviewPublicationDisposition::CLASS_MATERIAL,
            $disposition->effectiveWithdrawalClass()
        );
        $this->assertTrue($disposition->isPendingDisposition());
    }

    #[DataProvider('confirmedClasses')]
    public function test_a_confirmed_class_governs(string $class): void
    {
        $disposition = $this->disposition('PR-' . $class, [
            'withdrawn_at' => now(),
            'withdrawal_class' => $class,
            'classification_confirmed' => true,
            'withdrawal_reason' => 'Confirmed by the editor.',
            'disposition_at' => now(),
        ]);

        $this->assertSame($class, $disposition->effectiveWithdrawalClass());
        $this->assertFalse($disposition->isPendingDisposition());
        $this->assertSame(
            $class === ReviewPublicationDisposition::CLASS_MATERIAL,
            $disposition->isEffectivelyMaterial()
        );
    }

    public static function confirmedClasses(): array
    {
        return [
            'ordinary editorial' => [ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL],
            'material' => [ReviewPublicationDisposition::CLASS_MATERIAL],
            'temporary technical' => [ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL],
        ];
    }

    public function test_validity_and_availability_change_independently(): void
    {
        $disposition = $this->disposition('PR-6');

        $disposition->availability_state = ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE;
        $disposition->save();
        $disposition->refresh();

        $this->assertTrue($disposition->isTemporarilyUnavailable());
        $this->assertFalse($disposition->isMateriallyInvalid());
        $this->assertFalse($disposition->isWithdrawn());

        $disposition->validity_state = ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID;
        $disposition->availability_state = ReviewPublicationDisposition::AVAILABILITY_AVAILABLE;
        $disposition->save();
        $disposition->refresh();

        $this->assertTrue($disposition->isMateriallyInvalid());
        $this->assertFalse($disposition->isTemporarilyUnavailable());
        $this->assertFalse($disposition->isWithdrawn());
    }

    public function test_availability_can_be_restored_without_touching_a_withdrawal(): void
    {
        $disposition = $this->disposition('PR-7', [
            'withdrawn_at' => now(),
            'withdrawal_class' => ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL,
            'classification_confirmed' => true,
            'availability_state' => ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE,
        ]);

        $disposition->availability_state = ReviewPublicationDisposition::AVAILABILITY_AVAILABLE;
        $disposition->save();
        $disposition->refresh();

        $this->assertTrue($disposition->isWithdrawn());
        $this->assertSame(
            ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL,
            $disposition->effectiveWithdrawalClass()
        );
    }

    public function test_a_withdrawal_class_cannot_exist_without_a_withdrawal_event(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires a withdrawal event');

        ReviewPublicationDisposition::create([
            'published_review_id' => 'PR-8',
            'withdrawal_class' => ReviewPublicationDisposition::CLASS_MATERIAL,
        ]);
    }

    public function test_a_classification_cannot_be_confirmed_without_a_class(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot be confirmed');

        ReviewPublicationDisposition::create([
            'published_review_id' => 'PR-9',
            'withdrawn_at' => now(),
            'classification_confirmed' => true,
        ]);
    }

    public function test_the_review_key_identifies_exactly_one_disposition(): void
    {
        $this->disposition('PR-10', [
            'availability_state' => ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE,
        ]);

        $stored = ReviewPublicationDisposition::findOrFail('PR-10');

        $this->assertSame('PR-10', $stored->getKey());
        $this->assertSame(1, ReviewPublicationDisposition::count());
    }
}
