<?php

namespace Tests\Feature\AdminContent\Concerns;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Support\BestFor\BestForPublicationGate;
use App\Support\BestFor\PublicationConfirmations;
use App\Support\BestFor\SelectionSnapshotBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Sprint B public-surface fixtures (synthetic data only). Used with AdminContentFixtures,
 * whose ingestion / QA / Release helpers produce genuinely released reviews.
 */
trait PublicSurfaceFixtures
{
    /** The four regional hosts plus a non-regional host (unknown region). */
    protected function hosts(): array
    {
        return [
            'AU' => 'http://au.fstg.beastierated.com',
            'US' => 'http://us.fstg.beastierated.com',
            'UK' => 'http://uk.fstg.beastierated.com',
            'CA' => 'http://ca.fstg.beastierated.com',
            'unknown' => 'http://example.test',
        ];
    }

    protected function enablePublicRendering(int $cacheTtl = 0, bool $indexable = false): void
    {
        config(['reviews.rendering_enabled' => true, 'reviews.cache_ttl' => $cacheTtl, 'reviews.indexable' => $indexable]);
    }

    protected function sitemapLists(string $host, string $slug): bool
    {
        return str_contains($this->get($host . '/sitemap.xml')->assertOk()->getContent(), '/review/' . $slug . '</loc>');
    }

    protected function region(string $code): Region
    {
        $region = Region::query()->firstOrNew(['region_code' => $code]);
        $region->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$'])->save();

        return $region;
    }

    protected function raw(PublishedReviewPayload $payload, array $columns): void
    {
        DB::table('published_review_payloads')->where('id', $payload->id)->update($columns);
    }

    /** A bypass writer: rendered content edited with no fingerprint maintenance. */
    protected function bypassEdit(PublishedReviewPayload $payload, string $summary = 'Edited directly in the database.'): void
    {
        $page = json_decode(DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json'), true);
        $page['quick_verdict']['summary'] = $summary;
        $this->raw($payload, ['review_page_json' => json_encode($page)]);
    }

    /** A pipeline row that also satisfies the Best For snapshot contract. */
    protected function bestForRow(int $n, array $columns = [], array $page = []): array
    {
        return $this->reviewRow('pr_bf_' . $n, array_merge([
            'product_uid' => 'US::BARKTASTIC::ASIN_B0BF0000' . $n,
            'public_score' => 4.3,
            'public_rating_count' => 1200,
        ], $columns), array_replace_recursive([
            'payload_schema_version' => SelectionSnapshotBuilder::REQUIRED_PAYLOAD_SCHEMA_VERSION,
            'product' => ['product_name' => 'Feeder ' . $n, 'hero_image_url' => 'https://example.test/feeder-' . $n . '.jpg'],
            'review_facts' => ['public_signal' => 'Strong'],
        ], $page));
    }

    /**
     * Five genuinely released reviews (ingest, associations, QA pass, Release) with explicit
     * AU regional evidence, and one AU Best For edition selecting all five.
     *
     * @return array{0: PublishedReviewPayload[], 1: BestForEdition}
     */
    protected function releasedBestForEdition(): array
    {
        $au = $this->region('AU');
        $payloads = [];
        foreach (range(1, 5) as $n) {
            $payload = $this->ingest($this->bestForRow($n));
            $this->confirm($payload);
            $this->editorial()->setRegions($payload, [$au->id], 'AU Best For evidence', null);
            $this->qaPass($payload);
            $this->release($payload);
            $payloads[$n] = $payload->fresh();
        }

        $subject = BestForSubject::create([
            'category_id' => Category::create(['name' => 'Automatic Feeders Edition'])->id,
            'year' => 2026,
            'region_id' => $au->id,
        ]);
        $edition = BestForEdition::create([
            'subject_id' => $subject->id,
            'category_name_as_published' => 'placeholder',
            'year_as_published' => 1999,
            'methodology_text' => 'Five products, scored on the published BeastieScore method.',
            'video_asset_id' => 'vimeo:100001',
        ]);
        $superlatives = ['Best overall', 'Best value', 'Best for large dogs', 'Best budget', 'Best tech'];
        foreach ($payloads as $n => $payload) {
            BestForEditionSelection::create([
                'edition_id' => $edition->id,
                'position' => $n,
                'published_review_id' => $payload->published_review_id,
                'review_slug_as_published' => 'draft-slug',
                'product_name_as_published' => 'draft product',
                'superlative' => $superlatives[$n - 1],
                'selection_reason' => 'Scored highest on the published method.',
                'beastie_score_as_published' => 0.0,
                'public_rating_as_published' => 0.0,
                'public_rating_count_as_published' => 0,
                'public_signal_as_published' => 'Limited',
            ]);
        }

        return [$payloads, $edition];
    }

    protected function bestForErrors(BestForEdition $edition): string
    {
        $ids = $edition->selections()->pluck('published_review_id')->all();

        return implode(' | ', (new BestForPublicationGate())->errorsFor($edition->fresh(), new PublicationConfirmations(
            notDivergenceHeld: $ids,
            categoryConfirmed: $ids,
            productDistinctnessConfirmed: true,
            videoConsistencyConfirmed: true,
        )));
    }

    /**
     * A legacy `videos` row mapping a numeric id to an ASIN, in the in-memory/isolated
     * TEST database only, so the existing legacy numeric redirect can be exercised.
     */
    protected function legacyVideo(string $asin): int
    {
        return DB::table('videos')->insertGetId([
            'title' => 'Legacy video', 'type' => 'vimeo', 'video_url' => 'https://example.test/legacy',
            'character_id' => 0, 'channel_id' => 0, 'category_id' => 0, 'product_asin_sku' => $asin,
        ]);
    }
}
