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
        Schema::create('bloopers', function (Blueprint $table) {
            $table->id();

            // Remove foreign key
            $table->unsignedBigInteger('character_id');

            $table->string('video');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('bloopers');
    }
};
