<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mutable operational state about a review, keyed by published_review_id.
 *
 * Separate from `published_review_payloads` on purpose: that table is
 * rewritten wholesale by PublishedReviewPayloadSeeder::updateOrCreate, so a
 * DB-only update there is reverted by the next import. Disposition has to
 * outlive the import.
 *
 * WHAT COUNTS AS A WITHDRAWAL. Exactly one thing: `withdrawn_at` being set.
 * Not a class, not a validity state, and never an availability state. A row
 * saying a product is temporarily unavailable says nothing about withdrawal,
 * and `effectiveWithdrawalClass()` returns null for it.
 *
 * WHY `withdrawal_class` IS NULLABLE WITH NO DEFAULT. A column default of
 * 'material' would turn every availability-only row into a material
 * withdrawal by accident. Instead, "material" is derived at read time when a
 * withdrawal exists but has not been classified and confirmed by a human —
 * fail-safe, and visibly pending rather than silently decided.
 */
class ReviewPublicationDisposition extends Model
{
    protected $table = 'review_publication_dispositions';

    protected $primaryKey = 'published_review_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** No created_at/updated_at columns; `disposition_at` is the audit time. */
    public $timestamps = false;

    public const CLASS_ORDINARY_EDITORIAL = 'ordinary_editorial';
    public const CLASS_MATERIAL = 'material';
    public const CLASS_TEMPORARY_TECHNICAL = 'temporary_technical';

    public const VALIDITY_VALID = 'valid';
    public const VALIDITY_MATERIALLY_INVALID = 'materially_invalid';

    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';

    protected $fillable = [
        'published_review_id',
        'withdrawn_at',
        'withdrawal_class',
        'classification_confirmed',
        'withdrawal_reason',
        'validity_state',
        'availability_state',
        'disposition_at',
        'disposed_by',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'classification_confirmed' => 'boolean',
        'disposition_at' => 'datetime',
        'disposed_by' => 'integer',
    ];

    protected static function booted(): void
    {
        // Row-level invariants. These keep the derivation below meaningful:
        // a class without a withdrawal event, or a confirmation with nothing
        // to confirm, is a contradiction rather than a state.
        static::saving(function (ReviewPublicationDisposition $disposition) {
            if ($disposition->withdrawal_class !== null && $disposition->withdrawn_at === null) {
                throw new DomainException(
                    'A withdrawal class requires a withdrawal event: set withdrawn_at, or leave withdrawal_class null.'
                );
            }

            if ($disposition->classification_confirmed && $disposition->withdrawal_class === null) {
                throw new DomainException(
                    'A withdrawal classification cannot be confirmed before a withdrawal_class is set.'
                );
            }
        });
    }

    /** The only test for an actual withdrawal event. */
    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * How this review must actually be treated right now.
     *
     * null                    → no withdrawal event exists
     * self::CLASS_MATERIAL    → either a confirmed material withdrawal, or a
     *                           withdrawal that is not yet classified and
     *                           confirmed, which is treated as material until
     *                           a human says otherwise
     * any other class         → that confirmed class governs
     */
    public function effectiveWithdrawalClass(): ?string
    {
        if (! $this->isWithdrawn()) {
            return null;
        }

        if ($this->withdrawal_class === null || ! $this->classification_confirmed) {
            return self::CLASS_MATERIAL;
        }

        return $this->withdrawal_class;
    }

    /** A withdrawal is waiting on a human to classify or confirm it. */
    public function isPendingDisposition(): bool
    {
        return $this->isWithdrawn()
            && ($this->withdrawal_class === null || ! $this->classification_confirmed);
    }

    /** Treated as material — the fail-safe reading, confirmed or pending. */
    public function isEffectivelyMaterial(): bool
    {
        return $this->effectiveWithdrawalClass() === self::CLASS_MATERIAL;
    }

    public function isMateriallyInvalid(): bool
    {
        return $this->validity_state === self::VALIDITY_MATERIALLY_INVALID;
    }

    public function isTemporarilyUnavailable(): bool
    {
        return $this->availability_state === self::AVAILABILITY_TEMPORARILY_UNAVAILABLE;
    }

    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }
}
