<?php

namespace App\Support\Reviews;

use JsonException;
use Throwable;

/**
 * br-render-canonical-v1 - BeastieRated's own, versioned canonical form
 * (design record section 6.3). It is NOT RFC 8785 JCS.
 *
 * Two projections are equivalent exactly when the PHP values the renderer receives are
 * identical, order and types included. The canonical text is never LESS discriminating
 * than any current output path:
 *
 *   collapsed (no longer distinguishable once decoded):
 *     whitespace; escape spelling; number spelling of the same double (4.70 vs 4.7);
 *     {} vs []; a sequential numeric-key object vs the equivalent list; duplicate keys
 *     (last wins, as the renderer sees it).
 *   preserved:
 *     object member order (PHP keeps insertion order); array order; null vs absent;
 *     integer vs float (4 vs 4.0 - JSON_PRESERVE_ZERO_FRACTION); exact string bytes
 *     (no Unicode normalisation).
 *
 * Input is the already-decoded PHP value. Decoding is done by the model's `array` cast,
 * so the fingerprint covers exactly what the renderer receives.
 */
final class CanonicalJson
{
    public const VERSION = 'br-render-canonical-v1';

    private const FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * The canonical text. serialize_precision is pinned to -1 (shortest round-trip
     * representation) for the duration of the call and then restored.
     *
     * @throws JsonException when the value cannot be encoded (invalid UTF-8, depth, ...)
     */
    public static function encode(mixed $value): string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            return json_encode($value, self::FLAGS);
        } finally {
            ini_set('serialize_precision', $previous === false ? '-1' : (string) $previous);
        }
    }

    /**
     * Lowercase-hex SHA-256 over the UTF-8 bytes of the canonical text, or NULL when no
     * canonical text can be produced. NULL always fails closed.
     */
    public static function fingerprint(mixed $value): ?string
    {
        try {
            return hash('sha256', self::encode($value));
        } catch (Throwable) {
            return null;
        }
    }
}
