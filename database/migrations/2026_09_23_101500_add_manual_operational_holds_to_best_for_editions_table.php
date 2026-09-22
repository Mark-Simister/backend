<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine provenance for MANUAL operational holds on a Best For edition.
 *
 * `recommendation_state` and `indexing_state` remain the authoritative persisted
 * effective truth — what a renderer, sitemap or SQL query may rely on directly. They are
 * recomputed from two inputs: the current dispositions of the edition's selected reviews,
 * and an operator's own hold.
 *
 * Only the operator's hold needs storing. A disposition restriction is a pure function of
 * `review_publication_dispositions`, so it can always be recomputed from durable rows and
 * must never be remembered separately. An operator's hold cannot be derived from anything,
 * so without these markers a recomputation would silently clear a human decision.
 *
 * Two markers, not one, because the two axes are already independent: an operator may
 * suspend a recommendation while leaving the page indexed, or noindex a page that still
 * recommends. Collapsing them would lose a distinction `best_for_editions` already makes.
 *
 * Deliberately NOT used as provenance: `operational_reason` is free human text, and
 * `operational_at` / `operational_by` are written by every operational change including
 * disposition-driven ones. Neither can isolate a manual hold.
 *
 * Additive only. No existing column, type, nullability, key, index or constraint changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('best_for_editions', function (Blueprint $table) {
            if (! Schema::hasColumn('best_for_editions', 'manual_recommendation_suspended_at')) {
                $table->timestamp('manual_recommendation_suspended_at')->nullable();
            }

            if (! Schema::hasColumn('best_for_editions', 'manual_noindex_at')) {
                $table->timestamp('manual_noindex_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('best_for_editions', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['manual_recommendation_suspended_at', 'manual_noindex_at'],
                fn ($column) => Schema::hasColumn('best_for_editions', $column)
            )));
        });
    }
};
