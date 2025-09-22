<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('video_watch_histories', function (Blueprint $table) {
            $table->integer('last_position_seconds')->default(0);
            $table->boolean('is_completed')->default(false); 
            $table->timestamp('watched_at')->nullable();  
        });
    }

    public function down()
    {
        Schema::table('video_watch_histories', function (Blueprint $table) {
            $table->dropColumn(['last_position_seconds', 'is_completed', 'watched_at']);
        });
    }
};
