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
        Schema::table('regions', function (Blueprint $table) {
            // Change motif_color to JSON to support multiple colors
            $table->json('motif_color')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            // Revert back to string if needed
            $table->string('motif_color')->nullable()->change();
        });
    }
};
