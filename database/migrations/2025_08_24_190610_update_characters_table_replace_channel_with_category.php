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
        Schema::table('characters', function (Blueprint $table) {
            // Drop old channel_id if exists
            if (Schema::hasColumn('characters', 'channel_id')) {
                $table->dropConstrainedForeignId('channel_id');
            }

            // Add new category_id
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('categories')
                  ->onDelete('cascade')
                  ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Rollback: drop category_id
            $table->dropConstrainedForeignId('category_id');

            // Restore channel_id
            $table->foreignId('channel_id')
                  ->nullable()
                  ->constrained('channels')
                  ->onDelete('cascade')
                  ->after('id');
        });
    }
};
