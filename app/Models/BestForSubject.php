<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The stable address of a Best For selection: one category, one year.
 *
 * A subject is pure identity — it holds no state. The current edition is read
 * through `currentEdition()`, which resolves the single current-state
 * mechanism (`best_for_editions.current_for_subject_id`). There is no
 * `current_edition_id` column and no `is_current` flag, so there is nothing
 * for a second mechanism to disagree with.
 *
 * `currentEdition()` returning null is a legitimate state, not an error: it
 * means the subject has not published yet, or its current edition was severely
 * unpublished. There is deliberately no automatic fallback to an older
 * edition — an edition that was replaced was replaced.
 */
class BestForSubject extends Model
{
    protected $table = 'best_for_subjects';

    protected $fillable = [
        'category_id',
        'year',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'year' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** Every edition of this subject, oldest first. */
    public function editions(): HasMany
    {
        return $this->hasMany(BestForEdition::class, 'subject_id')->orderBy('edition_sequence');
    }

    /**
     * The current edition, or null. At most one row can satisfy this: the
     * column is UNIQUE, which both MySQL and SQLite enforce without colliding
     * NULLs.
     */
    public function currentEdition(): HasOne
    {
        return $this->hasOne(BestForEdition::class, 'current_for_subject_id');
    }

    public function hasCurrentEdition(): bool
    {
        return $this->currentEdition()->exists();
    }
}
