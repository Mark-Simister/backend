<?php

namespace App\Services;

use App\Exceptions\BestForPublishGateException;
use App\Models\BestForEdition;
use App\Support\BestFor\BestForPublicationGate;
use App\Support\BestFor\PublicationConfirmations;
use App\Support\BestFor\SelectionSnapshotBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of Best For publication.
 *
 * Publication is one committed unit: gate, then copy every derived value from its
 * authoritative source, then freeze, then move the current-edition pointer. If any part
 * fails the whole thing rolls back, so an edition is never left half-published with a
 * half-truthful snapshot.
 *
 * ### The snapshot is copied, not trusted
 *
 * Derived "as published" values are OVERWRITTEN from source inside the transaction. A
 * draft value is never carried through on the assumption it is still correct — a score
 * or ratings count moving between drafting and publishing is normal, and the snapshot
 * must record what was true at the publication boundary. Editorially authored values —
 * methodology text, superlative, selection reason — are validated by the gate and left
 * exactly as authored.
 *
 * Order matters: selections are rewritten while the edition is still a draft, because
 * BestForEditionSelection refuses every edit once its parent is published. The edition's
 * own freeze happens last, in a single save.
 *
 * ### No automatic fallback
 *
 * When an edition stops being current, the subject is simply left with no current
 * edition. An older edition is never promoted back — a replaced edition was replaced.
 */
final class BestForPublisher
{
    private BestForPublicationGate $gate;

    private SelectionSnapshotBuilder $snapshots;

    private ReviewDispositionService $dispositions;

    public function __construct(
        ?BestForPublicationGate $gate = null,
        ?SelectionSnapshotBuilder $snapshots = null,
        ?ReviewDispositionService $dispositions = null
    ) {
        $this->snapshots = $snapshots ?? new SelectionSnapshotBuilder();
        $this->gate = $gate ?? new BestForPublicationGate($this->snapshots);
        $this->dispositions = $dispositions ?? new ReviewDispositionService();
    }

    /**
     * @throws BestForPublishGateException when the edition may not be published
     */
    public function publish(
        BestForEdition $edition,
        PublicationConfirmations $confirmations,
        bool $makeCurrent = true
    ): BestForEdition {
        return DB::transaction(function () use ($edition, $confirmations, $makeCurrent) {
            $errors = $this->gate->errorsFor($edition, $confirmations);

            if ($errors !== []) {
                throw BestForPublishGateException::fromGateErrors($errors);
            }

            $subject = $edition->subject;

            // 1. Rewrite every selection snapshot from source. Still a draft here, so the
            //    selection immutability guard permits it.
            foreach ($edition->selections()->get() as $selection) {
                $snapshot = $this->snapshots->forSelection((string) $selection->published_review_id);
                $selection->forceFill($snapshot['values'])->save();
            }

            // 2. Rewrite the edition's derived values and freeze, in ONE save. The model
            //    guard allows this because published_at is still null in the database.
            $edition->forceFill($this->snapshots->forEdition($subject)['values']);
            $edition->published_at = now();

            // The durable record that a human affirmed video-to-collection consistency.
            // The confirmation object itself is transient and proves nothing on its own.
            $edition->video_consistency_verified_at = now();

            if ($confirmations->confirmedBy !== null) {
                $edition->operational_by = $confirmations->confirmedBy;
                $edition->operational_at = now();
            }

            if ($makeCurrent) {
                $this->releaseCurrentEditionOf((int) $subject->id, $edition);
                $edition->current_for_subject_id = $subject->id;
            }

            $edition->save();

            // Persist the effective operational state a consumer will read. The gate has
            // already refused any blocked selection, so this normally settles to
            // active/indexable — but it is computed, not assumed.
            $this->dispositions->recomputeOperationalState($edition);

            return $edition->refresh();
        });
    }

    /**
     * Severe unpublication: the only path to publication_state = removed. Never inferred —
     * it is an explicit human severity judgement. The subject is left with no current
     * edition; nothing is promoted in its place.
     *
     * `recommendation_state` and `indexing_state` are not set here. Recomputation derives
     * them from the removed publication state, so they cannot drift out of agreement with
     * it on some later disposition change.
     */
    public function unpublishSevere(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return DB::transaction(function () use ($edition, $reason, $by) {
            $edition->publication_state = BestForEdition::PUBLICATION_REMOVED;
            $edition->current_for_subject_id = null;
            $edition->operational_reason = $reason;
            $edition->operational_at = now();
            $edition->operational_by = $by;
            $edition->save();

            $this->dispositions->recomputeOperationalState($edition, $reason, $by);

            return $edition->refresh();
        });
    }

    /**
     * Reverse a severe unpublication. Explicit and human-triggered: there is no automatic
     * path back, and recomputation never clears `removed` on its own.
     *
     * The edition is NOT made current again — no older edition automatically becomes the
     * subject's current recommendation. Republishing a current edition is a separate,
     * deliberate act. Manual holds are left exactly as they are, and recomputation decides
     * the resulting recommendation and indexing state, so an unresolved material
     * withdrawal keeps the edition suspended and noindexed even after the restore.
     */
    public function restoreFromSevereUnpublication(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return DB::transaction(function () use ($edition, $reason, $by) {
            $edition->publication_state = BestForEdition::PUBLICATION_LIVE;
            $edition->operational_reason = $reason;
            $edition->operational_at = now();
            $edition->operational_by = $by;
            $edition->save();

            $this->dispositions->recomputeOperationalState($edition, $reason, $by);

            return $edition->refresh();
        });
    }

    /** Clear whichever edition currently holds the subject's pointer, except $keep. */
    private function releaseCurrentEditionOf(int $subjectId, BestForEdition $keep): void
    {
        BestForEdition::query()
            ->where('current_for_subject_id', $subjectId)
            ->when($keep->exists, fn ($q) => $q->whereKeyNot($keep->getKey()))
            ->get()
            ->each(function (BestForEdition $previous) {
                $previous->current_for_subject_id = null;
                $previous->save();
            });
    }
}
