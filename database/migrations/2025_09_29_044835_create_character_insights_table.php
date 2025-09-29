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
        Schema::create('character_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id'); 
            $table->string('title', 255);
            $table->text('short_description')->nullable();
            $table->string('character_insight_image')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_insights');
    }
};
