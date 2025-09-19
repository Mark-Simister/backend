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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade'); 
            $table->foreignId('video_id')->constrained()->onDelete('cascade'); 
            $table->string('review_url'); // Store the Vimeo or MP3 URL
            $table->boolean('is_featured')->default(false); 
            $table->boolean('is_active')->default(true);
            $table->timestamps(); 
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_reviews');
    }
};
