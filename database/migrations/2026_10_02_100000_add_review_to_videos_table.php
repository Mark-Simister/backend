<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `videos.review` is the structured review content the payload pipeline reads —
     * Video array-casts it, ReviewPayloadPublisher, ReviewSchemaBuilder and
     * VideoReviewMapper all read $video->review, and the tests persist arrays into
     * it — but no migration ever created the column, so it is absent on a fresh
     * database and absent on staging. This forward migration closes that gap.
     *
     * Nullable JSON with no index or constraint, matching the other structured
     * array-cast columns on this table (tags, highlight_tags, auto_tags). The
     * existence guard follows the pattern already used by add_tag_ids_to_videos_table
     * and add_seo_fields_to_videos_table, so the migration is safe anywhere the
     * column has already been added out of band.
     */
    public function up(): void
    {
        if (Schema::hasColumn('videos', 'review')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->json('review')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('videos', 'review')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('review');
        });
    }
};
