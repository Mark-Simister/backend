<?php

namespace App\Support\Reviews;

/**
 * The ONLY object a public surface may render review-derived content from (design
 * record section 5). Immutable: it carries the projection and media that were just
 * verified against the human-approved release binding, so nothing can change between
 * the check and the output.
 */
final class VerifiedReview
{
    public function __construct(
        public readonly string $publishedReviewId,
        public readonly string $scheme,
        public readonly string $contentFingerprint,
        public readonly string $mediaFingerprint,
        public readonly array $projection,
        public readonly array $media,
    ) {
    }
}
