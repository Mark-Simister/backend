<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * best_for_subjects — the stable address of a Best For selection: one category,
 * one year, one region. A subject is pure identity. It holds no state and no
 * pointer.
 *
 * REGION IS PART OF IDENTITY, not an attribute of it. `Best Dog Beds 2026` on
 * the AU host and on the US host are different collections with different
 * selections and independent edition histories, so they are different subjects.
 * Region lives here and nowhere else: an edition inherits it from its subject,
 * which is why `best_for_editions` and `best_for_edition_selections` carry no
 * region column and cannot disagree with this one.
 *
 * A GLOBAL subject and a region-specific subject for the same category/year may
 * BOTH exist. That is deliberate and is not an error state — which one a host
 * serves is a later renderer precedence rule (exact region, else GLOBAL, else
 * 404), not a constraint on what may be stored.
 *
 * `market_id` on published_review_payloads plays no part in any of this. It is
 * nullable free text mirrored from an external sheet, with no reference table
 * and no consumer, so it cannot carry identity.
 *
 * Deliberately absent: any `current_edition_id` / `is_current` column. Which
 * edition is currently recommended is represented exactly once, on the edition
 * side (`best_for_editions.current_for_subject_id`), so two mechanisms can
 * never disagree. See the editions migration.
 *
 * A subject may legitimately exist with zero editions (before first
 * publication) and with no current edition (after a severe unpublication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_for_subjects', function (Blueprint $table) {
            $table->id();

            // Stable category identity. Restrict, not cascade: deleting a
            // category must never silently destroy published editorial records.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            $table->unsignedSmallInteger('year');

            // Stable regional identity, NOT NULL. Nullable-as-global was rejected:
            // neither MySQL nor SQLite collides NULLs in a unique index, so a null
            // region would let unlimited duplicate (category, year, NULL) subjects
            // exist — a hole in a permanent public address. A global collection
            // points at the regions row whose region_code is GLOBAL, like any other.
            // Restrict, not cascade, for the same reason as category_id.
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();

            $table->timestamps();

            // One subject per category/year/region. Named explicitly and kept short:
            // the generated name would be best_for_subjects_category_id_year_region_id_unique
            // (54 characters) — under MySQL's 64-character limit, but this table's
            // sibling already had to be corrected for exactly that class of problem,
            // so the name is pinned rather than left to change with the columns.
            //
            // region_id needs no separate index: it is the LAST column here so this
            // index cannot serve the foreign key, and MySQL creates the FK's index
            // implicitly. category_id is the leading column, so the FK reuses this one.
            $table->unique(['category_id', 'year', 'region_id'], 'bfs_category_year_region_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_for_subjects');
    }
};
