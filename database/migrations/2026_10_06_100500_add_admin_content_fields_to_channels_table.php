<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - channel editorial fields
 * (design record sections 9.2 and 12.3).
 *
 * `pipeline_code` holds n8n's identifier for the channel; review associations resolve
 * by EXACT match only. Additive only. These columns are hidden from model serialisation
 * until the public channel API is composed (Sprint C), so no public output changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            if (! Schema::hasColumn('channels', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (! Schema::hasColumn('channels', 'category_label')) {
                $table->string('category_label', 80)->nullable();
            }
            if (! Schema::hasColumn('channels', 'pipeline_code')) {
                $code = $table->string('pipeline_code', 64)->nullable();
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $code->collation('utf8mb4_bin');   // exact, case-sensitive match on MySQL too
                }
                $table->unique('pipeline_code', 'channels_pipeline_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            if (Schema::hasColumn('channels', 'pipeline_code')) {
                $table->dropUnique('channels_pipeline_code_unique');
            }
        });
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['bio', 'category_label', 'pipeline_code'],
                fn ($c) => Schema::hasColumn('channels', $c)
            )));
        });
    }
};
