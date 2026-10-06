<?php

namespace App\Support\Reviews;

/**
 * Interprets config('reviews.video_authority') on every use (design record section 8):
 *
 *   unset (env returns null; Laravel's literal null / (null) map here)  -> Mode P
 *   exactly "payload"                                                    -> Mode P
 *   exactly "admin"                                                      -> Mode A
 *   anything else - a typo, another case, an empty string, a boolean     -> invalid
 *
 * An invalid value has NO active scheme. Nothing ever silently defaults to payload.
 * A release bound to a scheme other than the active one is ineligible.
 */
final class VideoAuthority
{
    public const MODE_PAYLOAD = 'payload';
    public const MODE_ADMIN = 'admin';

    private function __construct(
        public readonly ?string $mode,
        public readonly mixed $raw,
    ) {
    }

    public static function resolve(): self
    {
        $raw = config('reviews.video_authority');

        return match (true) {
            $raw === null => new self(self::MODE_PAYLOAD, null),
            $raw === self::MODE_PAYLOAD => new self(self::MODE_PAYLOAD, $raw),
            $raw === self::MODE_ADMIN => new self(self::MODE_ADMIN, $raw),
            default => new self(null, $raw),
        };
    }

    public function isValid(): bool
    {
        return $this->mode !== null;
    }

    /** The active content projection scheme, or NULL when the configuration is invalid. */
    public function contentScheme(): ?string
    {
        return match ($this->mode) {
            self::MODE_PAYLOAD => FingerprintScheme::P1,
            self::MODE_ADMIN => FingerprintScheme::A1,
            default => null,
        };
    }

    /** @throws VideoAuthorityConfigurationException */
    public function contentSchemeOrFail(): string
    {
        $scheme = $this->contentScheme();

        if ($scheme === null) {
            throw new VideoAuthorityConfigurationException(
                'PUBLIC_REVIEW_VIDEO_AUTHORITY must be unset, "payload" or "admin"; the configured value is invalid. '
                . 'Public review verification fails closed and supported ingestion is refused until it is corrected.'
            );
        }

        return $scheme;
    }
}
