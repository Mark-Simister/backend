<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('similar_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id'); // link to video, no FK
            $table->unsignedBigInteger('region_id')->default(0); // link to region, no FK
            $table->string('name', 255);
            $table->string('short_description', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('image', 1000)->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('similar_products');
    }
};
