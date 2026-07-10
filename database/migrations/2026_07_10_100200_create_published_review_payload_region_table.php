<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Region eligibility for public review payloads (Phase 4). A payload with rows
 * here is visible only on those regions' hosts; NO rows = all regions
 * (backwards-compat with pipeline-seeded payloads); a GLOBAL row also = all.
 * Index names are set explicitly + short to stay under MySQL's 64-char limit
 * (the auto-generated names for this long table would exceed it). No FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('published_review_payload_region')) {
            return;
        }
        Schema::create('published_review_payload_region', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('published_review_payload_id');
            $table->unsignedBigInteger('region_id');
            $table->unique(['published_review_payload_id', 'region_id'], 'prpr_pk_unique');
            $table->index('region_id', 'prpr_region_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_review_payload_region');
    }
};
