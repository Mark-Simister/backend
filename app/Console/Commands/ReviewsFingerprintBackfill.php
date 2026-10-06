<?php

namespace App\Console\Commands;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Services\Reviews\ReviewEditorialService;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * reviews:fingerprint-backfill (design record section 4.10). An explicit, idempotent
 * operational tool, run ONLY under an authorised operation, deployment or data boundary.
 * It is not scheduled.
 *
 *   --check  read-only: report payloads whose stored derived fingerprint differs from
 *            the recomputed one, and bindings that no longer match. Exit non-zero when
 *            anything is found.
 *   --apply  write only the derived fingerprint columns, and demote any QA pass/release
 *            whose binding no longer matches (T6 target, audited).
 */
class ReviewsFingerprintBackfill extends Command
{
    protected $signature = 'reviews:fingerprint-backfill
        {--check : Report only; no writes}
        {--apply : Write derived fingerprints and invalidate mismatched bindings}';

    protected $description = 'Recompute the derived render fingerprint of every payload (Admin Content & Media).';

    public function handle(ReviewEditorialService $editorial): int
    {
        if ($this->option('check') === $this->option('apply')) {
            $this->error('Pass exactly one of --check or --apply.');

            return self::INVALID;
        }

        $scheme = VideoAuthority::resolve()->contentScheme();
        if ($scheme === null) {
            $this->error('The video-authority configuration is invalid; refusing to fingerprint anything.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $drift = [];
        $invalidated = [];

        PublishedReviewPayload::query()->orderBy('id')->chunkById(100, function ($payloads) use ($scheme, $apply, $editorial, &$drift, &$invalidated) {
            foreach ($payloads as $payload) {
                $fingerprint = ReviewFingerprints::content($payload, $scheme);
                $storedMatches = $payload->content_fingerprint === $fingerprint
                    && $payload->content_fingerprint_scheme === ($fingerprint === null ? null : $scheme);

                $record = ReviewEditorialRecord::query()->find($payload->published_review_id);
                $bindingMismatch = $record
                    && in_array($record->editorial_state, [ReviewEditorialRecord::STATE_QA_PASSED, ReviewEditorialRecord::STATE_RELEASED], true)
                    && ($fingerprint === null
                        || $record->bound_fingerprint_scheme !== $scheme
                        || $record->bound_content_fingerprint === null
                        || ! hash_equals($record->bound_content_fingerprint, $fingerprint));

                if ($storedMatches && ! $bindingMismatch) {
                    continue;
                }

                $drift[] = $payload->published_review_id;

                if ($apply) {
                    DB::transaction(function () use ($payload, $fingerprint, $scheme, $editorial, &$invalidated) {
                        $previous = PublishedReviewPayload::query()->whereKey($payload->getKey())->lockForUpdate()->value('content_fingerprint');

                        PublishedReviewPayload::query()->whereKey($payload->getKey())->update([
                            'content_fingerprint' => $fingerprint,
                            'content_fingerprint_scheme' => $fingerprint === null ? null : $scheme,
                        ]);

                        $record = ReviewEditorialRecord::query()->whereKey($payload->published_review_id)->lockForUpdate()->first();
                        if ($record) {
                            $reason = $editorial->invalidateForIngestion($record, $fingerprint, $scheme, $previous, 'fingerprint_backfill');
                            if ($reason !== null) {
                                $invalidated[] = "{$payload->published_review_id} ({$reason})";
                            }
                        }
                    });
                }
            }
        });

        $this->line('scheme=' . $scheme . ' drift=' . count($drift));
        foreach ($drift as $id) {
            $this->line('  drift: ' . $id);
        }

        if (! $apply) {
            return $drift === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('invalidated=' . count($invalidated));
        foreach ($invalidated as $line) {
            $this->line('  invalidated: ' . $line);
        }

        return self::SUCCESS;
    }
}
