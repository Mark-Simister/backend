<?php

namespace App\Support\Reviews;

use App\Models\PublishedReviewPayload;

/**
 * The render projection: a scheme-versioned allowlist of exactly what the public
 * renderer may read (design record section 6.2). The fingerprint covers this object;
 * in Sprint B the renderer will receive ONLY this object, so coverage cannot drift.
 *
 * P1 (Mode P) includes:
 *   - flat columns: identity (published_review_id, review_slug, product_uid) and the
 *     rendered display fallbacks (canonical_url, h1, seo_title, seo_description,
 *     og_image_url, hero_image_url, channel, character_name, language_variant,
 *     created_at);
 *   - review_page_json top-level keys the renderer reads, in a fixed order;
 *   - schema_json_ld as stored (before the per-host rewrite).
 * P1 excludes beastiescore.raw (internal, carries per-run clock values),
 * safety.safety_age_days (derived from WF4's run clock, not rendered), the flat
 * updated_at, and every column/key the renderer does not read.
 *
 * A1 (Mode A) = P1 minus review_page_json.video and minus every schema_json_ld @graph
 * node whose @type is or contains VideoObject.
 *
 * Absent keys stay absent and NULLs stay NULL (null vs absent is preserved).
 */
final class RenderProjection
{
    public const FLAT_COLUMNS = [
        'published_review_id',
        'review_slug',
        'product_uid',
        'canonical_url',
        'h1',
        'seo_title',
        'seo_description',
        'og_image_url',
        'hero_image_url',
        'channel',
        'character_name',
        'language_variant',
        'created_at',
    ];

    public const PAGE_KEYS = [
        'page_identity',
        'product',
        'hero',
        'quick_verdict',
        'review_facts',
        'character_take',
        'beastiescore',
        'buyer_fit',
        'pros_cons',
        'full_character_review',
        'owner_consensus',
        'trait_breakdown',
        'qa',
        'transcript',
        'video',
        'seo',
        'commerce',
        'sources',
        'safety',
        'disclosure',
        'schema',
    ];

    /**
     * @throws ProjectionException
     */
    public static function build(PublishedReviewPayload $payload, string $scheme): array
    {
        if (! FingerprintScheme::isContentScheme($scheme)) {
            throw new ProjectionException("Unknown content projection scheme [{$scheme}].");
        }

        $raw = $payload->getAttributes();

        // The decoded JSON must be exactly what the stored text says. Laravel's array
        // cast silently turns invalid JSON into null, which would otherwise fingerprint
        // as "no content" - so invalid or over-deep JSON is a projection failure.
        foreach (['review_page_json', 'schema_json_ld'] as $column) {
            $value = $raw[$column] ?? null;
            if (is_string($value) && ! json_validate($value)) {
                throw new ProjectionException("Stored {$column} is not valid JSON.");
            }
        }

        $flat = [];
        foreach (self::FLAT_COLUMNS as $column) {
            if (! array_key_exists($column, $raw)) {
                throw new ProjectionException("Payload row is not fully loaded: [{$column}] is missing.");
            }
            $flat[$column] = $raw[$column];
        }

        return [
            'flat' => $flat,
            'review_page_json' => self::page($payload->review_page_json, $scheme),
            'schema_json_ld' => self::schema($payload->schema_json_ld, $scheme),
        ];
    }

    private static function page(mixed $rp, string $scheme): mixed
    {
        if (! is_array($rp)) {
            return $rp;
        }

        $page = [];
        foreach (self::PAGE_KEYS as $key) {
            if (! array_key_exists($key, $rp)) {
                continue;
            }
            if ($key === 'video' && $scheme === FingerprintScheme::A1) {
                continue;
            }

            $value = $rp[$key];

            if ($key === 'beastiescore' && is_array($value)) {
                unset($value['raw']);
            }
            if ($key === 'safety' && is_array($value)) {
                unset($value['safety_age_days']);
            }

            $page[$key] = $value;
        }

        return $page;
    }

    private static function schema(mixed $schema, string $scheme): mixed
    {
        if ($scheme !== FingerprintScheme::A1 || ! is_array($schema) || ! is_array($schema['@graph'] ?? null)) {
            return $schema;
        }

        $graph = $schema['@graph'];
        $wasList = array_is_list($graph);

        $kept = array_filter($graph, fn ($node) => ! self::isVideoObject($node));
        $schema['@graph'] = $wasList ? array_values($kept) : $kept;

        return $schema;
    }

    private static function isVideoObject(mixed $node): bool
    {
        if (! is_array($node) || ! array_key_exists('@type', $node)) {
            return false;
        }

        $type = $node['@type'];

        return $type === 'VideoObject' || (is_array($type) && in_array('VideoObject', $type, true));
    }
}
