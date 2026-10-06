<?php

namespace Tests\Feature\AdminContent;

use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\VerifiedReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * PublicReleaseVerifier (design record section 5): candidates() is the E1-E7 SQL
 * prefilter, verify()/verifyMany() add E8 by recomputing from the row actually loaded.
 * Built and tested in Sprint A; NOT wired to any public surface.
 */
class PublicReleaseVerifierTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    private PublicReleaseVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->associations();
        $this->verifier = app(PublicReleaseVerifier::class);
    }

    private function candidateIds(?string $region = null): array
    {
        return $this->verifier->candidates($region)->pluck('published_review_payloads.published_review_id')->sort()->values()->all();
    }

    public function test_a_released_review_verifies_and_carries_the_verified_projection(): void
    {
        $payload = $this->releasedReview();

        $verified = $this->verifier->verify($payload, null);

        $this->assertInstanceOf(VerifiedReview::class, $verified);
        $this->assertSame($payload->published_review_id, $verified->publishedReviewId);
        $this->assertSame(FingerprintScheme::P1, $verified->scheme);
        $this->assertSame($this->record($payload)->bound_content_fingerprint, $verified->contentFingerprint);
        $this->assertSame('Test Feeder Review', $verified->projection['flat']['h1']);
        $this->assertSame([], $verified->media);
        $this->assertSame([$payload->published_review_id], $this->candidateIds());
    }

    public function test_a_bypass_writer_cannot_make_changed_content_verify(): void
    {
        $payload = $this->releasedReview();
        $rp = json_decode(DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json'), true);
        $rp['quick_verdict']['summary'] = 'Edited directly in the database.';

        // Raw query-builder write, no fingerprint maintenance: the stored fingerprint still
        // matches the binding, so the SQL prefilter passes - E8 must still refuse it.
        DB::table('published_review_payloads')->where('id', $payload->id)->update(['review_page_json' => json_encode($rp)]);

        $this->assertSame([$payload->published_review_id], $this->candidateIds(), 'the prefilter alone is fooled');
        $this->assertNull($this->verifier->verify($payload->fresh(), null), 'E8 recomputation refuses it');
        $this->assertCount(0, $this->verifier->verifyMany([$payload->fresh()], null));
    }

    public function test_a_bypass_writer_that_also_rewrites_the_stored_fingerprint_is_excluded_by_the_prefilter(): void
    {
        $payload = $this->releasedReview();
        DB::table('published_review_payloads')->where('id', $payload->id)->update([
            'h1' => 'Changed', 'content_fingerprint' => str_repeat('b', 64),
        ]);

        $this->assertSame([], $this->candidateIds());
        $this->assertNull($this->verifier->verify($payload->fresh(), null));
    }

    public function test_null_stored_fingerprints_and_null_bindings_fail_closed(): void
    {
        $payload = $this->releasedReview();
        DB::table('published_review_payloads')->where('id', $payload->id)->update(['content_fingerprint' => null]);
        $this->assertSame([], $this->candidateIds());
        $this->assertNull($this->verifier->verify($payload->fresh(), null));

        $other = $this->releasedReview('pr_test_002');
        DB::table('review_editorial_records')->where('published_review_id', $other->published_review_id)->update(['bound_media_fingerprint' => null]);
        $this->assertNull($this->verifier->verify($other->fresh(), null));
    }

    public function test_a_media_bypass_change_fails_verification(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $this->registry()->stage($this->videoAsset(), MediaAttachment::OWNER_REVIEW, $payload->published_review_id, 'video', null);
        $this->qaPass($payload);
        $this->release($payload);
        $this->assertNotNull($this->verifier->verify($payload->fresh(), null));
        $this->assertCount(1, $this->verifier->verify($payload->fresh(), null)->media);

        DB::table('media_assets')->update(['title' => 'Retitled outside the registry']);

        $this->assertNull($this->verifier->verify($payload->fresh(), null));
    }

    public function test_a_scheme_change_or_an_invalid_authority_fails_closed(): void
    {
        $payload = $this->releasedReview();

        config(['reviews.video_authority' => 'admin']);
        $this->assertSame([], $this->candidateIds(), 'a P1 binding is ineligible under A1');
        $this->assertNull($this->verifier->verify($payload->fresh(), null));

        config(['reviews.video_authority' => 'payload ']);
        $this->assertSame([], $this->candidateIds());
        $this->assertNull($this->verifier->verify($payload->fresh(), null));

        config(['reviews.video_authority' => 'payload']);
        $this->assertNotNull($this->verifier->verify($payload->fresh(), null));
    }

    public function test_e1_to_e7_each_exclude(): void
    {
        $ok = $this->releasedReview('pr_ok');
        $status = $this->releasedReview('pr_status');
        $legacy = $this->releasedReview('pr_legacy');
        $d3 = $this->releasedReview('pr_d3');
        $d1 = $this->releasedReview('pr_d1');
        $withdrawn = $this->releasedReview('pr_withdrawn');

        DB::table('published_review_payloads')->where('id', $status->id)->update(['publish_status' => 'ready_for_review']);   // E2
        DB::table('published_review_payloads')->where('id', $legacy->id)->update(['source' => 'admin']);                      // E1
        config(['reviews.divergence_hold_product_uids' => [$d3->product_uid]]);                                                // E4
        $rp = json_decode(DB::table('published_review_payloads')->where('id', $d1->id)->value('review_page_json'), true);
        $rp['safety']['safety_feed_freshness_status'] = 'missing';
        DB::table('published_review_payloads')->where('id', $d1->id)->update(['review_page_json' => json_encode($rp)]);        // E5
        DB::table('review_publication_dispositions')->insert(['published_review_id' => $withdrawn->published_review_id, 'withdrawn_at' => now()]); // E7

        $this->assertSame(['pr_ok'], $this->candidateIds());
        $verified = $this->verifier->verifyMany([$withdrawn, $ok, $status, $legacy, $d3, $d1], null);
        $this->assertSame(['pr_ok'], $verified->map->publishedReviewId->all());
    }

    public function test_region_eligibility_is_the_existing_rule(): void
    {
        $payload = $this->releasedReview();
        $au = new Region();
        $au->forceFill(['region_name' => 'Australia', 'region_code' => 'AU', 'is_active' => 1, 'currency' => 'AUD', 'currency_symbol' => '$'])->save();
        $this->editorial()->setRegions($payload, [$au->id], 'AU only', null);

        $this->assertNull($this->verifier->verify($payload->fresh(), null), 'an unknown host sees only all-region reviews');
        $this->assertNull($this->verifier->verify($payload->fresh(), 'US'));
        $this->assertNotNull($this->verifier->verify($payload->fresh(), 'AU'));
    }

    public function test_verify_many_keeps_input_order_and_skips_non_payloads(): void
    {
        $a = $this->releasedReview('pr_a');
        $b = $this->releasedReview('pr_b');
        $unreleased = $this->ingest($this->reviewRow('pr_c'));

        $ids = $this->verifier->verifyMany([$b, 'not a payload', $unreleased, $a, new PublishedReviewPayload()], null)
            ->map->publishedReviewId->all();

        $this->assertSame(['pr_b', 'pr_a'], $ids);
    }

    public function test_the_verifier_is_not_wired_to_the_public_review_page_in_sprint_a(): void
    {
        config(['reviews.rendering_enabled' => true, 'reviews.cache_ttl' => 0]);
        $unreleased = $this->ingest($this->reviewRow('pr_public'));

        $this->assertNull($this->verifier->verify($unreleased, null));
        $this->get('http://example.test/review/' . $unreleased->review_slug)->assertOk();
    }
}
