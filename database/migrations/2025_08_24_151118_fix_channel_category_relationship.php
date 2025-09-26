<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    // Remove category_id from channels
    Schema::table('channels', function (Blueprint $table) {
        // $table->dropForeign(['category_id']);
        $table->dropColumn('category_id');
    });

    // Add channel_id to categories
    Schema::table('categories', function (Blueprint $table) {
        $table->unsignedBigInteger('channel_id')->nullable();
    });
}
};
