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
        Schema::table('video_watch_histories', function (Blueprint $table) {
            // Add new columns
            $table->integer('total_watched_seconds')->default(0)->after('last_position_seconds');
            $table->timestamp('completed_at')->nullable()->after('is_completed');

            // Add unique constraint on user_id + video_id
            $table->unique(['user_id', 'video_id'], 'uniq_user_video');

            // Optional index (not needed if unique exists, but harmless if you want it)
            // $table->index(['user_id', 'video_id'], 'idx_user_video');
        });
    }

    public function down(): void
    {
        Schema::table('video_watch_histories', function (Blueprint $table) {
            // Drop the columns & constraints if rolling back
            $table->dropColumn(['total_watched_seconds', 'completed_at']);
            $table->dropUnique('uniq_user_video');
            // $table->dropIndex('idx_user_video');
        });
    }
};
