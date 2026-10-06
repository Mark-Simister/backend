<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Content & Media Operations - the derived render fingerprint
 * (design record sections 6.4 and 12.3).
 *
 * Additive only: no existing column, type, nullability, key or index changes.
 * Written only by the supported ingestion service and the fingerprint-backfill tool.
 * It is an SQL prefilter, not a trusted control: public output (later sprints) always
 * recomputes from the bytes actually rendered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            if (! Schema::hasColumn('published_review_payloads', 'content_fingerprint')) {
                $table->char('content_fingerprint', 64)->nullable();
                $table->index('content_fingerprint', 'prp_content_fingerprint_idx');
            }
            if (! Schema::hasColumn('published_review_payloads', 'content_fingerprint_scheme')) {
                $table->string('content_fingerprint_scheme', 32)->nullable();
            }
        });
    }

    /**
     * Drop the index BEFORE its column: SQLite leaves a dangling index behind when an
     * indexed column is dropped, which would make the rollback fail.
     */
    public function down(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            if (Schema::hasColumn('published_review_payloads', 'content_fingerprint')) {
                $table->dropIndex('prp_content_fingerprint_idx');
            }
        });
        Schema::table('published_review_payloads', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['content_fingerprint', 'content_fingerprint_scheme'],
                fn ($c) => Schema::hasColumn('published_review_payloads', $c)
            )));
        });
    }
};
