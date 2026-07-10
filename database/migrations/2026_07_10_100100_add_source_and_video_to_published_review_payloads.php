<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets admin-sourced payloads coexist with pipeline-sourced ones in the same
 * read model (Phase 4). `source` discriminates producers; `video_id` links an
 * admin payload back to its video (idempotent re-publish key). No FK constraint
 * (matches the existing table + avoids cross-engine FK/charset traps).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            if (! Schema::hasColumn('published_review_payloads', 'video_id')) {
                $table->unsignedBigInteger('video_id')->nullable()->index();
            }
            if (! Schema::hasColumn('published_review_payloads', 'source')) {
                $table->string('source')->default('pipeline')->index();
            }
            if (! Schema::hasColumn('published_review_payloads', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('published_review_payloads', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter([
                'video_id', 'source', 'published_at',
            ], fn ($c) => Schema::hasColumn('published_review_payloads', $c))));
        });
    }
};
