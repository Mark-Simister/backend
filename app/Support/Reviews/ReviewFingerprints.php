<?php

namespace App\Support\Reviews;

use App\Models\PublishedReviewPayload;
use Illuminate\Support\Collection;

/**
 * Content and media fingerprints (design record section 6). Every method returns NULL
 * when a fingerprint cannot be produced; NULL always fails closed.
 */
final class ReviewFingerprints
{
    public static function content(PublishedReviewPayload $payload, ?string $scheme): ?string
    {
        if (! FingerprintScheme::isContentScheme($scheme)) {
            return null;
        }

        try {
            return CanonicalJson::fingerprint(RenderProjection::build($payload, $scheme));
        } catch (ProjectionException) {
            return null;
        }
    }

    public static function media(Collection $attachments): ?string
    {
        return CanonicalJson::fingerprint(MediaProjection::build($attachments));
    }

    public static function activeMedia(string $publishedReviewId): ?string
    {
        return self::media(ReviewMediaSet::active($publishedReviewId));
    }

    public static function proposedMedia(string $publishedReviewId): ?string
    {
        return self::media(ReviewMediaSet::proposed($publishedReviewId));
    }
}
