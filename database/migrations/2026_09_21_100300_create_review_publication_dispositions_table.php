<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_publication_dispositions — mutable operational state about a review,
 * keyed by its published_review_id.
 *
 * This lives in its own table rather than on `published_review_payloads`
 * because that table is rewritten wholesale by
 * PublishedReviewPayloadSeeder::updateOrCreate, so a DB-only update there is
 * reverted by the next import (see config/reviews.php). Disposition must
 * survive the import.
 *
 * THE WITHDRAWAL CONTRADICTION THIS SCHEMA AVOIDS.
 * An actual withdrawal is identified by exactly one thing: `withdrawn_at IS
 * NOT NULL`. `withdrawal_class` is therefore nullable and carries NO DEFAULT.
 * "Material" is derived at read time when a withdrawal exists but is
 * unclassified or unconfirmed — it is never written by a column default. A row
 * whose only non-default value is `availability_state` describes a temporary
 * availability problem and is not a withdrawal of any kind.
 *
 * Effective treatment (App\Models\ReviewPublicationDisposition):
 *   withdrawn_at NULL                                  → no withdrawal event
 *   withdrawn_at set, class NULL or unconfirmed        → material, pending human disposition
 *   withdrawn_at set, class confirmed                  → that class governs
 *
 * `validity_state` and `availability_state` are orthogonal to withdrawal and
 * to each other, and change independently.
 *
 * No created_at/updated_at: `disposition_at` is the audit time this table
 * needs, and the model sets $timestamps = false to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_publication_dispositions', function (Blueprint $table) {
            // Identity is the review key itself. Stable across payload rewrites.
            $table->string('published_review_id')->primary();

            // The sole existence test for a withdrawal event.
            $table->timestamp('withdrawn_at')->nullable();

            // Meaningful only when withdrawn_at is set. No default, by design.
            $table->enum('withdrawal_class', [
                'ordinary_editorial',
                'material',
                'temporary_technical',
            ])->nullable();

            $table->boolean('classification_confirmed')->default(false);
            $table->text('withdrawal_reason')->nullable();    // human-authored

            $table->enum('validity_state', ['valid', 'materially_invalid'])->default('valid');
            $table->enum('availability_state', ['available', 'temporarily_unavailable'])->default('available');

            $table->timestamp('disposition_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_publication_dispositions');
    }
};
