<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * content_tier records how much genuine review content backs a payload:
 *   'rich' — real review content (verdict / pros-cons / full report / Q&A)
 *   'thin' — product + score only (no review body)
 *   null   — not assessed (pipeline-seeded rows)
 * Re-evaluated on every publish AND refresh. Lets a later indexing policy key on
 * e.g. (source='admin' AND content_tier='thin') without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            if (! Schema::hasColumn('published_review_payloads', 'content_tier')) {
                $table->string('content_tier')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            if (Schema::hasColumn('published_review_payloads', 'content_tier')) {
                $table->dropColumn('content_tier');
            }
        });
    }
};
