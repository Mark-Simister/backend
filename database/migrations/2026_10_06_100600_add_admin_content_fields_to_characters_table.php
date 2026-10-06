<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - character editorial fields
 * (design record sections 9.3 and 12.3).
 *
 * Storage is split by visibility class:
 *   insights            human-authored profile content that MAY become public
 *                       (only through the governed allowlist presenter, Sprint C);
 *   production_profile  internal production/editorial direction - never serialised
 *                       by any public API.
 * `pipeline_code` holds n8n's identifier for the character (exact-match resolution).
 * Additive only; all three are hidden from model serialisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            if (! Schema::hasColumn('characters', 'insights')) {
                $table->json('insights')->nullable();
            }
            if (! Schema::hasColumn('characters', 'production_profile')) {
                $table->json('production_profile')->nullable();
            }
            if (! Schema::hasColumn('characters', 'pipeline_code')) {
                $code = $table->string('pipeline_code', 64)->nullable();
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $code->collation('utf8mb4_bin');   // exact, case-sensitive match on MySQL too
                }
                $table->unique('pipeline_code', 'characters_pipeline_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            if (Schema::hasColumn('characters', 'pipeline_code')) {
                $table->dropUnique('characters_pipeline_code_unique');
            }
        });
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['insights', 'production_profile', 'pipeline_code'],
                fn ($c) => Schema::hasColumn('characters', $c)
            )));
        });
    }
};
