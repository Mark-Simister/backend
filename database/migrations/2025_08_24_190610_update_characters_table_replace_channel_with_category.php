<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Drop old channel_id foreign key if exists
            if (Schema::hasColumn('characters', 'channel_id')) {
                $table->dropColumn('channel_id');
            }

            // Add new category_id without foreign key
            $table->unsignedBigInteger('category_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Drop category_id column
            $table->dropColumn('category_id');

            // Restore channel_id column without foreign key
            $table->unsignedBigInteger('channel_id')->nullable()->after('id');
        });
    }
};
