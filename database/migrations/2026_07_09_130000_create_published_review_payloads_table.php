<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published_Review_Payloads (from the Production OS spreadsheet) → the source
 * of truth for public, SEO-friendly review pages served at /review/{slug}.
 * The pipeline pre-builds review_page_json + schema_json_ld, so the public
 * controller mostly decodes and emits these rather than recomputing.
 * 36 columns, mirroring the sheet exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_review_payloads', function (Blueprint $table) {
            $table->id();

            // Identity / routing
            $table->string('published_review_id')->unique();
            $table->string('website_review_id')->nullable()->index();
            $table->string('project_id')->nullable();
            $table->string('product_uid')->nullable()->index();
            $table->string('market_id')->nullable()->index();
            $table->string('language_variant')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('channel')->nullable();
            $table->string('character_id')->nullable();
            $table->string('character_name')->nullable();
            $table->string('review_slug')->unique();       // public URL key
            $table->text('canonical_url')->nullable();

            // Headline / SEO
            $table->text('h1')->nullable();
            $table->text('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('og_image_url')->nullable();
            $table->text('hero_image_url')->nullable();

            // Scores
            $table->decimal('final_beastie_score', 4, 2)->nullable();
            $table->decimal('public_score', 4, 2)->nullable();
            $table->unsignedInteger('public_rating_count')->nullable();
            $table->unsignedInteger('analysed_evidence_count')->nullable();
            $table->unsignedInteger('source_count')->nullable();
            $table->string('confidence_tier')->nullable();

            // Lifecycle / QA
            $table->string('status')->nullable();
            $table->string('publish_status')->nullable()->index();
            $table->string('qa_status')->nullable();
            $table->longText('qa_errors_json')->nullable();
            $table->longText('qa_warnings_json')->nullable();

            // Pre-built payloads (primary display + structured data source)
            $table->longText('review_page_json')->nullable();
            $table->longText('schema_json_ld')->nullable();
            $table->longText('commerce_json')->nullable();
            $table->longText('sources_json')->nullable();
            $table->longText('safety_json')->nullable();
            $table->longText('disclosure_json')->nullable();

            // Sheet-provided timestamps (kept as-is)
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_review_payloads');
    }
};
