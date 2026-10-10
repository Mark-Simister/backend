<?php

namespace App\Services\Reviews;

use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONE supported ingestion path for n8n review payloads (design record section 4.6).
 * PublishedReviewPayloadSeeder writes through it today; the future authenticated
 * ingestion endpoint (a separate n8n boundary) must use it too.
 *
 * In one transaction it:
 *   1. locks the payload row;
 *   2. keeps the existing protection: a withdrawn row is not resurrected without --force;
 *   3. writes the payload;
 *   4. fingerprints the saved values as read back through the model cast;
 *   5. stores content_fingerprint / content_fingerprint_scheme (derived columns);
 *   6. locks the editorial record and applies T6: a release or QA pass whose binding no
 *      longer matches is invalidated (content_refreshed / fingerprint_unavailable), and a
 *      returned review whose content changed goes back to pending_review.
 * An unchanged fingerprint (e.g. only updated_at, beastiescore.raw or safety_age_days
 * moved) leaves a release standing.
 *
 * It refuses to run while the video-authority configuration is invalid, so a typo can
 * never cause mass demotion. Admin never writes through this service.
 */
class ReviewPayloadIngestor
{
    public const IMPORTED = 'imported';
    public const SKIPPED_WITHDRAWN = 'skipped_withdrawn';

    /** Derived columns the ingestion service owns; input rows can never set them. */
    private const DERIVED = ['id', 'content_fingerprint', 'content_fingerprint_scheme'];

    /**
     * Production OS columns that are deliberately not persisted (RP-ADMIN-CONTENT-MEDIA-F3-SCHEMA-GAP-01).
     * The first five are flat mirrors of values already stored inside review_page_json
     * (payload_schema_version, review_facts.public_signal / analysis_depth / analysis_depth_count,
     * beastiescore.score_basis_summary); the last two are transport-only with no approved consumer.
     * An explicit list, not a column allowlist: any other unknown column still fails the save,
     * so a new source field is never dropped silently.
     */
    public const SOURCE_ONLY_FIELDS = [
        'payload_schema_version',
        'public_signal',
        'analysis_depth',
        'analysis_depth_count',
        'score_basis_summary',
        'buyer_questions_json',
        'product_facts_json',
    ];

    public function __construct(private ReviewEditorialService $editorial)
    {
    }

    /**
     * @param  array<string, mixed>  $row  one payload row (already type-normalised)
     * @return array{status: string, payload: ?PublishedReviewPayload, invalidation: ?string}
     */
    public function upsert(array $row, bool $force = false): array
    {
        $scheme = VideoAuthority::resolve()->contentSchemeOrFail();

        $id = $row['published_review_id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidArgumentException('A payload row needs a published_review_id.');
        }

        foreach ([...self::DERIVED, ...self::SOURCE_ONLY_FIELDS] as $column) {
            unset($row[$column]);
        }

        return DB::transaction(function () use ($row, $id, $force, $scheme) {
            $payload = PublishedReviewPayload::query()->where('published_review_id', $id)->lockForUpdate()->first();

            if ($payload && $payload->publish_status === 'withdrawn' && ! $force) {
                return ['status' => self::SKIPPED_WITHDRAWN, 'payload' => $payload, 'invalidation' => null];
            }

            $previousFingerprint = $payload?->content_fingerprint;

            $payload ??= new PublishedReviewPayload(['published_review_id' => $id]);
            $payload->fill($row)->save();
            $payload->refresh();   // read back through the model cast

            $fingerprint = ReviewFingerprints::content($payload, $scheme);

            PublishedReviewPayload::query()->whereKey($payload->getKey())->update([
                'content_fingerprint' => $fingerprint,
                'content_fingerprint_scheme' => $fingerprint === null ? null : $scheme,
            ]);
            $payload->refresh();

            $invalidation = null;
            $record = ReviewEditorialRecord::query()->whereKey($id)->lockForUpdate()->first();
            if ($record) {
                $invalidation = $this->editorial->invalidateForIngestion($record, $fingerprint, $scheme, $previousFingerprint);
            }

            return ['status' => self::IMPORTED, 'payload' => $payload, 'invalidation' => $invalidation];
        });
    }
}
