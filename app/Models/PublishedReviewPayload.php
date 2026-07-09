<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pipeline-published review payload — the source of truth for the public
 * SEO review page at /review/{slug}. Resolved by review_slug. The heavy
 * display data + structured data are pre-built in the *_json columns.
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
}
