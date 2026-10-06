<?php

namespace App\Console\Commands;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Services\Reviews\ReviewEditorialService;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * reviews:reconcile-editorial (design record section 4.10). An explicit, idempotent
 * operational tool, run ONLY under an authorised operation, deployment or data boundary
 * - and REQUIRED inside any governance boundary that changes
 * reviews.divergence_hold_product_uids. It is not scheduled.
 *
 * Reports released records that are:
 *   d3_held             now under the D3 divergence hold          -> T5 (qa_passed)
 *   disposition_blocked withdrawn or materially invalid           -> T5 (qa_passed)
 *   scheme_mismatch     bound to a scheme other than the active   -> T6 target (pending_review)
 *   binding_mismatch    actual content/media differs from binding -> T6 target (pending_review)
 * Temporary unavailability is transient and is neither reported nor demoted.
 *
 *   --check  read-only; exit non-zero when anything is found.
 *   --apply  demote with system audit events.
 */
class ReviewsReconcileEditorial extends Command
{
    protected $signature = 'reviews:reconcile-editorial
        {--check : Report only; no writes}
        {--apply : Demote the reported releases}';

    protected $description = 'Reconcile released reviews against governance holds, dispositions and their bindings.';

    public function handle(ReviewEditorialService $editorial): int
    {
        if ($this->option('check') === $this->option('apply')) {
            $this->error('Pass exactly one of --check or --apply.');

            return self::INVALID;
        }

        $scheme = VideoAuthority::resolve()->contentScheme();
        if ($scheme === null) {
            $this->error('The video-authority configuration is invalid; releases cannot be evaluated.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $found = [];

        ReviewEditorialRecord::query()
            ->where('editorial_state', ReviewEditorialRecord::STATE_RELEASED)
            ->orderBy('published_review_id')
            ->get()
            ->each(function (ReviewEditorialRecord $record) use ($scheme, $editorial, $apply, &$found) {
                $issue = $this->issueFor($record, $scheme, $editorial);
                if ($issue === null) {
                    return;
                }

                $found[] = "{$record->published_review_id} {$issue}";

                if (! $apply) {
                    return;
                }

                DB::transaction(function () use ($record, $issue, $editorial) {
                    if (in_array($issue, ['d3_held', 'disposition_blocked'], true)) {
                        $editorial->demote($record->published_review_id, $issue);

                        return;
                    }

                    $locked = ReviewEditorialRecord::query()->whereKey($record->published_review_id)->lockForUpdate()->first();
                    if ($locked && $locked->isReleased()) {
                        $editorial->invalidateBinding($locked, $issue, null, ['source' => 'reconcile_editorial']);
                    }
                });
            });

        $this->line('scheme=' . $scheme . ' found=' . count($found));
        foreach ($found as $line) {
            $this->line('  ' . ($apply ? 'demoted: ' : 'issue: ') . $line);
        }

        return ($apply || $found === []) ? self::SUCCESS : self::FAILURE;
    }

    private function issueFor(ReviewEditorialRecord $record, string $scheme, ReviewEditorialService $editorial): ?string
    {
        $payload = PublishedReviewPayload::query()->where('published_review_id', $record->published_review_id)->first();
        if (! $payload) {
            return 'binding_mismatch';
        }

        if ($editorial->isDivergenceHeld($payload)) {
            return 'd3_held';
        }

        $disposition = ReviewPublicationDisposition::query()->find($record->published_review_id);
        if ($disposition && ($disposition->isWithdrawn() || $disposition->isMateriallyInvalid())) {
            return 'disposition_blocked';
        }

        if ($record->bound_fingerprint_scheme !== $scheme) {
            return 'scheme_mismatch';
        }

        $content = ReviewFingerprints::content($payload, $scheme);
        $media = ReviewFingerprints::activeMedia($record->published_review_id);
        if (! $record->hasBinding()
            || $content === null || ! hash_equals($record->bound_content_fingerprint, $content)
            || $media === null || ! hash_equals($record->bound_media_fingerprint, $media)) {
            return 'binding_mismatch';
        }

        return null;
    }
}
