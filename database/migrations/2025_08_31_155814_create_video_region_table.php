<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('video_region', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            // Remove foreign keys

            // Keep unique constraint to prevent duplicate pairs
            $table->unique(['video_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_region');
    }
};
