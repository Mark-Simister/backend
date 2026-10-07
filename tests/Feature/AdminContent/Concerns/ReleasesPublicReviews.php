<?php

namespace Tests\Feature\AdminContent\Concerns;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Support\Facades\DB;

/**
 * Since Sprint B every public review surface verifies the human release binding
 * (Admin Content & Media design record, section 5). Fixtures that need a publicly
 * visible review use this to model the state a QA pass and Release produce: the derived
 * content fingerprint is maintained, and an unheld `released` editorial record is bound
 * to the current content and the active media under the active scheme.
 *
 * Call it AFTER the payload's final state is written: a later write to rendered content
 * leaves the binding stale and the review fails verification, exactly as in production.
 * The real QA / Release workflow is tested by the Admin Content suite.
 */
trait ReleasesPublicReviews
{
    protected function releaseForPublic(PublishedReviewPayload|string $payload): void
    {
        $model = is_string($payload)
            ? PublishedReviewPayload::query()->where('published_review_id', $payload)->firstOrFail()
            : PublishedReviewPayload::query()->findOrFail($payload->getKey());

        $scheme = VideoAuthority::resolve()->contentScheme() ?? FingerprintScheme::P1;
        $content = ReviewFingerprints::content($model, $scheme);

        DB::table('published_review_payloads')->where('id', $model->id)->update([
            'content_fingerprint' => $content,
            'content_fingerprint_scheme' => $content === null ? null : $scheme,
        ]);

        $record = ReviewEditorialRecord::query()->find($model->published_review_id)
            ?? new ReviewEditorialRecord(['published_review_id' => $model->published_review_id]);
        $record->forceFill([
            'editorial_state' => ReviewEditorialRecord::STATE_RELEASED,
            'bound_content_fingerprint' => $content,
            'bound_media_fingerprint' => ReviewFingerprints::activeMedia($model->published_review_id),
            'bound_fingerprint_scheme' => $scheme,
            'hold_at' => null,
            'hold_reason' => null,
            'hold_by' => null,
        ])->save();
    }
}
