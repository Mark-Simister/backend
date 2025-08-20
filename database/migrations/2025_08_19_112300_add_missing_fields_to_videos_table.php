<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            // Add the missing fields for auto-tagging logic
            $table->decimal('original_price', 10, 2)->nullable(); // Original price of the product
            $table->unsignedInteger('views')->default(0); // Number of views
            $table->unsignedInteger('likes')->default(0); // Number of likes
            $table->dateTime('sale_end_date')->nullable(); // Date when the sale ends
           // $table->decimal('final_beastie_score', 5, 2)->nullable(); // Final BeastieScore
           // $table->decimal('editorial_score', 3, 2)->nullable(); // Editorial score
            $table->boolean('is_amazon_choice')->default(false); // Is Amazon Choice
        });
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            // Drop the columns if we need to rollback
            $table->dropColumn('original_price');
            $table->dropColumn('views');
            $table->dropColumn('likes');
            $table->dropColumn('sale_end_date');
           // $table->dropColumn('final_beastie_score');
           // $table->dropColumn('editorial_score');
            $table->dropColumn('is_amazon_choice');
        });
    }
};
