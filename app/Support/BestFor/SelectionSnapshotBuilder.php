<?php

namespace App\Support\BestFor;

use App\Models\BestForSubject;
use App\Models\PublishedReviewPayload;

/**
 * Resolves the AUTHORITATIVE value of every derived "as published" field, at the
 * publication boundary.
 *
 * This is what makes the immutable snapshot truthful rather than merely non-null. A
 * draft value is never trusted: whatever a curator or an earlier draft left in these
 * columns is replaced by what the authoritative source actually says at the moment of
 * publication. Editorially authored fields — methodology text, superlative, selection
 * reason — are NOT resolved here; they are authored, and the gate validates them.
 *
 * ### Authoritative sources
 *
 *   review_slug_as_published          column  published_review_payloads.review_slug
 *   product_name_as_published         json    review_page_json.product.product_name
 *                                             (fallback review_page_json.review_facts.product)
 *   product_image_ref_as_published    json    review_page_json.product.hero_image_url
 *                                             (fallback columns hero_image_url, og_image_url)
 *   source_product_identifier         column  published_review_payloads.product_uid
 *   beastie_score_as_published        column  published_review_payloads.final_beastie_score
 *   public_rating_as_published        column  published_review_payloads.public_score
 *   public_rating_count_as_published  column  published_review_payloads.public_rating_count
 *   public_signal_as_published        json    review_page_json.review_facts.public_signal
 *   category_name_as_published        column  categories.name (via best_for_subjects.category_id)
 *   year_as_published                 column  best_for_subjects.year
 *
 * ### Sources this class must NEVER read, and why
 *
 *   review_facts.public_rating_count  NOT the ratings count. VideoReviewMapper populates
 *                                     it from `reviews_analysed` — the retailer reviews
 *                                     ANALYSED — and emits the same value as
 *                                     `public_rating_count_retailer`. Reading it would
 *                                     publish a truthful-looking but wrong number. The
 *                                     column is the ratings count.
 *   confidence_tier                   internal only. Never renders, never a fallback —
 *                                     neither the column nor review_facts.confidence_tier.
 *   review_facts.best_for             unrelated. It is the "this product is best for these
 *                                     use cases" list, not the Best For collection.
 *
 * Every method returns ['values' => [...], 'errors' => [...]]. A non-empty `errors` means
 * the snapshot cannot be built truthfully and publication must refuse — the values array
 * is then incomplete and must not be written.
 */
final class SelectionSnapshotBuilder
{
    /** The v7 contract. A payload that does not declare exactly this cannot be published into a collection. */
    public const REQUIRED_PAYLOAD_SCHEMA_VERSION = 'confidence_fields_v7_2026-07-27';

    /** The four-value public_signal domain, as shipped by the v6 confidence patcher. */
    public const PUBLIC_SIGNAL_DOMAIN = ['Very strong', 'Strong', 'Moderate', 'Limited'];

    public function resolvePayload(string $publishedReviewId): ?PublishedReviewPayload
    {
        return PublishedReviewPayload::query()
            ->where('published_review_id', $publishedReviewId)
            ->first();
    }

    /**
     * Edition-level derived values: the category display name and year AS THEY READ NOW,
     * so a later category rename cannot rewrite a published H1.
     *
     * @return array{values: array<string, mixed>, errors: string[]}
     */
    public function forEdition(BestForSubject $subject): array
    {
        $errors = [];
        $category = $subject->category;

        if (! $category || blank($category->name)) {
            $errors[] = 'Subject category cannot be resolved, so the published category name would be untruthful.';
        }

        if ($subject->year === null) {
            $errors[] = 'Subject year is missing.';
        }

        if ($errors !== []) {
            return ['values' => [], 'errors' => $errors];
        }

        return [
            'values' => [
                'category_name_as_published' => (string) $category->name,
                'year_as_published' => (int) $subject->year,
            ],
            'errors' => [],
        ];
    }

    /**
     * Selection-level derived values for one referenced review.
     *
     * @return array{values: array<string, mixed>, errors: string[]}
     */
    public function forSelection(string $publishedReviewId): array
    {
        $payload = $this->resolvePayload($publishedReviewId);

        if (! $payload) {
            return [
                'values' => [],
                'errors' => ["Review {$publishedReviewId}: no published review payload exists."],
            ];
        }

        $errors = [];
        $page = is_array($payload->review_page_json) ? $payload->review_page_json : [];
        $product = is_array($page['product'] ?? null) ? $page['product'] : [];
        $facts = is_array($page['review_facts'] ?? null) ? $page['review_facts'] : [];

        // v7 contract. Without it there is no trustworthy public_signal to publish.
        $schemaVersion = $page['payload_schema_version'] ?? null;
        if ($schemaVersion !== self::REQUIRED_PAYLOAD_SCHEMA_VERSION) {
            $errors[] = sprintf(
                'Review %s: payload_schema_version is %s, required %s.',
                $publishedReviewId,
                $schemaVersion === null ? 'absent' : '"' . $schemaVersion . '"',
                '"' . self::REQUIRED_PAYLOAD_SCHEMA_VERSION . '"'
            );
        }

        $slug = $payload->review_slug;
        if (blank($slug)) {
            $errors[] = "Review {$publishedReviewId}: review_slug is missing.";
        }

        $productName = $product['product_name'] ?? $facts['product'] ?? null;
        if (blank($productName)) {
            $errors[] = "Review {$publishedReviewId}: product name cannot be resolved.";
        }

        $score = $payload->final_beastie_score;
        if ($score === null) {
            $errors[] = "Review {$publishedReviewId}: final_beastie_score is missing.";
        }

        $rating = $payload->public_score;
        if ($rating === null) {
            $errors[] = "Review {$publishedReviewId}: public_score is missing.";
        }

        // The COLUMN, never review_facts.public_rating_count — see the class docblock.
        $ratingCount = $payload->public_rating_count;
        if ($ratingCount === null) {
            $errors[] = "Review {$publishedReviewId}: public_rating_count is missing.";
        }

        $signal = $facts['public_signal'] ?? null;
        if (blank($signal)) {
            $errors[] = "Review {$publishedReviewId}: review_facts.public_signal is missing.";
        } elseif (! in_array($signal, self::PUBLIC_SIGNAL_DOMAIN, true)) {
            $errors[] = sprintf(
                'Review %s: public_signal "%s" is outside the approved domain (%s).',
                $publishedReviewId,
                (string) $signal,
                implode(', ', self::PUBLIC_SIGNAL_DOMAIN)
            );
        }

        if ($errors !== []) {
            return ['values' => [], 'errors' => $errors];
        }

        return [
            'values' => [
                'review_slug_as_published' => (string) $slug,
                'product_name_as_published' => (string) $productName,
                'product_image_ref_as_published' => $this->resolveImage($product, $payload),
                'source_product_identifier' => blank($payload->product_uid) ? null : (string) $payload->product_uid,
                'beastie_score_as_published' => (float) $score,
                'public_rating_as_published' => (float) $rating,
                'public_rating_count_as_published' => (int) $ratingCount,
                'public_signal_as_published' => (string) $signal,
            ],
            'errors' => [],
        ];
    }

    /** Nullable by contract: a collection entry may legitimately publish without an image. */
    private function resolveImage(array $product, PublishedReviewPayload $payload): ?string
    {
        foreach ([$product['hero_image_url'] ?? null, $payload->hero_image_url, $payload->og_image_url] as $candidate) {
            if (! blank($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
