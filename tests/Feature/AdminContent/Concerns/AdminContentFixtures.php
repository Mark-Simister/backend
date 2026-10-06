<?php

namespace Tests\Feature\AdminContent\Concerns;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\MediaAsset;
use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Services\Media\MediaRegistry;
use App\Services\Reviews\ReviewEditorialService;
use App\Services\Reviews\ReviewPayloadIngestor;

/**
 * Synthetic fixtures for the Admin Content & Media tests. Every row is invented here;
 * no real payload data is used. Payloads are written through the supported ingestion
 * service so their derived fingerprint is maintained exactly as in production.
 */
trait AdminContentFixtures
{
    protected ?Category $category = null;

    protected function configureAdminContent(): void
    {
        config([
            'reviews.video_authority' => null,
            'reviews.public_statuses' => ['published'],
            'reviews.divergence_hold_product_uids' => [],
        ]);
    }

    /** A complete pipeline payload row (column values as the seeder hands them over). */
    protected function reviewRow(string $id = 'pr_test_001', array $columns = [], array $page = []): array
    {
        $slug = 'test-feeder-' . strtolower(str_replace('_', '-', $id));

        $rp = array_replace_recursive([
            'page_identity' => ['published_review_id' => $id, 'review_slug' => $slug],
            'hero' => ['title' => 'Test Feeder Review', 'channel' => 'Pet Channel', 'character_name' => 'Rex'],
            'quick_verdict' => ['summary' => 'A solid pick for busy owners.'],
            'review_facts' => ['beastiescore' => 4.5, 'public_score' => 4.3],
            'beastiescore' => [
                'final_score' => 4.5,
                'raw' => ['calculated_at' => '2026-10-01T00:00:00Z', 'validation' => ['checked_at' => '2026-10-01T00:00:00Z']],
            ],
            'pros_cons' => ['pros' => ['Quiet motor'], 'cons' => ['Small hopper']],
            'video' => ['vimeo_id' => '123456789', 'title' => 'Feeder video'],
            'safety' => [
                'safety_feed_status' => 'clear',
                'safety_feed_freshness_status' => 'fresh',
                'safety_feed_checked_at' => '2026-09-30',
                'safety_age_days' => 3,
            ],
            'disclosure' => ['affiliate_disclosure_text' => 'Affiliate links may earn a commission.'],
        ], $page);

        return array_merge([
            'published_review_id' => $id,
            'review_slug' => $slug,
            'product_uid' => 'PUID_' . strtoupper($id),
            'channel' => 'CH_PET',
            'character_id' => 'CHR_REX',
            'character_name' => 'Rex',
            'h1' => 'Test Feeder Review',
            'canonical_url' => 'https://example.test/review/' . $slug,
            'language_variant' => 'en',
            'publish_status' => 'published',
            'source' => 'pipeline',
            'final_beastie_score' => 4.5,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
            'review_page_json' => $rp,
            'schema_json_ld' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'Product', 'name' => 'Test Feeder'],
                    ['@type' => 'VideoObject', 'name' => 'Feeder video'],
                ],
            ],
        ], $columns);
    }

    protected function ingest(array $row, bool $force = false): PublishedReviewPayload
    {
        return app(ReviewPayloadIngestor::class)->upsert($row, $force)['payload'];
    }

    /** The channel, character (exact pipeline_code) and category a review confirms. */
    protected function associations(string $channelCode = 'CH_PET', string $characterCode = 'CHR_REX'): Category
    {
        $this->category = Category::create(['name' => 'Automatic Feeders ' . $channelCode . $characterCode]);
        Channel::query()->forceCreate(['name' => 'Pet Channel ' . $channelCode, 'pipeline_code' => $channelCode]);
        Character::query()->forceCreate(['name' => 'Rex ' . $characterCode, 'category_id' => $this->category->id, 'pipeline_code' => $characterCode]);

        return $this->category;
    }

    protected function editorial(): ReviewEditorialService
    {
        return app(ReviewEditorialService::class);
    }

    protected function registry(): MediaRegistry
    {
        return app(MediaRegistry::class);
    }

    /** @return array{scheme: ?string, content: ?string, media: ?string} */
    protected function fp(PublishedReviewPayload $payload): array
    {
        return $this->editorial()->currentFingerprints($payload->fresh());
    }

    protected function confirm(PublishedReviewPayload $payload, ?int $by = null): ReviewEditorialRecord
    {
        $category = $this->category ?? $this->associations();

        return $this->editorial()->confirmAssociations($payload->fresh(), $category->id, $by);
    }

    protected function qaPass(PublishedReviewPayload $payload, ?int $by = null): ReviewEditorialRecord
    {
        $now = $this->fp($payload);

        return $this->editorial()->qaPass($payload->fresh(), (string) $now['content'], (string) $now['media'], $by);
    }

    protected function release(PublishedReviewPayload $payload, ?int $by = null): ReviewEditorialRecord
    {
        $now = $this->fp($payload);

        return $this->editorial()->release($payload->fresh(), (string) $now['content'], (string) $now['media'], 'released in test', $by);
    }

    protected function releasedReview(string $id = 'pr_test_001', array $columns = [], array $page = []): PublishedReviewPayload
    {
        $payload = $this->ingest($this->reviewRow($id, $columns, $page));
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->release($payload);

        return $payload->fresh();
    }

    protected function record(PublishedReviewPayload|string $payload): ?ReviewEditorialRecord
    {
        return ReviewEditorialRecord::query()->find(is_string($payload) ? $payload : $payload->published_review_id);
    }

    protected function videoAsset(string $reference = 'vimeo:123456789', array $metadata = []): MediaAsset
    {
        return $this->registry()->registerExternal($reference, array_merge([
            'title' => 'Feeder video',
            'description' => 'The feeder, reviewed.',
            'provider_published_at' => '2026-09-01 00:00:00',
        ], $metadata), null);
    }

    /** A managed image row (no file is written: the registry's file path is tested with a mocked disk). */
    protected function imageAsset(?string $altText = 'A grey automatic pet feeder', string $seed = 'a'): MediaAsset
    {
        $sha = hash('sha256', 'image-' . $seed);

        return MediaAsset::create([
            'kind' => MediaAsset::KIND_IMAGE,
            'storage' => MediaAsset::STORAGE_MANAGED,
            'disk' => 'media',
            'path' => 'image/' . substr($sha, 0, 2) . '/' . $sha . '.png',
            'sha256' => $sha,
            'mime_type' => 'image/png',
            'bytes' => 1024,
            'title' => 'Poster ' . $seed,
            'alt_text' => $altText,
        ]);
    }
}
