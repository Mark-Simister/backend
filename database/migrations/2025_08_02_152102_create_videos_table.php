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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['youtube', 'vimeo']);
            $table->text('video_url');
            $table->text('thumbnail_url')->nullable();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('category_id');
            $table->enum('access_level', ['public', 'premium', 'early_access'])->default('public');
            $table->text('affiliate_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
