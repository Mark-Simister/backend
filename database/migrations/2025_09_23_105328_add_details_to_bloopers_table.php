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
        Schema::table('bloopers', function (Blueprint $table) {
            $table->string('image')->nullable()->after('video'); // store image path
            $table->string('name')->nullable()->after('image');
            $table->text('description')->nullable()->after('name');
            $table->unsignedTinyInteger('stars')->default(0)->after('description'); // stars out of 5
        });
    }

    public function down(): void
    {
        Schema::table('bloopers', function (Blueprint $table) {
            $table->dropColumn(['image', 'name', 'description', 'stars']);
        });
    }
};
