<?php

namespace Tests\Feature\AdminContent;

use App\Http\Controllers\PublicReviewPageController;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Services\Reviews\ReviewPayloadIngestor;
use App\Support\RegionResolver;
use App\Support\Reviews\PublicReleaseVerifier;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\PublicSurfaceFixtures;
use Tests\TestCase;

/**
 * The Sprint B review-page cache rule (design record section 5), on the in-process array
 * store: verification before every cache lookup; only a verified review populates the
 * cache; the identity is slug + scheme + bound content fingerprint + bound media
 * fingerprint + indexability + origin.
 */
class PublicReviewCacheTest extends TestCase
{
    use AdminContentFixtures;
    use PublicSurfaceFixtures;
    use RefreshDatabase;

    private const AU = 'http://au.fstg.beastierated.com';

    /** @var string[] */
    private array $written = [];

    /** @var string[] */
    private array $hits = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->enablePublicRendering(cacheTtl: 600);
        $this->associations();

        Event::listen(KeyWritten::class, function (KeyWritten $e) {
            if (str_starts_with($e->key, 'review_html:')) {
                $this->written[] = $e->key;
            }
        });
        Event::listen(CacheHit::class, function (CacheHit $e) {
            if (str_starts_with($e->key, 'review_html:')) {
                $this->hits[] = $e->key;
            }
        });
    }

    private function keyFor(PublishedReviewPayload $payload, string $origin = self::AU, bool $indexable = false): string
    {
        $verified = app(PublicReleaseVerifier::class)->verify($payload->fresh(), RegionResolver::fromHost(parse_url($origin, PHP_URL_HOST)));
        $this->assertNotNull($verified, 'the review should verify when its key is computed');

        return PublicReviewPageController::cacheKey($verified, $indexable, $origin);
    }

    public function test_verification_runs_before_every_cache_lookup(): void
    {
        $review = $this->releasedReview('pr_cache_1');
        $key = $this->keyFor($review);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk();
        $this->assertSame([$key], $this->written);

        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk();
        $this->assertSame([$key], $this->hits, 'a verified repeat request is served from the cache');

        $this->editorial()->unrelease($review, 'Paused', null);
        $this->hits = [];

        $this->get(self::AU . '/review/' . $review->review_slug)->assertNotFound();
        $this->assertSame([], $this->hits, 'verification refused before any cache lookup ...');
        $this->assertTrue(Cache::has($key), '... although the earlier entry still exists');
    }

    public function test_only_a_successful_verification_populates_the_cache(): void
    {
        $unreleased = $this->ingest($this->reviewRow('pr_cache_2'));
        $this->get(self::AU . '/review/' . $unreleased->review_slug)->assertNotFound();
        $this->assertSame([], $this->written);

        $this->confirm($unreleased);
        $this->qaPass($unreleased);
        $this->release($unreleased);

        $this->get(self::AU . '/review/' . $unreleased->review_slug)->assertOk();
        $this->assertSame([$this->keyFor($unreleased)], $this->written);
    }

    public function test_a_bypass_mutation_cannot_serve_previously_cached_html(): void
    {
        $review = $this->releasedReview('pr_cache_3');
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('A solid pick for busy owners.');
        $key = $this->keyFor($review);

        $this->bypassEdit($review, 'Bypass text that must never be served.');

        $this->get(self::AU . '/review/' . $review->review_slug)->assertNotFound()->assertDontSee('A solid pick for busy owners.');
        $this->assertTrue(Cache::has($key), 'the stale entry is unreachable, not merely absent');
    }

    public function test_a_content_binding_change_separates_cache_identity(): void
    {
        $review = $this->releasedReview('pr_cache_4');
        $first = $this->keyFor($review);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('A solid pick for busy owners.');

        app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_cache_4', [], ['quick_verdict' => ['summary' => 'A corrected verdict.']]));
        $this->qaPass($review);
        $this->release($review);

        $second = $this->keyFor($review);
        $this->assertNotSame($first, $second);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('A corrected verdict.')->assertDontSee('A solid pick for busy owners.');
        $this->assertSame([$first, $second], $this->written);
    }

    public function test_a_media_binding_change_separates_cache_identity(): void
    {
        $review = $this->releasedReview('pr_cache_5');
        $first = $this->keyFor($review);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk();

        $this->registry()->stage($this->videoAsset('vimeo:555001'), MediaAttachment::OWNER_REVIEW, 'pr_cache_5', 'video', null);
        $this->qaPass($review);
        $this->release($review);

        $second = $this->keyFor($review);
        $this->assertNotSame($first, $second, 'only the bound media fingerprint changed');
        $this->assertSame(explode(':', $first)[1], explode(':', $second)[1], 'same slug');
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk();
        $this->assertSame([$first, $second], $this->written);
    }

    public function test_indexable_state_separates_cache_identity(): void
    {
        $review = $this->releasedReview('pr_cache_6');
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        config(['reviews.indexable' => true]);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('<meta name="robots" content="index, follow', false);

        $this->assertSame([$this->keyFor($review, self::AU, false), $this->keyFor($review, self::AU, true)], $this->written);
    }

    public function test_public_origin_separates_cache_identity(): void
    {
        $review = $this->releasedReview('pr_cache_7');
        $us = 'http://us.fstg.beastierated.com';

        $this->get(self::AU . '/review/' . $review->review_slug)->assertOk()->assertSee('<link rel="canonical" href="' . self::AU . '/review/' . $review->review_slug . '">', false);
        $this->get($us . '/review/' . $review->review_slug)->assertOk()->assertSee('<link rel="canonical" href="' . $us . '/review/' . $review->review_slug . '">', false);

        $this->assertSame([$this->keyFor($review, self::AU), $this->keyFor($review, $us)], $this->written);
    }

    public function test_a_verification_failure_never_creates_a_public_html_cache_entry(): void
    {
        $review = $this->releasedReview('pr_cache_8');

        config(['reviews.video_authority' => 'Admin']);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertNotFound();
        $this->editorial()->hold($review, 'Legal', null);
        config(['reviews.video_authority' => null]);
        $this->get(self::AU . '/review/' . $review->review_slug)->assertNotFound();

        $this->assertSame([], $this->written);
    }
}
