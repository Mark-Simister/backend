<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One edition of a Best For subject — draft while `published_at` is null,
 * permanent once it is set.
 *
 * PUBLICATION IS THE FREEZE. Setting `published_at` is the publication event.
 * From that save onward this model refuses to change any editorial field, and
 * BestForEditionSelection refuses to change, add or remove any of its
 * selections. A correction is a new edition, never an edit to a published one.
 *
 * The operational fields stay writable forever: they describe how the edition
 * is treated now, which is a different question from what was published.
 * They are three orthogonal concepts rather than one overloaded status, so
 * "removed but indexable" cannot be expressed as a contradiction — at render
 * time `removed` supersedes the other two.
 *
 * CURRENT EDITION. `current_for_subject_id` is the one and only mechanism. The
 * schema's UNIQUE on that column makes two current editions per subject
 * impossible on both engines. This model adds the two rules a unique index
 * cannot express: the pointer must name the edition's own subject, and a draft
 * cannot be current.
 *
 * LIMIT OF THIS GUARD. It is enforced through Eloquent model events, so it
 * covers save/update/delete on a model instance, including forceFill. It does
 * NOT cover query-builder writes (Model::query()->update(), DB::table(...)),
 * raw SQL, or saveQuietly/withoutEvents. That is the accepted trade-off for
 * not using database triggers, which would need separate MySQL and SQLite
 * implementations. Publication and disposition must go through model
 * instances.
 */
class BestForEdition extends Model
{
    protected $table = 'best_for_editions';

    public const PUBLICATION_LIVE = 'live';
    public const PUBLICATION_REMOVED = 'removed';

    public const RECOMMENDATION_ACTIVE = 'active';
    public const RECOMMENDATION_SUSPENDED = 'suspended';

    public const INDEXING_INDEXABLE = 'indexable';
    public const INDEXING_NOINDEX = 'noindex';

    /** Frozen the moment `published_at` is set. */
    public const IMMUTABLE_AFTER_PUBLICATION = [
        'public_edition_key',
        'subject_id',
        'edition_sequence',
        'category_name_as_published',
        'year_as_published',
        'published_at',
        'methodology_text',
        'methodology_version',
        'video_asset_id',
    ];

    /** Writable for the life of the edition, published or not. */
    public const OPERATIONALLY_MUTABLE = [
        'current_for_subject_id',
        'publication_state',
        'recommendation_state',
        'indexing_state',
        'operational_reason',
        'operational_at',
        'operational_by',
        'video_consistency_verified_at',
        'manual_recommendation_suspended_at',
        'manual_noindex_at',
    ];

    protected $fillable = [
        'public_edition_key',
        'subject_id',
        'edition_sequence',
        'category_name_as_published',
        'year_as_published',
        'published_at',
        'methodology_text',
        'methodology_version',
        'video_asset_id',
        'video_consistency_verified_at',
        'current_for_subject_id',
        'publication_state',
        'recommendation_state',
        'indexing_state',
        'operational_reason',
        'operational_at',
        'operational_by',
        'manual_recommendation_suspended_at',
        'manual_noindex_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'edition_sequence' => 'integer',
        'year_as_published' => 'integer',
        'published_at' => 'datetime',
        'video_consistency_verified_at' => 'datetime',
        'current_for_subject_id' => 'integer',
        'operational_at' => 'datetime',
        'operational_by' => 'integer',
        'manual_recommendation_suspended_at' => 'datetime',
        'manual_noindex_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BestForEdition $edition) {
            $edition->allocatePermanentIdentityIfMissing();
            $edition->assertCurrentPointerIsCoherent();
        });

        static::updating(function (BestForEdition $edition) {
            $edition->assertCurrentPointerIsCoherent();

            // A draft is freely editable. Publication is the freeze, and the
            // save that sets `published_at` is itself allowed to set the rest.
            if (! $edition->wasPublishedBeforeThisSave()) {
                return;
            }

            foreach (self::IMMUTABLE_AFTER_PUBLICATION as $attribute) {
                if ($edition->isDirty($attribute)) {
                    throw new DomainException(sprintf(
                        'Best For edition %s is published; "%s" is immutable. Publish a new edition instead.',
                        $edition->public_edition_key ?? ('#' . $edition->getKey()),
                        $attribute
                    ));
                }
            }
        });

        // Not in the brief's list, but required to make the selection guard
        // real: best_for_edition_selections cascades on edition delete, so
        // without this, deleting a published edition would erase exactly the
        // selections BestForEditionSelection refuses to let anyone delete.
        static::deleting(function (BestForEdition $edition) {
            if ($edition->wasPublishedBeforeThisSave()) {
                throw new DomainException(sprintf(
                    'Best For edition %s is published and cannot be deleted. Use the operational state fields instead.',
                    $edition->public_edition_key ?? ('#' . $edition->getKey())
                ));
            }
        });
    }

    /**
     * Allocate the two identity columns the schema requires on EVERY persisted row,
     * including a draft: `public_edition_key` is NOT NULL UNIQUE, and
     * `edition_sequence` is NOT NULL and UNIQUE within the subject. Neither can wait
     * for publication, so both are assigned once here, at creation. Publication only
     * validates that they are present — it never reassigns them.
     *
     * `public_edition_key` is an OPAQUE permanent identifier: a ULID, deliberately not
     * a slug and not a category/year routing rule. The eventual public URL is a
     * separate later decision and must not be derived from this value.
     *
     * An explicitly supplied value always wins, so callers keep full control and the
     * uniqueness constraints stay directly observable in tests.
     */
    protected function allocatePermanentIdentityIfMissing(): void
    {
        if (blank($this->public_edition_key)) {
            $this->public_edition_key = (string) Str::ulid();
        }

        if ($this->edition_sequence === null && $this->subject_id !== null) {
            $this->edition_sequence = 1 + (int) static::query()
                ->where('subject_id', $this->subject_id)
                ->max('edition_sequence');
        }
    }

    /** Published as the database currently has it, ignoring unsaved changes. */
    public function wasPublishedBeforeThisSave(): bool
    {
        return $this->exists && $this->getRawOriginal('published_at') !== null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isCurrent(): bool
    {
        return $this->current_for_subject_id !== null;
    }

    /**
     * The two current-edition rules a UNIQUE index cannot express.
     *
     * The subject match is enforced here rather than by a database CHECK:
     * Laravel has no portable CHECK builder, and MySQL below 8.0.16 parses
     * CHECK and then ignores it, which would make the rule depend on the
     * server version. This runs everywhere.
     */
    protected function assertCurrentPointerIsCoherent(): void
    {
        if ($this->current_for_subject_id === null) {
            return;
        }

        if ((int) $this->current_for_subject_id !== (int) $this->subject_id) {
            throw new DomainException(sprintf(
                'A Best For edition can only be current for its own subject (edition subject %s, pointer %s).',
                var_export($this->subject_id, true),
                var_export($this->current_for_subject_id, true)
            ));
        }

        if ($this->published_at === null) {
            throw new DomainException(
                'A draft Best For edition cannot be marked current. Set published_at first.'
            );
        }
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(BestForSubject::class, 'subject_id');
    }

    /** The subject this edition is currently the recommendation for, if any. */
    public function currentForSubject(): BelongsTo
    {
        return $this->belongsTo(BestForSubject::class, 'current_for_subject_id');
    }

    /** The five entries, in approved order. */
    public function selections(): HasMany
    {
        return $this->hasMany(BestForEditionSelection::class, 'edition_id')->orderBy('position');
    }

    /** Who last changed the operational state. */
    public function operationalBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operational_by');
    }
}
