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
        Schema::table('video_likes', function (Blueprint $table) {
            // Add columns only if they don't exist
            if (!Schema::hasColumn('video_likes', 'user_id')) {
                $table->foreignId('user_id')->constrained()->onDelete('cascade')->after('id');
            }
            if (!Schema::hasColumn('video_likes', 'video_id')) {
                $table->foreignId('video_id')->constrained()->onDelete('cascade')->after('user_id');
            }

            // Add unique constraint
            $table->unique(['user_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::table('video_likes', function (Blueprint $table) {
            if (Schema::hasColumn('video_likes', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('video_likes', 'video_id')) {
                $table->dropForeign(['video_id']);
                $table->dropColumn('video_id');
            }
        });
    }
};
