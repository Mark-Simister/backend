<?php

namespace App\Models;

use App\Models\Concerns\RefusesHardDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Admin key of two-key publication for one pipeline review
 * (design record sections 4 and 12.4).
 *
 * Keyed by published_review_id - deliberately not a foreign key, because payload rows
 * are rewritten wholesale by imports (the review_publication_dispositions precedent).
 * No row means the review is not released. State changes only through
 * App\Services\Reviews\ReviewEditorialService, which audits every transition.
 */
class ReviewEditorialRecord extends Model
{
    use RefusesHardDelete;

    public const STATE_PENDING_REVIEW = 'pending_review';
    public const STATE_RETURNED_FOR_CORRECTION = 'returned_for_correction';
    public const STATE_QA_PASSED = 'qa_passed';
    public const STATE_RELEASED = 'released';

    public const STATES = [
        self::STATE_PENDING_REVIEW,
        self::STATE_RETURNED_FOR_CORRECTION,
        self::STATE_QA_PASSED,
        self::STATE_RELEASED,
    ];

    protected $table = 'review_editorial_records';
    protected $primaryKey = 'published_review_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['published_review_id'];

    protected $attributes = [
        'editorial_state' => self::STATE_PENDING_REVIEW,
    ];

    protected $casts = [
        'channel_id' => 'integer',
        'character_id' => 'integer',
        'category_id' => 'integer',
        'associations_confirmed_at' => 'datetime',
        'associations_confirmed_by' => 'integer',
        'state_changed_at' => 'datetime',
        'state_changed_by' => 'integer',
        'hold_at' => 'datetime',
        'hold_by' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payload()
    {
        return $this->hasOne(PublishedReviewPayload::class, 'published_review_id', 'published_review_id');
    }

    public function isReleased(): bool
    {
        return $this->editorial_state === self::STATE_RELEASED;
    }

    public function isHeld(): bool
    {
        return $this->hold_at !== null;
    }

    public function associationsConfirmed(): bool
    {
        return $this->associations_confirmed_at !== null
            && $this->channel_id !== null
            && $this->character_id !== null
            && $this->category_id !== null;
    }

    public function hasBinding(): bool
    {
        return $this->bound_content_fingerprint !== null
            && $this->bound_media_fingerprint !== null
            && $this->bound_fingerprint_scheme !== null;
    }
}
