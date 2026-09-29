<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A published review payload — the read model for the public SEO page at
 * /review/{slug}. Resolved by review_slug. Rows are either `source=pipeline`
 * (spreadsheet-seeded) or `source=admin` (published from a video). Region
 * eligibility lives in the published_review_payload_region pivot.
 */
class PublishedReviewPayload extends Model
{
    protected $table = 'published_review_payloads';

    // Sheet supplies its own created_at/updated_at; don't let Eloquent overwrite them.
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'final_beastie_score' => 'float',
        'public_score' => 'float',
        'public_rating_count' => 'integer',
        'analysed_evidence_count' => 'integer',
        'source_count' => 'integer',
        'qa_errors_json' => 'array',
        'qa_warnings_json' => 'array',
        'review_page_json' => 'array',
        'schema_json_ld' => 'array',
        'commerce_json' => 'array',
        'sources_json' => 'array',
        'safety_json' => 'array',
        'disclosure_json' => 'array',
    ];

    /** Route-model binding resolves by the public slug. */
    public function getRouteKeyName(): string
    {
        return 'review_slug';
    }

    /** Regions this payload is eligible for (empty = all regions). */
    public function regions()
    {
        return $this->belongsToMany(Region::class, 'published_review_payload_region');
    }

    /**
     * The single source of truth for "is this payload publicly visible on this
     * region's host". Used by BOTH the controller and the sitemap so the rule
     * is never duplicated. Combines the publish_status gate with region
     * eligibility:
     *   - empty region pivot            → all regions (backwards-compat)
     *   - a GLOBAL region row           → all regions
     *   - a row for $region             → that region
     *   - $region null (unknown host)   → only all-region payloads (fail-safe)
     */
    public function scopePublicForRegion(Builder $query, ?string $region): Builder
    {
        return $query
            ->whereIn('publish_status', (array) config('reviews.public_statuses', ['published']))
            ->where(function (Builder $q) use ($region) {
                $q->whereDoesntHave('regions')
                  ->orWhereHas('regions', fn (Builder $r) => $r->where('region_code', 'GLOBAL'));
                if ($region) {
                    $q->orWhereHas('regions', fn (Builder $r) => $r->where('region_code', $region));
                }
            })
            ->tap(fn (Builder $q) => $this->applyDivergenceHold($q))
            ->tap(fn (Builder $q) => $this->applySafetyContradictionGate($q));
    }

    /**
     * TEMPORARY D3 divergence-evidence hold (RP-DIV-02B1). Excludes reviews whose
     * legacy divergence adjustment has no D3-grade evidentiary grounding. They are
     * NOT wrong - they are unproven to the current disclosure standard, so they fail
     * closed at the public boundary. Exact product_uid match only; never LIKE.
     * Applied here so /review/{slug} and the sitemap share one determination.
     */
    private function applyDivergenceHold(Builder $q): void
    {
        $held = array_values(array_filter((array) config('reviews.divergence_hold_product_uids', [])));

        if ($held !== []) {
            // NULL-safe: `product_uid NOT IN (...)` is NULL for a NULL product_uid,
            // which would silently 404 every payload that has no product_uid.
            $q->where(function (Builder $w) use ($held) {
                $w->whereNull('product_uid')->orWhereNotIn('product_uid', $held);
            });
        }
    }

    /**
     * D1 - a materially contradictory public safety state fails closed.
     *
     * The qualified predicate is exactly:
     *   safety_feed_status === "clear" AND
     *   safety_feed_freshness_status IN ("missing","unknown","stale")
     *
     * The renderer never reconciles, reinterprets or chooses between contradictory
     * safety fields - it declines to publish. Expressed positively and NULL-safe:
     * a payload with no safety block is not contradictory and stays eligible,
     * because SQL NULL comparisons would otherwise silently exclude it.
     */
    private function applySafetyContradictionGate(Builder $q): void
    {
        $status = 'review_page_json->safety->safety_feed_status';
        $fresh  = 'review_page_json->safety->safety_feed_freshness_status';

        $q->where(function (Builder $outer) use ($status, $fresh) {
            $outer->where(function (Builder $s) use ($status) {
                $s->whereNull($status)->orWhere($status, '!=', 'clear');
            })->orWhere(function (Builder $f) use ($fresh) {
                $f->whereNull($fresh)
                  ->orWhereNotIn($fresh, ['missing', 'unknown', 'stale']);
            });
        });
    }
}
