<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('character_region', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('character_id')->references('id')->on('characters')->onDelete('cascade');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');

            // Prevent duplicate pairs
            $table->unique(['character_id', 'region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_region');
    }
};
