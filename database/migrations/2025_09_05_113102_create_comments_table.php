<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->text('body');
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamps();

            $table->index(['video_id', 'parent_id', 'created_at']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
