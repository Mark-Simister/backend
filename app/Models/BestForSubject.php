<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The stable address of a Best For selection: one category, one year, one region.
 *
 * All three are identity. `region_id` is not an attribute of the subject — the AU
 * and US collections for the same category and year are different collections with
 * independent edition histories, so they are different subjects. An edition inherits
 * its region from here; no edition or selection row carries one.
 *
 * A GLOBAL subject and a region-specific subject for the same category/year may both
 * exist. Which one a host serves is a later renderer precedence rule, not a constraint
 * on what may be stored, so nothing here forbids that combination.
 *
 * IDENTITY IS IMMUTABLE ONCE PERSISTED. The unique index stops a DUPLICATE triple;
 * it does nothing to stop an existing row's triple being CHANGED to an unused one.
 * Without this guard `$subject->update(['region_id' => $us])` would silently move
 * every edition hanging off that subject_id from one regional identity to another —
 * rewriting the identity of already-published editorial records without creating a
 * single new row. A curator who created the wrong subject creates the right one;
 * they never mutate the wrong one into it.
 *
 * LIMIT OF THIS GUARD, and it is the same trade-off BestForEdition accepts: it runs
 * on Eloquent model events, so it covers save/update on a model instance, including
 * forceFill. It does NOT cover query-builder writes (Model::query()->update(),
 * DB::table(...)), raw SQL, or saveQuietly/withoutEvents. Database triggers would
 * need separate MySQL and SQLite implementations and are deliberately not used, so
 * admin and service code must go through the model boundary.
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

    /**
     * The identity tuple, frozen once the row exists. All three together, never a
     * subset: category, year and region are one address, and freezing only the newest
     * of them would leave the same defect behind under a different column name.
     */
    public const IMMUTABLE_IDENTITY = [
        'category_id',
        'year',
        'region_id',
    ];

    protected $fillable = [
        'category_id',
        'year',
        'region_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'year' => 'integer',
        'region_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // `updating` fires only for a row that already exists, so building a subject and
        // setting all three fields before the first save is naturally allowed — there is
        // no persisted identity yet for anything to contradict.
        static::updating(function (BestForSubject $subject) {
            $changed = array_values(array_filter(
                self::IMMUTABLE_IDENTITY,
                fn (string $attribute) => $subject->isDirty($attribute)
            ));

            if ($changed === []) {
                return;
            }

            throw new DomainException(sprintf(
                'Best For subject #%s is persisted; %s immutable identity. Create the correct subject instead.',
                $subject->getKey(),
                count($changed) === 1
                    ? sprintf('"%s" is', $changed[0])
                    : sprintf('"%s" are', implode('", "', $changed))
            ));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** The region this collection is published for. GLOBAL is an ordinary region row. */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
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
