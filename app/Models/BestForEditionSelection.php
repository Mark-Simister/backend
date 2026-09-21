<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the five entries of a Best For edition.
 *
 * Every column is editorial, because a selection is a snapshot of what the
 * reader was shown at publication. Nothing here is re-read from a live source
 * at render time — the payload read model it came from is rewritten wholesale
 * on every import, so a value this row did not copy is a value that can change
 * underneath a published page.
 *
 * Once the parent edition is published, this model refuses all three ways of
 * altering the set: changing a row, adding a row, and deleting a row. The
 * fourth way — deleting the edition and letting the cascade take the rows —
 * is refused by BestForEdition.
 *
 * `published_review_id` is a traceability key, not a foreign key: the payload
 * row it names is mutable and can be replaced by an import.
 *
 * Same limit as BestForEdition: these are Eloquent model events, so they cover
 * model-instance writes including forceFill, but not query-builder updates,
 * raw SQL, or withoutEvents.
 */
class BestForEditionSelection extends Model
{
    protected $table = 'best_for_edition_selections';

    /**
     * Frozen once the parent edition is published — which is every column
     * except the surrogate key and Laravel's own timestamps.
     */
    public const IMMUTABLE_AFTER_PUBLICATION = [
        'edition_id',
        'position',
        'published_review_id',
        'review_slug_as_published',
        'product_name_as_published',
        'product_image_ref_as_published',
        'source_product_identifier',
        'superlative',
        'selection_reason',
        'beastie_score_as_published',
        'public_rating_as_published',
        'public_rating_count_as_published',
        'public_signal_as_published',
    ];

    protected $fillable = self::IMMUTABLE_AFTER_PUBLICATION;

    protected $casts = [
        'edition_id' => 'integer',
        'position' => 'integer',
        'beastie_score_as_published' => 'float',
        'public_rating_as_published' => 'float',
        'public_rating_count_as_published' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (BestForEditionSelection $selection) {
            if ($selection->editionIsPublished($selection->edition_id)) {
                throw new DomainException(
                    'Cannot add a selection to published Best For edition #' . $selection->edition_id
                    . '. Publish a new edition instead.'
                );
            }
        });

        static::updating(function (BestForEditionSelection $selection) {
            // Check both ends: moving a row out of a published edition, and
            // moving one into a published edition, are both edits to a
            // published edition's set.
            $publishedBefore = $selection->editionIsPublished($selection->getRawOriginal('edition_id'));
            $publishedAfter = $selection->editionIsPublished($selection->edition_id);

            if (! $publishedBefore && ! $publishedAfter) {
                return;
            }

            foreach (self::IMMUTABLE_AFTER_PUBLICATION as $attribute) {
                if ($selection->isDirty($attribute)) {
                    throw new DomainException(sprintf(
                        'Selection #%s belongs to a published Best For edition; "%s" is immutable.',
                        $selection->getKey(),
                        $attribute
                    ));
                }
            }
        });

        static::deleting(function (BestForEditionSelection $selection) {
            $editionId = $selection->getRawOriginal('edition_id') ?? $selection->edition_id;

            if ($selection->editionIsPublished($editionId)) {
                throw new DomainException(
                    'Cannot delete a selection from published Best For edition #' . $editionId . '.'
                );
            }
        });
    }

    /**
     * Asked of the database rather than the loaded relation: the relation may
     * be stale or absent at the moment a guard runs, and a stale "draft"
     * answer would silently open the edition back up.
     */
    public function editionIsPublished(mixed $editionId): bool
    {
        if ($editionId === null) {
            return false;
        }

        return BestForEdition::query()
            ->whereKey($editionId)
            ->whereNotNull('published_at')
            ->exists();
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(BestForEdition::class, 'edition_id');
    }
}
