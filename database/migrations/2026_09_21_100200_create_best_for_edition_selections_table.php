<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * best_for_edition_selections — the five entries of a Best For edition.
 *
 * Every column here is editorial. There are no operational columns, because a
 * selection is a SNAPSHOT: what was shown to the reader at publication, owned
 * by the edition and never re-read from a live source. That matters because
 * `published_review_payloads` is rewritten wholesale by
 * PublishedReviewPayloadSeeder::updateOrCreate — anything this table needed to
 * display but did not copy would silently change underneath a published page.
 *
 * `published_review_id` is kept for traceability and live lookups only. It is
 * a mutable row key in the payload read model, so it is NOT a foreign key and
 * must never be the source of displayed values.
 *
 * Deliberately absent, and not to be added here:
 *   product_id / edition_product_key — no canonical product identity exists
 *       yet. Distinctness beyond "no duplicate published_review_id in one
 *       edition" is a publication-gate concern resolved by deterministic
 *       comparison where source identifiers exist, and by human confirmation
 *       where they do not. An operator-assigned key would only catch the
 *       duplicate the operator had already noticed, while claiming a machine
 *       certainty the source model cannot provide.
 *   analysis_depth / analysis_depth_count / analysis_depth_basis /
 *   score_basis_summary / confidence_tier — not rendered on Best For.
 *   price / retailer URL / availability / retailer position — live commerce,
 *       never frozen into an editorial snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_for_edition_selections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')->constrained('best_for_editions')->cascadeOnDelete();

            $table->unsignedSmallInteger('position');         // approved sequence, 1–5

            // Traceability key into the payload read model. Not an FK: the
            // payload row it names can be rewritten or replaced.
            $table->string('published_review_id');

            // ---- Snapshot: displayed exactly as published ------------------
            $table->string('review_slug_as_published');
            $table->string('product_name_as_published');
            $table->text('product_image_ref_as_published')->nullable();
            $table->string('source_product_identifier')->nullable(); // ASIN/SKU/product_uid as seen — traceability only
            $table->string('superlative');
            $table->text('selection_reason');
            $table->decimal('beastie_score_as_published', 4, 2);
            $table->decimal('public_rating_as_published', 4, 2);
            $table->unsignedInteger('public_rating_count_as_published');
            $table->string('public_signal_as_published');

            $table->timestamps();

            $table->unique(['edition_id', 'position']);
            $table->unique(['edition_id', 'published_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_for_edition_selections');
    }
};
