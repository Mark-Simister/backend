<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * best_for_editions — one published (or draft) Best For edition of a subject.
 *
 * The table carries two planes that must not be confused:
 *
 *  IMMUTABLE EDITORIAL — frozen the moment `published_at` is set. What was
 *  published stays published, including the category name and year *as they
 *  read at publication*, so a later category rename cannot rewrite history.
 *  Enforced by App\Models\BestForEdition, not by triggers.
 *
 *  MUTABLE OPERATIONAL — how the edition is treated now. Three orthogonal
 *  concepts, deliberately not collapsed into one overloaded "disposition"
 *  column, so contradictory combinations cannot be expressed:
 *      publication_state    live | removed        (removed supersedes the rest)
 *      recommendation_state active | suspended
 *      indexing_state       indexable | noindex
 *
 * CURRENT EDITION. `current_for_subject_id` is the single mechanism. It is
 * nullable and UNIQUE, so "at most one current edition per subject" is
 * enforced by the schema on both MySQL and SQLite (neither collides NULLs),
 * with no partial index and no circular foreign key between subjects and
 * editions — a circularity SQLite could not resolve, since it cannot add a
 * foreign key to an existing table.
 *
 * The companion invariant — a non-null `current_for_subject_id` must equal the
 * edition's own `subject_id` — is enforced in the model. A DB CHECK is
 * deliberately NOT used: Laravel has no portable CHECK builder, it would need
 * engine-specific raw SQL, and it is silently ignored on MySQL below 8.0.16,
 * which would make the protection a matter of server version. The model guard
 * is authoritative on every engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_for_editions', function (Blueprint $table) {
            $table->id();

            // ---- Immutable editorial identity -----------------------------
            $table->string('public_edition_key')->unique();   // permanent, never reused
            $table->foreignId('subject_id')->constrained('best_for_subjects')->restrictOnDelete();
            $table->unsignedSmallInteger('edition_sequence'); // 1, 2, 3 … within the subject
            $table->string('category_name_as_published');
            $table->unsignedSmallInteger('year_as_published');

            // NULL = draft. Setting it IS the publication event and freezes the
            // editorial fields above and every selection row below.
            $table->timestamp('published_at')->nullable();

            $table->text('methodology_text');                 // mandatory visible basis
            $table->string('methodology_version')->nullable();
            $table->string('video_asset_id');                 // curated Vimeo identity

            // ---- Mutable operational --------------------------------------
            $table->timestamp('video_consistency_verified_at')->nullable();

            $table->foreignId('current_for_subject_id')
                ->nullable()
                ->constrained('best_for_subjects')
                ->restrictOnDelete();

            $table->enum('publication_state', ['live', 'removed'])->default('live');
            $table->enum('recommendation_state', ['active', 'suspended'])->default('active');
            $table->enum('indexing_state', ['indexable', 'noindex'])->default('indexable');

            $table->text('operational_reason')->nullable();   // human-authored
            $table->timestamp('operational_at')->nullable();
            $table->foreignId('operational_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['subject_id', 'edition_sequence']);

            // At most one current edition per subject, on both engines: neither
            // MySQL nor SQLite collides NULLs in a unique index, so every
            // non-current edition sits out of the constraint for free. Declared
            // here rather than fluently on the column so the unique index is
            // created with the table and MySQL reuses it for the foreign key.
            $table->unique('current_for_subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('best_for_editions');
    }
};
