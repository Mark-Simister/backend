<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin SEO-publishing state on videos (Phase 4). review_slug is the stable,
 * immutable public URL slug generated once at first "Publish to SEO"; the
 * seo_* fields expose publish state back to the admin panel.
 * Guarded against re-run in case of a partial migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'review_slug')) {
                $table->string('review_slug')->nullable()->unique();
            }
            if (! Schema::hasColumn('videos', 'seo_publish_status')) {
                $table->string('seo_publish_status')->default('unpublished');
            }
            if (! Schema::hasColumn('videos', 'seo_published_at')) {
                $table->timestamp('seo_published_at')->nullable();
            }
            if (! Schema::hasColumn('videos', 'seo_last_published_at')) {
                $table->timestamp('seo_last_published_at')->nullable();
            }
            if (! Schema::hasColumn('videos', 'seo_publish_error')) {
                $table->text('seo_publish_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // drop the unique index explicitly before the column (portable)
            if (Schema::hasColumn('videos', 'review_slug')) {
                $table->dropUnique(['review_slug']);
            }
            $table->dropColumn(array_values(array_filter([
                'review_slug', 'seo_publish_status', 'seo_published_at',
                'seo_last_published_at', 'seo_publish_error',
            ], fn ($c) => Schema::hasColumn('videos', $c))));
        });
    }
};
