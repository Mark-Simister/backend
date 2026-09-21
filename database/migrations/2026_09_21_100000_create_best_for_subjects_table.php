<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * best_for_subjects — the stable address of a Best For selection: one category,
 * one year. A subject is pure identity. It holds no state and no pointer.
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

            $table->timestamps();

            // One subject per category/year. This is the whole point of the table.
            $table->unique(['category_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_for_subjects');
    }
};
