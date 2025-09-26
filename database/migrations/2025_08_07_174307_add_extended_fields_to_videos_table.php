<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            // Product Info
            $table->string('product_name')->nullable();
            $table->string('product_asin_sku')->nullable();
            $table->float('public_rating')->nullable();
            $table->float('character_score')->nullable();
            $table->float('editorial_score')->nullable();
            $table->float('final_beastie_score')->nullable();
            $table->string('product_thumbnail')->nullable();

            // review_details

            // Video Info
            $table->string('video_type')->nullable();
            $table->json('video_platforms')->nullable();
            $table->string('youtube_id')->nullable();
            $table->string('wistia_id')->nullable();
            $table->string('raw_video_path')->nullable();
            $table->string('caption_file')->nullable();
            $table->string('thumbnail_image')->nullable(); // ← your requested field

            // Workflow & Status Flags
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_ai_generated')->default(false);
            $table->boolean('is_finalized')->default(false);
            $table->boolean('qa_passed')->default(false);
            $table->timestamp('post_schedule_at')->nullable();

            // Review Type & Sponsorship
            $table->enum('review_type', ['rating', 'review'])->nullable();
            $table->boolean('sponsored')->default(false);

            // SEO & Social Sharing
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->longText('hashtags')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('open_graph_image')->nullable();
            
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
        });
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'product_name',
                'product_asin_sku',
                'public_rating',
                'character_score',
                'editorial_score',
                'final_beastie_score',
                'product_thumbnail',

                'video_type',
                'video_platforms',
                'youtube_id',
                'wistia_id',
                'raw_video_path',
                'caption_file',
                'thumbnail_image',

                'is_draft',
                'is_ai_generated',
                'is_finalized',
                'qa_passed',
                'post_schedule_at',

                'review_type',
                'sponsored',

                'seo_title',
                'seo_description',
                'hashtags',
                'cta_text',
                'open_graph_image',
                'twitter_title',
                'twitter_description',
            ]);
        });
    }
};
