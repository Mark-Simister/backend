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
        Schema::table('videos', function (Blueprint $table) {
            // Add after a logical column, adjust placement as you like
            $table->string('tag_ids')
                  ->nullable()
                  ->after('status')
                  ->comment('Comma-separated list of tag IDs');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('tag_ids');
        });
    }
};
