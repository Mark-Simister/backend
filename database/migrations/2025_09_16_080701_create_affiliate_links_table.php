<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();

            // Remove foreign keys
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('region_id');

            $table->string('retailer');
            $table->string('url');
            $table->timestamps();

            // Ensuring a unique combination of video and region
            $table->unique(['video_id', 'region_id', 'retailer']);
        });

    }

    public function down()
    {
        Schema::dropIfExists('affiliate_links');
    }
};
