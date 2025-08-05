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
        Schema::table('characters', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->integer('age')->nullable();
            $table->string('species')->nullable();
            $table->string('style_vibe')->nullable();

            $table->integer('durability_score')->nullable();
            $table->text('durability_notes')->nullable();

            $table->integer('comfort_score')->nullable();
            $table->text('comfort_notes')->nullable();

            $table->integer('style_score')->nullable();
            $table->text('style_notes')->nullable();

            $table->integer('affordability_score')->nullable();
            $table->text('affordability_notes')->nullable();

            $table->integer('tech_feature_score')->nullable();
            $table->text('tech_feature_notes')->nullable();

            $table->integer('eco_friendliness_score')->nullable();
            $table->text('eco_friendliness_notes')->nullable();

            $table->integer('engagement_score')->nullable();
            $table->text('engagement_notes')->nullable();

            $table->integer('ease_of_use_score')->nullable();
            $table->text('ease_of_use_notes')->nullable();

            $table->integer('performance_score')->nullable();
            $table->text('performance_notes')->nullable();

            $table->integer('brand_reputation_score')->nullable();
            $table->text('brand_reputation_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'location', 'age', 'species', 'style_vibe',
                'durability_score', 'durability_notes',
                'comfort_score', 'comfort_notes',
                'style_score', 'style_notes',
                'affordability_score', 'affordability_notes',
                'tech_feature_score', 'tech_feature_notes',
                'eco_friendliness_score', 'eco_friendliness_notes',
                'engagement_score', 'engagement_notes',
                'ease_of_use_score', 'ease_of_use_notes',
                'performance_score', 'performance_notes',
                'brand_reputation_score', 'brand_reputation_notes'
            ]);
        });
    }
};
