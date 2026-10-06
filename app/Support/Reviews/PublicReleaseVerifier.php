<?php

namespace App\Support\Reviews;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The single reusable public-release verifier (design record section 5).
 *
 * Governing rule: no public surface may emit review-derived content unless the actual
 * current render projection still matches the human-approved release binding.
 *
 *   candidates()   SQL prefilter only (E1-E7): pipeline source, producer clearance,
 *                  region, D3 hold, D1, a released and unheld record bound to the active
 *                  scheme with stored fingerprint = bound fingerprint, no blocking
 *                  disposition. Efficient, but never trusted for output.
 *   verify()       E1-E7 for one row, then E8: recompute the content fingerprint from the
 *                  row actually loaded and the media fingerprint from the active review
 *                  attachments, and compare both with the binding.
 *   verifyMany()   the same in bulk, for listings and the sitemap.
 *
 * SPRINT A: built and tested only. It is NOT wired to /review/{slug}, the sitemap, the
 * Best For gate or any public API - that wiring belongs to Sprint B.
 */
class PublicReleaseVerifier
{
    public function candidates(?string $region): Builder
    {
        $query = PublishedReviewPayload::query()
            ->publicForRegion($region)
            ->where('published_review_payloads.source', 'pipeline');

        $scheme = VideoAuthority::resolve()->contentScheme();
        if ($scheme === null) {
            return $query->whereIn('published_review_payloads.id', []);   // invalid authority: fail closed
        }

        return $query
            ->whereNotNull('published_review_payloads.content_fingerprint')
            ->where('published_review_payloads.content_fingerprint_scheme', $scheme)
            ->whereExists(function (QueryBuilder $record) use ($scheme) {
                $record->select('rer.published_review_id')
                    ->from('review_editorial_records as rer')
                    ->whereColumn('rer.published_review_id', 'published_review_payloads.published_review_id')
                    ->where('rer.editorial_state', ReviewEditorialRecord::STATE_RELEASED)
                    ->whereNull('rer.hold_at')
                    ->where('rer.bound_fingerprint_scheme', $scheme)
                    ->whereColumn('rer.bound_content_fingerprint', 'published_review_payloads.content_fingerprint');
            })
            ->whereNotExists(function (QueryBuilder $disposition) {
                $disposition->select('d.published_review_id')
                    ->from('review_publication_dispositions as d')
                    ->whereColumn('d.published_review_id', 'published_review_payloads.published_review_id')
                    ->where(function (QueryBuilder $blocking) {
                        $blocking->whereNotNull('d.withdrawn_at')
                            ->orWhere('d.validity_state', ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID)
                            ->orWhere('d.availability_state', ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE);
                    });
            });
    }

    public function verify(PublishedReviewPayload $payload, ?string $region): ?VerifiedReview
    {
        if (! $payload->exists || ! $this->candidates($region)->whereKey($payload->getKey())->exists()) {
            return null;
        }

        return $this->verifyBinding($payload);
    }

    /**
     * @param  iterable<PublishedReviewPayload>  $payloads
     * @return Collection<int, VerifiedReview>  only the payloads that verify, in input order
     */
    public function verifyMany(iterable $payloads, ?string $region): Collection
    {
        $payloads = collect($payloads)->filter(fn ($p) => $p instanceof PublishedReviewPayload && $p->exists)->values();
        if ($payloads->isEmpty()) {
            return collect();
        }

        $eligible = $this->candidates($region)
            ->whereIn('published_review_payloads.id', $payloads->map->getKey()->all())
            ->pluck('published_review_payloads.id')
            ->flip();

        return $payloads
            ->filter(fn (PublishedReviewPayload $p) => $eligible->has($p->getKey()))
            ->map(fn (PublishedReviewPayload $p) => $this->verifyBinding($p))
            ->filter()
            ->values();
    }

    /** E8: the actual current projection and media must match the release binding. */
    private function verifyBinding(PublishedReviewPayload $payload): ?VerifiedReview
    {
        $scheme = VideoAuthority::resolve()->contentScheme();
        if ($scheme === null || $payload->source !== 'pipeline') {
            return null;
        }

        $record = ReviewEditorialRecord::query()->find($payload->published_review_id);
        if (! $record
            || ! $record->isReleased()
            || $record->isHeld()
            || ! $record->hasBinding()
            || $record->bound_fingerprint_scheme !== $scheme) {
            return null;
        }

        try {
            $projection = RenderProjection::build($payload, $scheme);
        } catch (ProjectionException) {
            return null;
        }

        $content = CanonicalJson::fingerprint($projection);
        if ($content === null || ! hash_equals($record->bound_content_fingerprint, $content)) {
            return null;
        }

        $media = MediaProjection::build(ReviewMediaSet::active($payload->published_review_id));
        $mediaFingerprint = CanonicalJson::fingerprint($media);
        if ($mediaFingerprint === null || ! hash_equals($record->bound_media_fingerprint, $mediaFingerprint)) {
            return null;
        }

        return new VerifiedReview($payload->published_review_id, $scheme, $content, $mediaFingerprint, $projection, $media);
    }
}
