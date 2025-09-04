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
        Schema::create('category_region', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('region_id');

            // Either composite PK or unique index; choose one. Here: composite PK.
            $table->primary(['category_id', 'region_id']);

            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->onDelete('cascade');

            $table->foreign('region_id')
                ->references('id')->on('regions')
                ->onDelete('cascade');

            // Helpful for lookups
            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_region');
    }
};
