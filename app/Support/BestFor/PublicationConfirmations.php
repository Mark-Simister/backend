<?php

namespace App\Support\BestFor;

/**
 * The explicit human confirmations required to publish a Best For edition, for the
 * conditions that CANNOT be determined mechanically from current backend data.
 *
 * ### These are transient inputs. They are NOT an audit record.
 *
 * This object lives for the duration of one publication call and is then discarded.
 * Nothing here is persisted, and nothing here should ever be described, exported or
 * reported as evidence that a human confirmed anything. If publication-time
 * confirmations need to be auditable, that requires new columns and a separate
 * approved schema boundary — it is not something this object can be made to do by
 * reading it differently.
 *
 * The one exception is video-to-collection consistency, which DOES have an approved
 * durable home: BestForPublisher stamps `best_for_editions.video_consistency_verified_at`
 * when it publishes. That timestamp is the durable record; the boolean here is only the
 * instruction to stamp it.
 *
 * ### Fail closed
 *
 * Absence is never satisfaction. An empty object confirms nothing, and every condition
 * that needs a confirmation refuses publication until it receives one. `none()` exists
 * to make that explicit at call sites and in tests.
 */
final class PublicationConfirmations
{
    /**
     * @param  string[]  $notDivergenceHeld  published_review_id values a human has confirmed are NOT divergence-held.
     *                                       Required for every selection: no divergence data exists in this backend,
     *                                       and missing evidence must never read as "not held".
     * @param  string[]  $categoryConfirmed  published_review_id values a human has confirmed belong to the subject's
     *                                       category. Required only where category cannot be resolved deterministically.
     * @param  bool  $productDistinctnessConfirmed  a human has confirmed no two selections are the same product,
     *                                              where source identifiers cannot decide it.
     * @param  bool  $videoConsistencyConfirmed  a human has confirmed the curated video matches these five selections.
     * @param  int|null  $confirmedBy  users.id of the confirming human, recorded only on the durable video timestamp path.
     */
    public function __construct(
        public readonly array $notDivergenceHeld = [],
        public readonly array $categoryConfirmed = [],
        public readonly bool $productDistinctnessConfirmed = false,
        public readonly bool $videoConsistencyConfirmed = false,
        public readonly ?int $confirmedBy = null,
    ) {
    }

    /** Confirms nothing. Every gated condition will refuse. */
    public static function none(): self
    {
        return new self();
    }

    public function confirmsNotDivergenceHeld(string $publishedReviewId): bool
    {
        return in_array($publishedReviewId, $this->notDivergenceHeld, true);
    }

    public function confirmsCategory(string $publishedReviewId): bool
    {
        return in_array($publishedReviewId, $this->categoryConfirmed, true);
    }
}
