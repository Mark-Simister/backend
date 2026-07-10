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
        // FU-1: see create_themes_table. `user_themes` already exists on the admin
        // backend while this migration is still Pending; guard so a bare `migrate`
        // cannot die here and strand a half-applied batch.
        if (Schema::hasTable('user_themes')) {
            return;
        }

        Schema::create('user_themes', function (Blueprint $table) {
            $table->id();
            // The upstream stub created only id + timestamps; the UserTheme model and
            // its relationships need these (login eager-loads userTheme).
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('theme_id')->nullable()->index();
            $table->string('button_color')->nullable();
            $table->string('link_color')->nullable();
            $table->string('dark_bg_color')->nullable();
            $table->string('light_bg_color')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_themes');
    }
};
