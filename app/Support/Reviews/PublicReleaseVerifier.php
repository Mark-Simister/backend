<?php

namespace App\Support\Reviews;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The single reusable public-release verifier (design record section 5).
 *
 * Governing rule: no public surface may emit review-derived content unless the actual
 * current render projection still matches the human-approved release binding.
 *
 * Eligibility pipeline (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-03):
 *
 *   SQL gates (candidates())  E1 pipeline source, E2 producer public status, E3 region,
 *                             E4 exact D3 hold, E6 a released and unheld record bound to
 *                             the active scheme with stored fingerprint = bound
 *                             fingerprint, E7 no blocking disposition. Set-oriented and
 *                             JSON-free: no public candidate query asks the database to
 *                             parse review_page_json.
 *   structural evaluability   the loaded review_page_json must be JSON the supported
 *                             engines can evaluate (evaluable()), or the row is ineligible.
 *   E5 / D1                   SafetyFeedContradiction, on the in-memory render projection.
 *   E8                        the recomputed content projection and active-media
 *                             projection must match the release binding.
 *   -> VerifiedReview         the only thing a public renderer receives.
 *
 * Structural evaluability, D1, the projection and therefore the render all derive from
 * the SAME loaded payload object, never a second read: a raw writer after the load can
 * only affect the next verification, and nothing here depends on transaction isolation.
 * Collection surfaces run the SQL gates once per bounded chunk and read the editorial
 * records and active media of a chunk in one query each.
 *
 * Wired in Sprint B to every public surface: /review/{slug} (before any cache lookup;
 * the renderer receives only the VerifiedReview), the legacy numeric redirect, the
 * sitemap (eachVerified()) and the Best For publication gate (verify()). No surface
 * reimplements it. Under the A1 scheme a bound video also requires a bound poster in the
 * same release (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-01).
 */
class PublicReleaseVerifier
{
    /**
     * MySQL refuses a document nested more than 100 arrays/objects deep (error 3157).
     * PHP's json_validate()/json_decode() depth counts one level more than that nesting,
     * so depth 101 accepts exactly the documents MySQL can evaluate. (SQLite's own limit
     * is far higher.)
     */
    public const MAX_EVALUABLE_JSON_DEPTH = 101;

    /** Rows per bounded set step: one SQL-gate statement per chunk. */
    public const SET_CHUNK = 200;

    public function __construct(private SafetyFeedContradiction $d1 = new SafetyFeedContradiction())
    {
    }

