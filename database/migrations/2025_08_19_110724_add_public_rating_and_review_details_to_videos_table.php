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
            // Add public_rating column
           // $table->decimal('public_rating', 3, 1)->nullable()->after('affiliate_link'); // Adjust `after` as needed
            
            // Add review_details column
            $table->text('review_details')->nullable()->after('public_rating'); // Adjust `after` as needed
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            // Drop the columns if the migration is rolled back
         //   $table->dropColumn('public_rating');
            $table->dropColumn('review_details');
        });
    }
};
