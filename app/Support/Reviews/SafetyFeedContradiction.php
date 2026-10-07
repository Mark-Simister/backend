<?php

namespace App\Support\Reviews;

/**
 * D1 (E5) - a materially contradictory public safety state fails closed. The ONE D1
 * implementation the public release verifier uses (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-03):
 * evaluated in application code on the structurally validated, in-memory render
 * projection, never by a database JSON function. No public surface reimplements it.
 *
 * Blocked exactly when
 *   safety.safety_feed_status === "clear" AND
 *   safety.safety_feed_freshness_status IN ("missing", "unknown", "stale").
 *
 * Every other state is not contradictory: an absent or non-object page or safety block,
 * an absent or null field, and any non-string value (no type coercion). Comparison is
 * exact and case-sensitive. The single tolerance is trailing U+0020 spaces: the D1
 * predicate this replaces compared through MySQL's PAD SPACE collation, so in production
 * "clear " and "stale " were blocked; ignoring trailing spaces keeps every state MySQL
 * blocked blocked, and anything else is compared byte for byte.
 */
class SafetyFeedContradiction
{
    public const CLEAR = 'clear';

    public const BLOCKING_FRESHNESS = ['missing', 'unknown', 'stale'];

    /** @param  mixed  $reviewPage  the projection's review_page_json */
    public function blocks(mixed $reviewPage): bool
    {
        $safety = is_array($reviewPage) ? ($reviewPage['safety'] ?? null) : null;
        if (! is_array($safety)) {
            return false;
        }

        return self::normalised($safety['safety_feed_status'] ?? null) === self::CLEAR
            && in_array(self::normalised($safety['safety_feed_freshness_status'] ?? null), self::BLOCKING_FRESHNESS, true);
    }

    private static function normalised(mixed $value): ?string
    {
        return is_string($value) ? rtrim($value, ' ') : null;
    }
}
