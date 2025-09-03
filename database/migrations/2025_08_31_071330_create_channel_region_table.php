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
        Schema::create('channel_region', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('channel_id')
                  ->references('id')->on('channels')
                  ->onDelete('cascade');

            $table->foreign('region_id')
                  ->references('id')->on('regions')
                  ->onDelete('cascade');

            // Avoid duplicate pairs
            $table->unique(['channel_id', 'region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_region');
    }
};
