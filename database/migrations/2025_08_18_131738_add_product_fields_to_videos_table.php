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
            // Add new fields
            // $table->string('product_name')->nullable()->after('description'); // Product Name
            // $table->string('product_asin_sku')->nullable()->after('product_name'); // Product ASIN / SKU
           // $table->string('affiliate_link')->nullable()->after('product_asin_sku'); // Affiliate Link
            // $table->decimal('public_rating', 3, 1)->nullable()->after('affiliate_link'); // Public Rating
            // $table->decimal('character_score', 5, 2)->nullable()->after('public_rating'); // Character Score
            // $table->decimal('editorial_score', 5, 2)->nullable()->after('character_score'); // Editorial Score
            // $table->decimal('final_beastiescore', 5, 2)->nullable()->after('editorial_score'); // Final BeastieScore
            //$table->string('product_thumbnail')->nullable()->after('final_beastiescore'); // Product Thumbnail
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Drop the added fields if rolling back the migration
            // $table->dropColumn([
            //     'product_name',
            //     'product_asin_sku',
            //     'affiliate_link',
            //     'public_rating',
            //     'character_score',
            //     'editorial_score',
            //     'final_beastiescore',
            //     'product_thumbnail',
            // ]);
        });
    }
};
