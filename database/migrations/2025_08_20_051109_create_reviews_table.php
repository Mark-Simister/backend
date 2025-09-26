<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id');

            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('review');                // review text

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')->index();

            $table->timestamps();

            // one review per user per video
            $table->unique(['video_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
