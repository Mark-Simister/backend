<?php

namespace Tests\Feature\AdminContent;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Services\Reviews\EditorialTransitionException;
use App\Support\BestFor\CurrentMembershipResolver;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\ReviewFingerprints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\PublicSurfaceFixtures;
use Tests\Feature\AdminContent\Concerns\ReleasesPublicReviews;
use Tests\TestCase;

/**
 * One public video truth per mode (design record section 8, as corrected by
 * RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-01). Mode P: the payload video, exactly as before.
 * Mode A (configuration overridden in this process only): ONLY the release-bound Admin
 * video, with the poster bound in the same release as the visible poster and the
 * VideoObject thumbnailUrl - no provider-thumbnail fallback, never provider_metadata.
 */
class VideoAuthorityRenderingTest extends TestCase
{
    use AdminContentFixtures;
    use PublicSurfaceFixtures;
    use RefreshDatabase;
    use ReleasesPublicReviews;

    private const HOST = 'http://example.test';

    private const MEDIA_URL = 'https://media.example.test';

    private const PAYLOAD_VIDEO = ['youtube_id' => 'dQw4w9WgXcQ', 'video_url' => ''];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->enablePublicRendering();
        config(['filesystems.disks.media.url' => self::MEDIA_URL]);
        $this->associations();
    }

    private function modeA(): void
    {
        config(['reviews.video_authority' => 'admin']);
    }

    private function payloadWithVideo(string $id): PublishedReviewPayload
    {
        // The existing view shows the payload video only with a product image as its poster.
        return $this->ingest($this->reviewRow($id, [], [
            'video' => self::PAYLOAD_VIDEO,
            'product' => ['product_name' => 'Test Feeder', 'hero_image_url' => 'https://example.test/feeder.jpg'],
        ]));
    }

    private function adminVideo(string $ref): MediaAsset
    {
        return $this->videoAsset($ref, [
            'title' => 'Admin bound video', 'description' => 'The bound review video.',
            'duration_seconds' => 312, 'chapters' => '[{"t": 0, "label": "Intro"}, {"t": 75, "label": "Verdict"}]',
        ]);
    }

    private function stage(MediaAsset $asset, string $reviewId, string $role): MediaAttachment
    {
        return $this->registry()->stage($asset, MediaAttachment::OWNER_REVIEW, $reviewId, $role, null);
    }

    /** Forces an attachment active WITHOUT Release, to construct states the services refuse. */
    private function forceActive(MediaAttachment $attachment): void
    {
        DB::table('media_attachments')->where('id', $attachment->id)->update([
            'state' => MediaAttachment::STATE_ACTIVE,
            'active_slot' => MediaAttachment::slotKey(MediaAttachment::OWNER_REVIEW, $attachment->owner_key, $attachment->role),
            'activated_at' => now(),
        ]);
    }

    private function posterUrl(MediaAsset $poster): string
    {
        return self::MEDIA_URL . '/' . $poster->path;
    }

    /** @return array<int, array<string, mixed>> every VideoObject node in the page JSON-LD */
    private function videoObjects(string $html): array
    {
        if (! preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m)) {
            return [];
        }
        $ld = json_decode($m[1], true);
        $nodes = isset($ld['@graph']) ? $ld['@graph'] : [$ld];

        return array_values(array_filter($nodes, function ($n) {
            $type = is_array($n) ? ($n['@type'] ?? null) : null;

            return $type === 'VideoObject' || (is_array($type) && in_array('VideoObject', $type, true));
        }));
    }

    // ---- Mode P --------------------------------------------------------------------------

    public function test_mode_p_output_is_byte_identical_to_the_pre_sprint_b_render_path(): void
    {
        $payload = $this->payloadWithVideo('pr_mode_p');
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->release($payload);

        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();

        // The renderer as it was before Sprint B: the whole payload model and its full
        // review_page_json handed to the same view.
        $model = PublishedReviewPayload::query()->findOrFail($payload->id);
        $rp = $model->review_page_json;
        $origin = 'https://example.test';
        $json = json_encode($model->schema_json_ld ?: ($rp['schema'] ?? null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $legacy = view('reviews.public-show', [
            'payload' => $model,
            'rp' => $rp,
            'schemaJson' => preg_replace('#https?://(?:[a-z]{2}\.)?(?:fstg\.|bstg\.)?beastierated\.com#i', $origin, $json),
            'canonical' => $origin . '/review/' . $model->review_slug,
            'indexable' => false,
            'origin' => $origin,
            'bestFor' => (new CurrentMembershipResolver())->forReview($model->published_review_id, null),
        ])->render();

        $this->assertSame($legacy, $html);
        $this->assertStringContainsString('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $html);
    }

    public function test_mode_p_never_lets_admin_bound_media_displace_the_payload_video(): void
    {
        $payload = $this->payloadWithVideo('pr_mode_p_media');
        $this->confirm($payload);
        $this->stage($this->adminVideo('vimeo:424201'), 'pr_mode_p_media', 'video');
        $poster = $this->imageAsset('Bound poster', 'p201');
        $this->stage($poster, 'pr_mode_p_media', 'poster');
        $this->qaPass($payload);
        $this->release($payload);   // P1: media is recorded and bound but not rendered

        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();

        $this->assertStringContainsString('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $html);
        $this->assertStringNotContainsString('player.vimeo.com', $html);
        $this->assertStringNotContainsString($this->posterUrl($poster), $html);
        $objects = $this->videoObjects($html);
        $this->assertCount(1, $objects);
        $this->assertSame('Feeder video', $objects[0]['name'], 'the payload VideoObject');
    }

    // ---- Mode A --------------------------------------------------------------------------

    public function test_mode_a_bound_video_and_bound_poster_render_with_exactly_one_admin_video_object(): void
    {
        $this->modeA();
        $payload = $this->payloadWithVideo('pr_mode_a');
        $this->assertSame(FingerprintScheme::A1, $payload->content_fingerprint_scheme);
        $this->confirm($payload);
        $this->stage($this->adminVideo('vimeo:424242'), 'pr_mode_a', 'video');
        $poster = $this->imageAsset('A grey feeder on a kitchen floor', 'p242');
        $this->stage($poster, 'pr_mode_a', 'poster');
        $this->qaPass($payload);
        $this->release($payload);

        $this->assertNotNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();

        $this->assertStringContainsString('href="https://player.vimeo.com/video/424242"', $html);
        $this->assertStringContainsString('src="' . $this->posterUrl($poster) . '"', $html);
        $this->assertStringContainsString('alt="A grey feeder on a kitchen floor"', $html);
        $this->assertStringNotContainsString('youtube.com/watch', $html, 'no payload video');

        $objects = $this->videoObjects($html);
        $this->assertCount(1, $objects, 'exactly one VideoObject');
        $this->assertSame('Admin bound video', $objects[0]['name']);
        $this->assertSame('The bound review video.', $objects[0]['description']);
        $this->assertSame($this->posterUrl($poster), $objects[0]['thumbnailUrl']);
        $this->assertSame('https://player.vimeo.com/video/424242', $objects[0]['embedUrl']);
        $this->assertSame('2026-09-01T00:00:00+00:00', $objects[0]['uploadDate']);
        $this->assertSame('PT312S', $objects[0]['duration']);
        $this->assertSame([
            ['@type' => 'Clip', 'name' => 'Intro', 'startOffset' => 0, 'endOffset' => 75],
            ['@type' => 'Clip', 'name' => 'Verdict', 'startOffset' => 75, 'endOffset' => 312],
        ], $objects[0]['hasPart']);
    }

    public function test_mode_a_bound_video_without_a_bound_poster_fails_closed(): void
    {
        $this->modeA();
        $payload = $this->payloadWithVideo('pr_no_poster');
        $this->confirm($payload);
        $video = $this->stage($this->adminVideo('vimeo:424243'), 'pr_no_poster', 'video');

        try {
            $this->qaPass($payload);
            $this->fail('QA must refuse a Mode-A video without a poster');
        } catch (EditorialTransitionException $e) {
            $this->assertStringContainsString('needs a bound poster', implode(' ', $e->errors));
        }

        // Even a consistent binding of an active video with no poster fails verification.
        $this->forceActive($video);
        $this->releaseForPublic($payload);
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));

        $response = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertNotFound();
        $this->assertStringNotContainsString('player.vimeo.com', $response->getContent());
        $this->assertSame([], $this->videoObjects($response->getContent()));
        $this->assertFalse($this->sitemapLists(self::HOST, $payload->review_slug));
    }

    public function test_provider_metadata_cannot_stand_in_for_a_bound_poster(): void
    {
        $this->modeA();
        $payload = $this->payloadWithVideo('pr_provider_thumb');
        $this->confirm($payload);
        $asset = $this->adminVideo('vimeo:424244');
        DB::table('media_assets')->where('id', $asset->id)->update(['provider_metadata' => json_encode(['thumbnail_url' => 'https://i.vimeocdn.com/video/424244.jpg'])]);
        $video = $this->stage($asset, 'pr_provider_thumb', 'video');

        $this->forceActive($video);
        $this->releaseForPublic($payload);

        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
        $response = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertNotFound();
        $this->assertStringNotContainsString('i.vimeocdn.com', $response->getContent());
        $this->assertSame([], $this->videoObjects($response->getContent()));
    }

    public function test_mode_a_without_a_bound_video_renders_the_no_video_state(): void
    {
        $this->modeA();
        $payload = $this->payloadWithVideo('pr_no_video');
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->release($payload);

        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('class="videoposter"', $html);
        $this->assertStringNotContainsString('youtube.com/watch', $html);
        $this->assertSame([], $this->videoObjects($html), 'no VideoObject, payload or Admin');
    }

    public function test_a_pending_poster_cannot_satisfy_the_mode_a_requirement(): void
    {
        $this->modeA();

        // At QA: a poster retired before the pass is not in the proposed set.
        $first = $this->payloadWithVideo('pr_pending_poster_qa');
        $this->confirm($first);
        $this->stage($this->adminVideo('vimeo:424245'), 'pr_pending_poster_qa', 'video');
        $retired = $this->stage($this->imageAsset('Alt', 'p245'), 'pr_pending_poster_qa', 'poster');
        $this->registry()->retire($retired, 'Wrong crop', null);
        try {
            $this->qaPass($first);
            $this->fail('QA must refuse without a proposed poster');
        } catch (EditorialTransitionException) {
            $this->addToAssertionCount(1);
        }

        // At the public boundary: an active video with a merely PENDING poster fails closed.
        $second = $this->payloadWithVideo('pr_pending_poster_public');
        $video = $this->stage($this->adminVideo('vimeo:424246'), 'pr_pending_poster_public', 'video');
        $this->stage($this->imageAsset('Alt', 'p246'), 'pr_pending_poster_public', 'poster');
        $this->forceActive($video);
        $this->releaseForPublic($second);

        $this->assertNull(app(PublicReleaseVerifier::class)->verify($second->fresh(), null));
        $this->get(self::HOST . '/review/' . $second->review_slug)->assertNotFound();
    }

    public function test_replacing_the_poster_changes_the_media_fingerprint_and_needs_a_fresh_release(): void
    {
        $this->modeA();
        $payload = $this->payloadWithVideo('pr_poster_swap');
        $this->confirm($payload);
        $this->stage($this->adminVideo('vimeo:424247'), 'pr_poster_swap', 'video');
        $first = $this->imageAsset('First poster', 'p247a');
        $this->stage($first, 'pr_poster_swap', 'poster');
        $this->qaPass($payload);
        $this->release($payload);
        $boundBefore = $this->record($payload)->bound_media_fingerprint;

        $second = $this->imageAsset('Second poster', 'p247b');
        $this->stage($second, 'pr_poster_swap', 'poster');
        $pendingVideo = $this->stage($this->adminVideo('vimeo:424248'), 'pr_poster_swap', 'video');

        $this->assertNotSame($boundBefore, ReviewFingerprints::proposedMedia('pr_poster_swap'));
        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();
        $this->assertStringContainsString($this->posterUrl($first), $html, 'the released poster still renders');
        $this->assertStringNotContainsString($this->posterUrl($second), $html, 'a staged poster never renders before Release');
        $this->assertStringNotContainsString('player.vimeo.com/video/424248', $html, 'a staged video never renders before Release');
        $this->assertStringContainsString('changed since the QA pass', implode(' ', $this->editorial()->releaseBlockers($payload->fresh(), $this->record($payload))));

        $this->qaPass($payload);
        $this->release($payload);
        $this->assertNotSame($boundBefore, $this->record($payload)->bound_media_fingerprint);
        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();
        $this->assertStringContainsString($this->posterUrl($second), $html);
        $this->assertStringNotContainsString($this->posterUrl($first), $html);
        $this->assertSame($this->posterUrl($second), $this->videoObjects($html)[0]['thumbnailUrl']);
        $this->assertSame(MediaAttachment::STATE_ACTIVE, $pendingVideo->fresh()->state);

        // A swap of the bound poster outside Release breaks the M1 binding.
        DB::table('media_attachments')->where('owner_key', 'pr_poster_swap')->where('role', 'poster')
            ->where('state', MediaAttachment::STATE_ACTIVE)->update(['media_asset_id' => $first->id]);
        $this->get(self::HOST . '/review/' . $payload->review_slug)->assertNotFound();
    }

    public function test_mode_a_strips_payload_video_objects_from_the_review_json_schema_fallback(): void
    {
        $this->modeA();
        $payload = $this->ingest($this->reviewRow('pr_schema_fallback', ['schema_json_ld' => null], [
            'video' => self::PAYLOAD_VIDEO,
            'schema' => ['@context' => 'https://schema.org', '@graph' => [
                ['@type' => 'Product', 'name' => 'Test Feeder'],
                ['@type' => 'VideoObject', 'name' => 'Payload fallback video'],
            ]],
        ]));
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->release($payload);

        $html = $this->get(self::HOST . '/review/' . $payload->review_slug)->assertOk()->getContent();
        $this->assertSame([], $this->videoObjects($html));
        $this->assertStringNotContainsString('Payload fallback video', $html);
        $this->assertStringContainsString('"name": "Test Feeder"', $html);
    }
}
