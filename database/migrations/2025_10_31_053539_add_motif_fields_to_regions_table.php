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
            $table->string('motif_color')->nullable()->after('currency_symbol');
            $table->string('motif_type')->nullable()->after('motif_color');
            $table->decimal('opacity', 5, 2)->nullable()->after('motif_type'); // e.g., 0.75
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['motif_color', 'motif_type', 'opacity']);
        });
    }
};
