<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - the Admin key of two-key publication
 * (design record sections 4 and 12.4, `review_editorial_records`).
 *
 * One row per pipeline review, keyed by the stable review identity. Like
 * review_publication_dispositions and best_for_edition_selections, published_review_id
 * is NOT a foreign key: it is a mutable row key in the payload read model, which a
 * routine import rewrites. No row means "not released".
 *
 * The bound fingerprints are the human-approved release binding; public eligibility
 * (later sprints) requires the actual current projection to match them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_editorial_records', function (Blueprint $table) {
            $table->string('published_review_id', 191)->primary();

            $table->foreignId('channel_id')->nullable()->constrained('channels')->restrictOnDelete();
            $table->foreignId('character_id')->nullable()->constrained('characters')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->timestamp('associations_confirmed_at')->nullable();
            $table->foreignId('associations_confirmed_by')->nullable()
                ->constrained('users', 'id', 'rer_assoc_confirmed_by_fk')->nullOnDelete();

            $table->enum('editorial_state', ['pending_review', 'returned_for_correction', 'qa_passed', 'released'])
                ->default('pending_review');
            $table->char('bound_content_fingerprint', 64)->nullable();
            $table->char('bound_media_fingerprint', 64)->nullable();
            $table->string('bound_fingerprint_scheme', 32)->nullable();

            $table->text('state_reason')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->foreignId('state_changed_by')->nullable()
                ->constrained('users', 'id', 'rer_state_changed_by_fk')->nullOnDelete();

            $table->timestamp('hold_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->foreignId('hold_by')->nullable()->constrained('users', 'id', 'rer_hold_by_fk')->nullOnDelete();

            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index('editorial_state', 'rer_editorial_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_editorial_records');
    }
};
