<?php

namespace Tests\Feature\AdminContent;

use App\Models\AdminAuditEvent;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Services\Reviews\ReviewPayloadIngestor;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\VideoAuthorityConfigurationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\PublicSurfaceFixtures;
use Tests\Feature\AdminContent\Concerns\ReleasesPublicReviews;
use Tests\TestCase;

/**
 * Sprint B public enforcement (Admin Content & Media design record, sections 4.1 and 5):
 * no public surface emits review-derived content unless the current render projection
 * still matches the human-approved release binding. Every surface - /review/{slug}, the
 * legacy numeric redirect, the sitemap and the Best For publication gate - goes through
 * the one PublicReleaseVerifier.
 */
class PublicEnforcementTest extends TestCase
{
    use AdminContentFixtures;
    use PublicSurfaceFixtures;
    use RefreshDatabase;
    use ReleasesPublicReviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->enablePublicRendering();
        $this->associations();
    }

    private function assertPublicOn(array $hosts, PublishedReviewPayload $payload): void
    {
        foreach ($hosts as $label => $host) {
            $this->get($host . '/review/' . $payload->review_slug)->assertOk();
            $this->assertTrue($this->sitemapLists($host, $payload->review_slug), "{$label}: sitemap should list {$payload->review_slug}");
        }
    }

    private function assertHiddenOn(array $hosts, PublishedReviewPayload $payload, string $because): void
    {
        foreach ($hosts as $label => $host) {
            $this->get($host . '/review/' . $payload->review_slug)->assertNotFound();
            $this->assertFalse($this->sitemapLists($host, $payload->review_slug), "{$because} / {$label}: the sitemap must exclude it");
        }
        foreach (['AU', 'US', 'UK', 'CA', null] as $region) {
            $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), $region), "{$because} / verify({$region})");
        }
    }

    // ---- positive control ------------------------------------------------------------

    public function test_positive_control_a_fully_released_review_is_public_on_every_surface(): void
    {
        $review = $this->releasedReview('pr_positive', ['product_uid' => 'US::BARKTASTIC::ASIN_B0POSITIVE']);
        $this->assertPublicOn($this->hosts(), $review);

        $html = $this->get('http://au.fstg.beastierated.com/review/' . $review->review_slug)->getContent();
        $this->assertStringContainsString('Test Feeder Review', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://au.fstg.beastierated.com/review/' . $review->review_slug . '">', $html);

        $video = $this->legacyVideo('B0POSITIVE');
        foreach ($this->hosts() as $host) {
            $this->get($host . '/review/' . $video)->assertStatus(301)->assertRedirect($host . '/review/' . $review->review_slug);
        }

        [, $edition] = $this->releasedBestForEdition();
        $this->assertSame('', $this->bestForErrors($edition));
    }

    // ---- E1 to E8 ----------------------------------------------------------------------

    public function test_every_release_condition_fails_closed_on_every_host_and_the_sitemap(): void
    {
        $cases = [
            'E1 admin source' => fn (PublishedReviewPayload $p) => $this->raw($p, ['source' => 'admin']),
            'E1 any other source' => fn (PublishedReviewPayload $p) => $this->raw($p, ['source' => 'sheet-legacy']),
            'E2 producer withdrew' => fn (PublishedReviewPayload $p) => $this->raw($p, ['publish_status' => 'withdrawn']),
            'E2 producer status not public' => fn (PublishedReviewPayload $p) => $this->raw($p, ['publish_status' => 'ready_for_review']),
            'E4 D3 divergence hold' => fn (PublishedReviewPayload $p) => config(['reviews.divergence_hold_product_uids' => [$p->product_uid]]),
            'E6 no editorial record' => fn (PublishedReviewPayload $p) => DB::table('review_editorial_records')->where('published_review_id', $p->published_review_id)->delete(),
            'E6 not released' => fn (PublishedReviewPayload $p) => $this->editorial()->unrelease($p, 'Paused', null),
            'E6 editorial hold' => fn (PublishedReviewPayload $p) => $this->editorial()->hold($p, 'Legal check', null),
            'E6 bound scheme mismatch' => fn (PublishedReviewPayload $p) => DB::table('review_editorial_records')->where('published_review_id', $p->published_review_id)->update(['bound_fingerprint_scheme' => FingerprintScheme::A1]),
            'E6 stored vs bound fingerprint mismatch' => fn (PublishedReviewPayload $p) => $this->raw($p, ['content_fingerprint' => str_repeat('e', 64)]),
            'E6 NULL stored fingerprint' => fn (PublishedReviewPayload $p) => $this->raw($p, ['content_fingerprint' => null]),
            'E7 withdrawn' => fn (PublishedReviewPayload $p) => $this->editorial()->withdraw($p, null, 'Recall', null),
            'E7 materially invalid' => fn (PublishedReviewPayload $p) => $this->editorial()->setValidity($p, ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID, 'Wrong model', null),
            'E7 temporarily unavailable' => fn (PublishedReviewPayload $p) => $this->editorial()->setAvailability($p, ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE, 'Out of stock', null),
            'E8 recomputed content mismatch' => fn (PublishedReviewPayload $p) => $this->bypassEdit($p),
            // Corrupt stored JSON-LD: the projection cannot be rebuilt, so no fingerprint.
            // (Corrupt review_page_json is covered by MalformedPayloadIsolationTest.)
            'E8 recompute failure' => fn (PublishedReviewPayload $p) => $this->raw($p, ['schema_json_ld' => '{"@graph": [']),
        ];

        $n = 0;
        foreach ($cases as $label => $mutate) {
            $review = $this->releasedReview('pr_mut_' . (++$n));
            $this->get('http://au.fstg.beastierated.com/review/' . $review->review_slug)->assertOk();

            $mutate($review->fresh());
            $this->assertHiddenOn($this->hosts(), $review, $label);

            config(['reviews.divergence_hold_product_uids' => []]);
        }
    }

    public function test_d1_contradiction_fails_closed_even_with_a_matching_binding(): void
    {
        // Release refuses D1 content, so the binding is forced here to prove the public
        // boundary itself (the scope's own D1 predicate) still excludes it.
        $review = $this->ingest($this->reviewRow('pr_d1', [], ['safety' => ['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'stale']]));
        $this->releaseForPublic($review);

        $this->assertHiddenOn($this->hosts(), $review, 'E5 D1 contradiction');
    }

    public function test_a_recomputed_media_fingerprint_mismatch_fails_closed(): void
    {
        $review = $this->ingest($this->reviewRow('pr_media'));
        $this->confirm($review);
        $this->registry()->stage($this->videoAsset('vimeo:777001'), MediaAttachment::OWNER_REVIEW, 'pr_media', 'video', null);
        $this->qaPass($review);
        $this->release($review);
        $this->assertPublicOn($this->hosts(), $review);

        DB::table('media_assets')->update(['title' => 'Retitled outside the registry']);

        $this->assertHiddenOn($this->hosts(), $review, 'E8 recomputed media mismatch');
    }

    public function test_region_semantics_are_unchanged(): void
    {
        $everywhere = $this->releasedReview('pr_everywhere');
        $auOnly = $this->releasedReview('pr_au_only');
        $global = $this->releasedReview('pr_global');
        $usOnly = $this->releasedReview('pr_us_only');
        $this->editorial()->setRegions($auOnly, [$this->region('AU')->id], 'AU only', null);
        $this->editorial()->setRegions($global, [$this->region('GLOBAL')->id], 'Global', null);
        $this->editorial()->setRegions($usOnly, [$this->region('US')->id], 'US only', null);

        $hosts = $this->hosts();
        $this->assertPublicOn($hosts, $everywhere);
        $this->assertPublicOn($hosts, $global);

        $this->assertPublicOn(['AU' => $hosts['AU']], $auOnly);
        foreach (['US', 'UK', 'CA', 'unknown'] as $label) {
            $this->get($hosts[$label] . '/review/' . $auOnly->review_slug)->assertNotFound();
            $this->assertFalse($this->sitemapLists($hosts[$label], $auOnly->review_slug));
        }

        $this->assertPublicOn(['US' => $hosts['US']], $usOnly);
        $this->get($hosts['unknown'] . '/review/' . $usOnly->review_slug)->assertNotFound();
    }

    // ---- frozen-design mutation tests -----------------------------------------------

    public function test_a_bypass_writer_is_refused_by_every_surface_even_after_a_cached_render(): void
    {
        $this->enablePublicRendering(cacheTtl: 600);
        [$payloads, $edition] = $this->releasedBestForEdition();
        $target = $payloads[2];
        $video = $this->legacyVideo('B0BF00002');
        $au = 'http://au.fstg.beastierated.com';

        $this->get($au . '/review/' . $target->review_slug)->assertOk();          // cached now
        $this->get($au . '/review/' . $video)->assertRedirect($au . '/review/' . $target->review_slug);
        $this->assertTrue($this->sitemapLists($au, $target->review_slug));
        $this->assertSame('', $this->bestForErrors($edition));

        $this->bypassEdit($target);

        $this->get($au . '/review/' . $target->review_slug)->assertNotFound();
        $this->assertFalse($this->sitemapLists($au, $target->review_slug));
        $this->assertStringContainsString('Review pr_bf_2 does not pass public-release verification', $this->bestForErrors($edition));
        $this->get($au . '/review/' . $video)->assertNotFound();
    }

    public function test_a_supported_substantive_refresh_demotes_and_leaves_public_view(): void
    {
        [$payloads, $edition] = $this->releasedBestForEdition();
        $target = $payloads[3];
        $before = $target->content_fingerprint;
        $au = 'http://au.fstg.beastierated.com';

        $result = app(ReviewPayloadIngestor::class)->upsert($this->bestForRow(3, [], ['quick_verdict' => ['summary' => 'A materially corrected verdict.']]));

        $this->assertSame('content_refreshed', $result['invalidation']);
        $this->assertNotSame($before, $target->fresh()->content_fingerprint);
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $this->record($target)->editorial_state);
        $this->assertTrue(AdminAuditEvent::query()->where('action', 'review.invalidated')->where('subject_key', 'pr_bf_3')->where('reason', 'content_refreshed')->exists());
        $this->get($au . '/review/' . $target->review_slug)->assertNotFound();
        $this->assertFalse($this->sitemapLists($au, $target->review_slug));
        $this->assertStringContainsString('Review pr_bf_3 does not pass public-release verification', $this->bestForErrors($edition));
    }

    public function test_a_supported_insignificant_refresh_keeps_the_review_public(): void
    {
        [$payloads, $edition] = $this->releasedBestForEdition();
        $target = $payloads[4];
        $before = $target->content_fingerprint;
        $au = 'http://au.fstg.beastierated.com';

        $result = app(ReviewPayloadIngestor::class)->upsert($this->bestForRow(4, ['updated_at' => '2026-10-07 09:00:00'], [
            'beastiescore' => ['raw' => ['calculated_at' => '2026-10-07T09:00:00Z']],
            'safety' => ['safety_age_days' => 11],
        ]));

        $this->assertNull($result['invalidation']);
        $this->assertSame($before, $target->fresh()->content_fingerprint);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($target)->editorial_state);
        $this->get($au . '/review/' . $target->review_slug)->assertOk();
        $this->assertTrue($this->sitemapLists($au, $target->review_slug));
        $this->assertSame('', $this->bestForErrors($edition));
    }

    public function test_an_invalid_video_authority_fails_closed_everywhere_and_ingestion_refuses(): void
    {
        [$payloads, $edition] = $this->releasedBestForEdition();
        $target = $payloads[1];
        $video = $this->legacyVideo('B0BF00001');
        $au = 'http://au.fstg.beastierated.com';
        $this->get($au . '/review/' . $target->review_slug)->assertOk();

        foreach (['typo' => 'admn', 'wrong case' => 'Payload', 'empty' => ''] as $label => $value) {
            config(['reviews.video_authority' => $value]);

            $this->get($au . '/review/' . $target->review_slug)->assertNotFound();
            $this->assertFalse($this->sitemapLists($au, $target->review_slug), $label);
            $this->assertStringContainsString('Review pr_bf_1 does not pass public-release verification', $this->bestForErrors($edition), $label);
            $this->get($au . '/review/' . $video)->assertNotFound();

            try {
                app(ReviewPayloadIngestor::class)->upsert($this->bestForRow(1, ['h1' => 'Would demote']));
                $this->fail("{$label}: supported ingestion must refuse");
            } catch (VideoAuthorityConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }

        config(['reviews.video_authority' => null]);
        $this->get($au . '/review/' . $target->review_slug)->assertOk();
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($target)->editorial_state, 'no mass demotion from a typo');
    }

    public function test_the_legacy_redirect_never_reveals_an_ineligible_slug(): void
    {
        $unreleased = $this->ingest($this->reviewRow('pr_legacy_hidden', ['product_uid' => 'US::BARKTASTIC::ASIN_B0HIDDEN01']));
        $video = $this->legacyVideo('B0HIDDEN01');

        $response = $this->get('http://au.fstg.beastierated.com/review/' . $video);
        $response->assertNotFound();
        $this->assertStringNotContainsString($unreleased->review_slug, (string) $response->headers->get('Location'));

        $this->get('http://au.fstg.beastierated.com/review/987654')->assertNotFound();   // no mapping
    }

    public function test_no_new_public_numeric_route_exists(): void
    {
        $reviewRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'review/'))
            ->map(fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri() . ' -> ' . $route->getActionName())
            ->values()->all();

        $this->assertSame([
            'GET|HEAD review/{id} -> App\Http\Controllers\PublicReviewPageController@legacyRedirect',
            'GET|HEAD review/{slug} -> App\Http\Controllers\PublicReviewPageController@show',
        ], $reviewRoutes);
    }
}
