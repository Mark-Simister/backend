<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // FU-2: six of these seven columns already exist on the long-lived staging
            // database while this migration is still Pending, so a bare `php artisan
            // migrate` dies here on the first duplicate column and never reaches any
            // later migration. Each addition is guarded individually, in the same way
            // as add_tag_ids_to_videos_table and add_seo_fields_to_videos_table, so a
            // fresh database still receives all seven with their intended definitions
            // while staging receives only the ones it is actually missing. The guards
            // check existence only; no column definition is changed.

            // Tags / metadata
            if (! Schema::hasColumn('videos', 'tags')) {
                $table->json('tags')->nullable()->after('affiliate_link');
            }
            if (! Schema::hasColumn('videos', 'rating_type')) {
                $table->enum('rating_type', ['rating', 'review'])->default('rating')->after('tags');
            }
            if (! Schema::hasColumn('videos', 'sponsorship_type')) {
                $table->enum('sponsorship_type', ['sponsored', 'unsponsored'])->default('unsponsored')->after('rating_type');
            }
            if (! Schema::hasColumn('videos', 'highlight_tags')) {
                $table->json('highlight_tags')->nullable()->after('sponsorship_type');
            }
            if (! Schema::hasColumn('videos', 'auto_tags')) {
                $table->json('auto_tags')->nullable()->after('highlight_tags');
            }
            // $table->enum('video_type', ['short', 'full_review', 'reel', 'live', 'compilation'])->default('short')->after('auto_tags');
           // $table->json('video_platforms')->nullable()->after('video_type');

            // Files
            if (! Schema::hasColumn('videos', 'raw_video_file')) {
                $table->string('raw_video_file')->nullable()->after('video_platforms');
            }
            //$table->string('caption_file')->nullable()->after('raw_video_file');

            // Workflow
            if (! Schema::hasColumn('videos', 'status')) {
                $table->enum('status', ['draft', 'published'])->default('draft')->after('caption_file');
            }
            // $table->boolean('is_ai_generated')->default(false)->after('status');
            //$table->boolean('is_finalized')->default(false)->after('is_ai_generated');
          //  $table->boolean('is_qa_passed')->default(false)->after('is_finalized');
          //  $table->timestamp('post_schedule_at')->nullable()->after('is_qa_passed');

            // SEO & SM
            // $table->string('seo_title')->nullable()->after('post_schedule_at');
            // $table->text('seo_description')->nullable()->after('seo_title');
            // $table->longText('hashtags')->nullable()->after('seo_description');
            // $table->string('cta_text')->nullable()->after('hashtags');
            // $table->string('og_image_url')->nullable()->after('cta_text');
            // $table->string('twitter_title')->nullable()->after('og_image_url');
            // $table->text('twitter_description')->nullable()->after('twitter_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'tags',
                'rating_type',
               // 'sponsorship_type',
               // 'highlight_tags',
                'auto_tags',
              //  'video_type',
                'video_platforms',
                'raw_video_file',
                'caption_file',
                'status',
                'is_ai_generated',
                'is_finalized',
                'is_qa_passed',
                'post_schedule_at',
                'seo_title',
                'seo_description',
                'hashtags',
                'cta_text',
                'og_image_url',
                'twitter_title',
                'twitter_description',
            ]);
        });
    }
};
