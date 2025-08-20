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
            // Tags / metadata
        //    $table->json('tags')->nullable()->after('affiliate_link');
           // $table->enum('rating_type', ['rating', 'review'])->default('rating')->after('tags');
           // $table->enum('sponsorship_type', ['sponsored', 'unsponsored'])->default('unsponsored')->after('rating_type');
           // $table->json('highlight_tags')->nullable()->after('sponsorship_type');
           // $table->json('auto_tags')->nullable()->after('highlight_tags');
            // $table->enum('video_type', ['short', 'full_review', 'reel', 'live', 'compilation'])->default('short')->after('auto_tags');
            $table->json('video_platforms')->nullable()->after('video_type');

            // Files
            $table->string('raw_video_file')->nullable()->after('video_platforms');
            $table->string('caption_file')->nullable()->after('raw_video_file');

            // Workflow
            $table->enum('status', ['draft', 'published'])->default('draft')->after('caption_file');
            $table->boolean('is_ai_generated')->default(false)->after('status');
            $table->boolean('is_finalized')->default(false)->after('is_ai_generated');
            $table->boolean('is_qa_passed')->default(false)->after('is_finalized');
            $table->timestamp('post_schedule_at')->nullable()->after('is_qa_passed');

            // SEO & SM
            $table->string('seo_title')->nullable()->after('post_schedule_at');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->json('hashtags')->nullable()->after('seo_description');
            $table->string('cta_text')->nullable()->after('hashtags');
            $table->string('og_image_url')->nullable()->after('cta_text');
            $table->string('twitter_title')->nullable()->after('og_image_url');
            $table->text('twitter_description')->nullable()->after('twitter_title');
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
