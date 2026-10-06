<?php

namespace App\Support\Reviews;

/**
 * Stored scheme identifiers for br-render-canonical-v1 (design record section 6.1).
 *
 *   P1  content projection, Mode P (payload video authority)
 *   A1  content projection, Mode A (Admin video authority)
 *   M1  the bound review-media set
 */
final class FingerprintScheme
{
    public const P1 = 'br-render-canonical-v1:P1';
    public const A1 = 'br-render-canonical-v1:A1';
    public const M1 = 'br-render-canonical-v1:M1';

    public const CONTENT_SCHEMES = [self::P1, self::A1];

    public static function isContentScheme(?string $scheme): bool
    {
        return in_array($scheme, self::CONTENT_SCHEMES, true);
    }
}
