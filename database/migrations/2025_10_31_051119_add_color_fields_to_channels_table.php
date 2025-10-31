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
        Schema::table('channels', function (Blueprint $table) {
            $table->string('text_color', 10)->nullable()->after('background_color');
            $table->string('hover_color', 10)->nullable()->after('text_color');
            $table->string('highlight_color', 10)->nullable()->after('hover_color');
            $table->string('cta', 10)->nullable()->after('highlight_color'); // CTA = Call To Action color
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['text_color', 'hover_color', 'highlight_color', 'cta']);
        });
    }
};
