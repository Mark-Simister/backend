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
        // FU-1: `themes` already EXISTS on the admin backend while this migration is
        // still Pending, so a bare `php artisan migrate` dies here with SQLSTATE[42S01]
        // — after the migration before it has already ALTERed `videos`. MySQL has no
        // transactional DDL, so that ALTER cannot be undone. The guard makes this a
        // no-op against the live table (recording the migration as run and leaving the
        // real schema untouched) while a fresh database still gets a usable table.
        if (Schema::hasTable('themes')) {
            return;
        }

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            // The upstream stub created only id + timestamps; the Theme model needs
            // name + colours, so a fresh migrate must produce a schema it can use.
            $table->string('name');
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
        Schema::dropIfExists('themes');
    }
};
