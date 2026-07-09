<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_images')) {
            Schema::create('site_images', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();     // slug, e.g. pets_hero
                $table->string('label');             // human label for the admin
                $table->string('image')->nullable(); // public-relative path
                $table->timestamps();
            });
        }

        // Seed the managed slots (idempotent).
        $slots = [
            ['key' => 'home_hero',   'label' => 'Home Page Hero'],
            ['key' => 'pets_hero',   'label' => 'Pets Home Hero'],
            ['key' => 'people_hero', 'label' => 'People Home Hero'],
            ['key' => 'mylair_hero', 'label' => 'My Lair Hero'],
        ];
        foreach ($slots as $slot) {
            DB::table('site_images')->updateOrInsert(
                ['key' => $slot['key']],
                ['label' => $slot['label'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_images');
    }
};
