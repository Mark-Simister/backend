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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Pet Reviewer Application"
            $table->string('video_path')->nullable(); // video file path (in public/videos)
            $table->json('fields'); // store fields as JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id'); // no foreign key constraint
            $table->json('data'); // submitted values as JSON
            $table->timestamps();

            // optional index for faster queries
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
