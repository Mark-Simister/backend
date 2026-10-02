<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * add_extra_fields_to_videos_table declares `status` as NOT NULL with a 'draft'
     * default, so a fresh database gets NOT NULL — but the long-lived staging
     * database holds the column as nullable. Staging is the divergent environment
     * and carries zero NULL rows, so the contract can be enforced there without any
     * data remediation first.
     *
     * Enum values and default are unchanged; only nullability moves. No row is
     * touched, and no fallback value is written.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('videos', 'status')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])
                ->default('draft')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('videos', 'status')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])
                ->default('draft')
                ->nullable()
                ->change();
        });
    }
};
