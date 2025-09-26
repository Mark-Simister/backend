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
        Schema::create('category_region', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('region_id');

            // Keep composite primary key
            $table->primary(['category_id', 'region_id']);

            // Remove foreign keys

            // Helpful for lookups
            $table->index('region_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('category_region');
    }
};