    /**
     * The SQL gates E1, E2, E3, E4, E6 and E7 - none of them reads stored JSON. A prefilter
     * only: never trusted for output on its own.
     */
    public function candidates(?string $region): Builder
    {
        $query = PublishedReviewPayload::query()
            ->publicForRegionBeforeContentGate($region)
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

    /**
     * Every VerifiedReview of a universe the caller narrows and orders with JSON-free
     * constraints (slug, product_uid, ordering), in that order.
     *
     * @param  (Closure(Builder): mixed)|null  $constrain
     * @return Collection<int, VerifiedReview>
     */
    public function verifiedRows(?string $region, ?Closure $constrain = null): Collection
    {
        $verified = collect();
        $this->eachVerified($region, $constrain, function (VerifiedReview $review) use ($verified) {
            $verified->push($review);
        });

        return $verified;
    }

    /**
     * Calls $each(VerifiedReview, PublishedReviewPayload) for every verified row of the
     * caller's universe, in order, one bounded chunk at a time: ONE SQL-gate statement per
     * chunk, then structural evaluability, D1 and E8 in memory on the rows that statement
     * returned. The chunks are read in one transaction for ordinary consistency between
     * them; no eligibility decision depends on it.
     *
     * @param  (Closure(Builder): mixed)|null  $constrain
     * @param  Closure(VerifiedReview, PublishedReviewPayload): mixed  $each
     */
    public function eachVerified(?string $region, ?Closure $constrain, Closure $each): void
    {
        $query = $this->candidates($region);
        if ($constrain) {
            $constrain($query);
        }
        $query->orderBy('published_review_payloads.id');   // a total order, so the chunks are stable

        $query->getConnection()->transaction(function () use ($query, $each) {
            $seen = [];
            $query->chunk($this->chunkSize(), function (Collection $payloads) use ($each, &$seen) {
                $payloads = $payloads->reject(fn (PublishedReviewPayload $p) => isset($seen[$p->getKey()]));
                foreach ($payloads as $payload) {
                    $seen[$payload->getKey()] = true;
                }
                foreach ($this->verifyLoaded($payloads) as [$payload, $review]) {
                    $each($review, $payload);
                }
            });
        });
    }

    public function verify(PublishedReviewPayload $payload, ?string $region): ?VerifiedReview
    {
        return $this->verifyMany([$payload], $region)->first();
    }

    /**
     * The full pipeline for loaded rows: the SQL gates once per chunk, restricted to the
     * rows' keys, then structural evaluability, D1 and E8 on the given objects themselves -
     * what verifies, and what a VerifiedReview carries, is exactly what was passed in.
     *
     * @param  iterable<PublishedReviewPayload>  $payloads
     * @return Collection<int, VerifiedReview>  only the payloads that verify, in input order
     */
    public function verifyMany(iterable $payloads, ?string $region): Collection
    {
        $payloads = collect($payloads)->filter(fn ($p) => $p instanceof PublishedReviewPayload && $p->exists)->values();

        $verified = collect();
        foreach ($payloads->chunk($this->chunkSize()) as $chunk) {
            $gated = $this->candidates($region)
                ->whereIn('published_review_payloads.id', $chunk->map(fn (PublishedReviewPayload $p) => $p->getKey())->all())
                ->pluck('published_review_payloads.id')
                ->flip();

            foreach ($this->verifyLoaded($chunk->filter(fn (PublishedReviewPayload $p) => $gated->has($p->getKey()))) as [, $review]) {
                $verified->push($review);
            }
        }

        return $verified;
    }

    /**
     * Can the database evaluate JSON paths on this stored review_page_json value? NULL can
     * (the path is simply NULL). A string must be valid JSON within the depth limit, and
     * every number in it must be finite: a number beyond the double range is valid JSON
     * syntax, but MySQL refuses the whole document (3141) - PHP decodes it to INF.
     */
    public static function evaluable(mixed $storedJson): bool
    {
        if ($storedJson === null) {
            return true;
        }

        return is_string($storedJson)
            && json_validate($storedJson, self::MAX_EVALUABLE_JSON_DEPTH)
            && self::allFinite(json_decode($storedJson, true, self::MAX_EVALUABLE_JSON_DEPTH));
    }

    private static function allFinite(mixed $value): bool
    {
        if (is_float($value)) {
            return is_finite($value);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::allFinite($item)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Structural evaluability of the review_page_json this very object loaded. */
    protected function structurallyEvaluable(PublishedReviewPayload $payload): bool
    {
        $attributes = $payload->getAttributes();

        return array_key_exists('review_page_json', $attributes) && self::evaluable($attributes['review_page_json']);
    }

    protected function chunkSize(): int
    {
        return self::SET_CHUNK;
    }

    /**
     * Structural evaluability, then D1, then E8 - in memory, on rows that passed the SQL
     * gates. The editorial records and the active media of the whole set are read once
     * each (the same reads E8 made row by row before).
     *
     * @param  Collection<int, PublishedReviewPayload>  $payloads
     * @return list<array{0: PublishedReviewPayload, 1: VerifiedReview}>
     */
    private function verifyLoaded(Collection $payloads): array
    {
        $scheme = VideoAuthority::resolve()->contentScheme();
        if ($scheme === null || $payloads->isEmpty()) {
            return [];
        }

        $ids = $payloads->map(fn (PublishedReviewPayload $p) => (string) $p->published_review_id)->unique()->values()->all();
        $records = ReviewEditorialRecord::query()->whereKey($ids)->get()->keyBy(fn (ReviewEditorialRecord $r) => (string) $r->getKey());
        $media = ReviewMediaSet::activeFor($ids);

        $verified = [];
        foreach ($payloads as $payload) {
            $id = (string) $payload->published_review_id;
            $review = $this->verifyOne($payload, $scheme, $records->get($id), $media->get($id, collect()));
            if ($review) {
                $verified[] = [$payload, $review];
            }
        }

        return $verified;
    }

    private function verifyOne(PublishedReviewPayload $payload, string $scheme, ?ReviewEditorialRecord $record, Collection $activeMedia): ?VerifiedReview
    {
        if ($payload->source !== 'pipeline' || ! $this->structurallyEvaluable($payload)) {
            return null;
        }

        try {
            $projection = RenderProjection::build($payload, $scheme);
        } catch (ProjectionException) {
            return null;
        }

        // E5 / D1, on the same in-memory projection that E8 fingerprints and the renderer receives.
        if ($this->d1->blocks($projection['review_page_json'])) {
            return null;
        }

        // E8: the actual current projection and media must match the release binding.
        if (! $record
            || ! $record->isReleased()
            || $record->isHeld()
            || ! $record->hasBinding()
            || $record->bound_fingerprint_scheme !== $scheme) {
            return null;
        }

        $content = CanonicalJson::fingerprint($projection);
        if ($content === null || ! hash_equals($record->bound_content_fingerprint, $content)) {
            return null;
        }

        $media = MediaProjection::build($activeMedia);
        $mediaFingerprint = CanonicalJson::fingerprint($media);
        if ($mediaFingerprint === null || ! hash_equals($record->bound_media_fingerprint, $mediaFingerprint)) {
            return null;
        }

        // Mode A: a bound video without a poster captured in the same release fails
        // closed (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-01). Only ACTIVE attachments count.
        if ($scheme === FingerprintScheme::A1 && ! ReviewMediaSet::modeAPosterSatisfied(array_column($media, 'role'))) {
            return null;
        }

        return new VerifiedReview($payload->published_review_id, $scheme, $content, $mediaFingerprint, $projection, $media);
    }
}
