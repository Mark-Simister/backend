<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // If you want to store comma-separated tag IDs on videos table:
        Schema::table('videos', function (Blueprint $table) {
            $table->string('tag_ids')->nullable()->after('status'); // CSV of tag IDs
        });
    }

    public function down(): void {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('tag_ids');
        });
        Schema::dropIfExists('tags');
    }
};
