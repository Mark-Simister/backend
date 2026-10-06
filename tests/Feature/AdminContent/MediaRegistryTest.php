<?php

namespace Tests\Feature\AdminContent;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Region;
use App\Services\Media\MediaRegistryException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * The provider-neutral media registry and attachment lifecycle (design record section 7).
 * Managed-file storage is exercised against a mocked media disk, so no file is written.
 */
class MediaRegistryTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->associations();
    }

    public static function acceptedReferences(): array
    {
        return [
            'vimeo short form' => ['vimeo:123456789', 'vimeo:123456789'],
            'vimeo url' => ['https://vimeo.com/123456789', 'vimeo:123456789'],
            'vimeo player url' => ['https://player.vimeo.com/video/123456789?h=abc', 'vimeo:123456789'],
            'youtube short form' => ['youtube:dQw4w9WgXcQ', 'youtube:dQw4w9WgXcQ'],
            'youtube watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5', 'youtube:dQw4w9WgXcQ'],
            'youtu.be url' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube:dQw4w9WgXcQ'],
            'youtube embed url' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'youtube:dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('acceptedReferences')]
    public function test_allow_listed_references_are_parsed(string $input, string $expected): void
    {
        $this->assertSame($expected, implode(':', $this->registry()->parseExternalReference($input)));
    }

    public static function rejectedReferences(): array
    {
        return [
            'plain http' => ['http://vimeo.com/123456789'],
            'unknown provider' => ['dailymotion:x7abc'],
            'unknown host' => ['https://example.com/video/123'],
            'bad vimeo id' => ['vimeo:12ab'],
            'bad youtube id' => ['youtube:short'],
            'script scheme' => ['javascript:alert(1)'],
            'file path' => ['/storage/videos/a.mp4'],
        ];
    }

    #[DataProvider('rejectedReferences')]
    public function test_other_references_are_refused(string $input): void
    {
        $this->expectException(MediaRegistryException::class);
        $this->registry()->parseExternalReference($input);
    }

    public function test_embed_urls_are_built_on_the_server(): void
    {
        $this->assertSame('https://player.vimeo.com/video/123456789', $this->videoAsset()->embedUrl());
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $this->videoAsset('https://youtu.be/dQw4w9WgXcQ')->embedUrl());
        $this->assertNull($this->imageAsset()->embedUrl());
        $this->assertNull($this->imageAsset('x', 'b')->publicUrl(), 'no public URL without an explicit MEDIA_URL');
    }

    public function test_youtube_ids_are_case_sensitive(): void
    {
        $this->videoAsset('youtube:dQw4w9WgXcQ');
        $this->videoAsset('youtube:DQW4W9WGXCQ');

        $this->assertSame(2, MediaAsset::query()->where('provider', 'youtube')->count());
    }

    public function test_a_reference_is_registered_once_and_its_identity_is_immutable(): void
    {
        $asset = $this->videoAsset();

        try {
            $this->videoAsset('https://vimeo.com/123456789');
            $this->fail('a duplicate reference must be refused');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('already registered', $e->getMessage());
        }

        $this->expectException(LogicException::class);
        $asset->forceFill(['external_ref' => 'vimeo:1'])->save();
    }

    public function test_a_managed_image_is_content_addressed_on_the_media_disk(): void
    {
        $source = base_path('public/admin/assets/images/beastierated_logo_old.png');
        $sha = hash_file('sha256', $source);
        $file = new UploadedFile($source, 'poster.PNG', 'image/png', null, true);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->with('image/' . substr($sha, 0, 2), $file, $sha . '.png')->andReturn('ok');
        Storage::shouldReceive('disk')->once()->with('media')->andReturn($disk);

        $asset = $this->registry()->storeManaged($file, 'image', ['alt_text' => 'BeastieRated logo', 'title' => 'Logo'], null);

        $this->assertSame(MediaAsset::STORAGE_MANAGED, $asset->storage);
        $this->assertSame('media', $asset->disk);
        $this->assertSame('image/' . substr($sha, 0, 2) . '/' . $sha . '.png', $asset->path);
        $this->assertSame($sha, $asset->sha256);
        $this->assertSame(mime_content_type($source), $asset->mime_type, 'the type is detected from the bytes, not the name');
        $this->assertSame(filesize($source), $asset->bytes);
        $this->assertGreaterThan(0, $asset->width);
        $this->assertSame('BeastieRated logo', $asset->alt_text);
    }

    public function test_managed_uploads_are_validated_before_anything_is_stored(): void
    {
        Storage::shouldReceive('disk')->never();
        $source = base_path('public/admin/assets/images/beastierated_logo_old.png');

        $cases = [
            'video is never managed' => fn () => $this->registry()->storeManaged(new UploadedFile($source, 'a.png', null, null, true), 'video', [], null),
            'extension allowlist' => fn () => $this->registry()->storeManaged(new UploadedFile($source, 'a.gif', null, null, true), 'image', [], null),
            'audio extension allowlist' => fn () => $this->registry()->storeManaged(new UploadedFile($source, 'a.png', null, null, true), 'audio', [], null),
            'image content sniffed' => fn () => $this->registry()->storeManaged(new UploadedFile(base_path('composer.json'), 'a.png', null, null, true), 'image', [], null),
        ];
        foreach ($cases as $label => $case) {
            try {
                $case();
                $this->fail("{$label} must be refused");
            } catch (MediaRegistryException) {
                $this->addToAssertionCount(1);
            }
        }

        config(['media.managed.image.max_kb' => 1]);
        $this->expectException(MediaRegistryException::class);
        $this->registry()->storeManaged(new UploadedFile($source, 'a.png', null, null, true), 'image', [], null);
    }

    /*
     * Managed audio is accepted only when the type fileinfo detects from the bytes is one
     * listed for the file's own extension. The fixtures in tests/Fixtures/AdminContent/audio
     * are synthetic (invented bytes, 32-1261 bytes): a PCM WAVE header, ID3 + three MPEG-1
     * Layer III frame headers, an Ogg page carrying a Vorbis identification header, and
     * ISO-BMFF `ftyp` boxes with the "M4A " brand and the "mp42" (video container) brand.
     */
    public static function acceptedAudio(): array
    {
        return [
            'mp3' => ['synthetic.mp3', 'mp3', 'audio/mpeg'],
            'm4a' => ['synthetic.m4a', 'm4a', 'audio/x-m4a'],
            'ogg' => ['synthetic.ogg', 'ogg', 'audio/ogg'],
            'wav' => ['synthetic.wav', 'wav', 'audio/x-wav'],
        ];
    }

    #[DataProvider('acceptedAudio')]
    public function test_managed_audio_is_accepted_when_its_detected_content_matches_its_extension(string $fixture, string $extension, string $detected): void
    {
        $source = base_path('tests/Fixtures/AdminContent/audio/' . $fixture);
        $sha = hash_file('sha256', $source);
        // The client claims a generic type and an upper-case extension: neither is trusted.
        $file = new UploadedFile($source, 'theme.' . strtoupper($extension), 'application/octet-stream', null, true);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->with('audio/' . substr($sha, 0, 2), $file, $sha . '.' . $extension)->andReturn('ok');
        Storage::shouldReceive('disk')->once()->with('media')->andReturn($disk);

        $asset = $this->registry()->storeManaged($file, MediaAsset::KIND_AUDIO, ['title' => 'Theme'], null);

        $this->assertSame(MediaAsset::KIND_AUDIO, $asset->kind);
        $this->assertSame($detected, $asset->mime_type, 'the detected type is stored, not the client claim');
        $this->assertSame('audio/' . substr($sha, 0, 2) . '/' . $sha . '.' . $extension, $asset->path);
        $this->assertNull($asset->width);

        // The existing role/kind checks still apply to the stored audio asset.
        $this->assertSame(MediaAttachment::STATE_PENDING, $this->registry()->stage($asset, MediaAttachment::OWNER_CHARACTER, 'CHR_REX', 'voice_sample', null)->state);
        $this->expectException(MediaRegistryException::class);
        $this->registry()->stage($asset, MediaAttachment::OWNER_CHARACTER, 'CHR_REX', 'portrait', null);
    }

    public static function refusedAudio(): array
    {
        return [
            'png image renamed .mp3' => ['public/admin/assets/images/beastierated_logo.png', 'song.mp3'],
            'json text renamed .wav' => ['composer.json', 'song.wav'],
            'png image renamed .ogg' => ['public/admin/assets/images/beastierated_logo.png', 'song.ogg'],
            'wav content named .mp3' => ['tests/Fixtures/AdminContent/audio/synthetic.wav', 'song.mp3'],
            'mp3 content named .ogg' => ['tests/Fixtures/AdminContent/audio/synthetic.mp3', 'song.ogg'],
            'ogg content named .m4a' => ['tests/Fixtures/AdminContent/audio/synthetic.ogg', 'song.m4a'],
            'm4a content named .wav' => ['tests/Fixtures/AdminContent/audio/synthetic.m4a', 'song.wav'],
            'mp4 video container named .m4a' => ['tests/Fixtures/AdminContent/audio/synthetic-video-container.m4a', 'song.m4a'],
        ];
    }

    #[DataProvider('refusedAudio')]
    public function test_managed_audio_whose_detected_content_does_not_match_is_refused(string $relativeSource, string $clientName): void
    {
        Storage::shouldReceive('disk')->never();

        try {
            // The client also CLAIMS an audio type; only the detected content counts.
            $this->registry()->storeManaged(new UploadedFile(base_path($relativeSource), $clientName, 'audio/mpeg', null, true), MediaAsset::KIND_AUDIO, [], null);
            $this->fail('the upload must be refused');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('is not .' . pathinfo($clientName, PATHINFO_EXTENSION) . ' audio', $e->getMessage());
        }

        $this->assertSame(0, MediaAsset::query()->count(), 'nothing is registered');
    }

    public function test_staging_checks_role_and_kind_and_keeps_one_pending_per_single_role(): void
    {
        $video = $this->videoAsset();
        $image = $this->imageAsset();

        foreach ([
            fn () => $this->registry()->stage($image, MediaAttachment::OWNER_REVIEW, 'pr_x', 'video', null),
            fn () => $this->registry()->stage($video, MediaAttachment::OWNER_REVIEW, 'pr_x', 'not_a_role', null),
            fn () => $this->registry()->stage($video, MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'card_thumbnail', null),
        ] as $refused) {
            try {
                $refused();
                $this->fail('expected a refusal');
            } catch (MediaRegistryException) {
                $this->addToAssertionCount(1);
            }
        }

        $first = $this->registry()->stage($image, MediaAttachment::OWNER_REVIEW, 'pr_x', 'poster', null);
        $second = $this->registry()->stage($this->imageAsset('alt', 'b'), MediaAttachment::OWNER_REVIEW, 'pr_x', 'poster', null);

        $this->assertSame(MediaAttachment::STATE_RETIRED, $first->fresh()->state);
        $this->assertSame(MediaAttachment::STATE_PENDING, $second->fresh()->state);
    }

    public function test_multi_occupancy_roles_take_positions(): void
    {
        $a = $this->registry()->stage($this->audioAsset('a'), MediaAttachment::OWNER_CHARACTER, 'CHR_REX', 'signature_song', null);
        $b = $this->registry()->stage($this->audioAsset('b'), MediaAttachment::OWNER_CHARACTER, 'CHR_REX', 'signature_song', null);

        $this->assertSame([1, 2], [$a->position, $b->position]);
        $this->assertSame(MediaAttachment::STATE_PENDING, $a->fresh()->state, 'multi roles never supersede');
    }

    public function test_activation_replaces_the_single_occupant_atomically(): void
    {
        $old = $this->registry()->activate($this->registry()->stage($this->imageAsset('Old art', 'a'), MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'hero_artwork', null), null);
        $new = $this->registry()->activate($this->registry()->stage($this->imageAsset('New art', 'b'), MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'hero_artwork', null), null);

        $this->assertSame(MediaAttachment::STATE_RETIRED, $old->fresh()->state);
        $this->assertNull($old->fresh()->active_slot);
        $this->assertSame('replaced', $old->fresh()->retire_reason);
        $this->assertSame('channel:CH_PET:hero_artwork', $new->fresh()->active_slot);
        $this->assertSame(1, MediaAttachment::query()->where('state', MediaAttachment::STATE_ACTIVE)->count());
    }

    public function test_the_database_enforces_one_active_occupant_per_slot(): void
    {
        $active = $this->registry()->activate($this->registry()->stage($this->imageAsset(), MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'card_image', null), null);

        $this->expectException(QueryException::class);
        DB::table('media_attachments')->insert([
            'media_asset_id' => $active->media_asset_id, 'owner_type' => 'channel', 'owner_key' => 'CH_PET', 'role' => 'card_image',
            'position' => 0, 'state' => 'active', 'active_slot' => 'channel:CH_PET:card_image',
        ]);
    }

    public function test_an_image_needs_alt_text_and_a_video_its_object_inputs_to_go_public(): void
    {
        $noAlt = $this->registry()->stage($this->imageAsset(null), MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'card_image', null);
        try {
            $this->registry()->activate($noAlt, null);
            $this->fail('an image without alt text must not activate');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('needs alt text', $e->getMessage());
        }

        $bareVideo = $this->registry()->registerExternal('vimeo:555', [], null);
        $staged = $this->registry()->stage($bareVideo, MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'intro_video', null);
        $errors = $this->registry()->publishabilityErrors($staged);
        $this->assertCount(3, $errors, 'title, description and provider_published_at');
    }

    public function test_review_media_activates_only_through_release(): void
    {
        $staged = $this->registry()->stage($this->videoAsset(), MediaAttachment::OWNER_REVIEW, 'pr_test_001', 'video', null);

        try {
            $this->registry()->activate($staged, null);
            $this->fail('review media must not activate outside Release');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('only through Release', $e->getMessage());
        }

        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->release($payload);
        $this->assertSame(MediaAttachment::STATE_ACTIVE, $staged->fresh()->state);

        try {
            $this->registry()->retire($staged->fresh(), 'swap', null);
            $this->fail('released review media cannot be retired directly');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('stage the replacement and release again', $e->getMessage());
        }
    }

    public function test_rendered_fields_of_captured_media_are_immutable(): void
    {
        $video = $this->videoAsset();
        $this->registry()->stage($video, MediaAttachment::OWNER_REVIEW, 'pr_test_001', 'video', null);
        $this->releasedReview();

        try {
            $this->registry()->updateMetadata($video->fresh(), ['title' => 'Retitled'], null);
            $this->fail('a captured title must not change');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString('captured by a released review', $e->getMessage());
        }

        $this->registry()->updateMetadata($video->fresh(), ['rights_note' => 'Licensed to 2027'], null);
        $this->assertSame('Licensed to 2027', $video->fresh()->rights_note, 'non-rendered fields stay editable');

        $this->expectException(LogicException::class);
        $video->fresh()->forceFill(['description' => 'model-level guard'])->save();
    }

    public function test_metadata_updates_are_normalised_and_audited(): void
    {
        $video = $this->registry()->registerExternal('vimeo:777', [], null);

        $this->registry()->updateMetadata($video, [
            'title' => '  Feeder  ', 'chapters' => '[{"t": 0, "label": "Intro"}, {"t": "75", "label": "Verdict"}]', 'duration_seconds' => '312',
        ], null);

        $fresh = $video->fresh();
        $this->assertSame('Feeder', $fresh->title);
        $this->assertSame([['t' => 0, 'label' => 'Intro'], ['t' => 75, 'label' => 'Verdict']], $fresh->chapters);
        $this->assertSame(312, $fresh->duration_seconds);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.metadata_updated', 'subject_key' => (string) $video->id]);

        $this->expectException(MediaRegistryException::class);
        $this->registry()->updateMetadata($video, ['chapters' => '[{"label": "no time"}]'], null);
    }

    public function test_archive_is_refused_while_in_use_and_nothing_is_ever_deleted(): void
    {
        $video = $this->videoAsset();
        $staged = $this->registry()->stage($video, MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'intro_video', null);

        $this->assertArchiveRefused($video, 'still attached');

        $this->registry()->retire($staged, 'not needed', null);
        $this->insertBestForEdition(videoAssetId: 'vimeo:123456789');
        $this->assertArchiveRefused($video, 'Best For edition');

        $image = $this->imageAsset();
        $this->insertBestForEdition(videoAssetId: 'vimeo:999', imageRef: $image->path, key: 'edition-2');
        $this->assertArchiveRefused($image, 'published Best For selection');

        $free = $this->imageAsset('free', 'c');
        $archived = $this->registry()->archive($free, 'Superseded', null);
        $this->assertTrue($archived->isArchived());
        $this->assertDatabaseHas('media_assets', ['id' => $free->id]);

        try {
            $this->registry()->stage($archived, MediaAttachment::OWNER_CHANNEL, 'CH_PET', 'card_image', null);
            $this->fail('an archived asset cannot be attached');
        } catch (MediaRegistryException) {
            $this->addToAssertionCount(1);
        }

        foreach ([fn () => $free->delete(), fn () => $staged->fresh()->delete()] as $delete) {
            try {
                $delete();
                $this->fail('hard delete must be refused');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(1, MediaAttachment::query()->count());
    }

    private function assertArchiveRefused(MediaAsset $asset, string $fragment): void
    {
        try {
            $this->registry()->archive($asset, 'try', null);
            $this->fail('archive must be refused');
        } catch (MediaRegistryException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
        $this->assertFalse($asset->fresh()->isArchived());
    }

    private function audioAsset(string $seed): MediaAsset
    {
        $sha = hash('sha256', 'audio-' . $seed);

        return MediaAsset::create([
            'kind' => MediaAsset::KIND_AUDIO, 'storage' => MediaAsset::STORAGE_MANAGED, 'disk' => 'media',
            'path' => 'audio/' . substr($sha, 0, 2) . '/' . $sha . '.mp3', 'sha256' => $sha, 'title' => 'Song ' . $seed,
        ]);
    }

    private function insertBestForEdition(string $videoAssetId, ?string $imageRef = null, string $key = 'edition-1'): void
    {
        $region = Region::query()->where('region_code', 'AU')->first();
        if (! $region) {
            $region = new Region();
            $region->forceFill(['region_name' => 'Australia', 'region_code' => 'AU', 'is_active' => 1, 'currency' => 'AUD', 'currency_symbol' => '$'])->save();
        }
        $subjectId = DB::table('best_for_subjects')->insertGetId([
            'category_id' => Category::create(['name' => 'Subject ' . $key])->id, 'year' => 2026, 'region_id' => $region->id,
        ]);
        $editionId = DB::table('best_for_editions')->insertGetId([
            'public_edition_key' => $key, 'subject_id' => $subjectId, 'edition_sequence' => 1,
            'category_name_as_published' => 'Feeders', 'year_as_published' => 2026,
            'methodology_text' => 'Method', 'video_asset_id' => $videoAssetId,
        ]);
        if ($imageRef !== null) {
            DB::table('best_for_edition_selections')->insert([
                'edition_id' => $editionId, 'position' => 1, 'published_review_id' => 'pr_bf', 'review_slug_as_published' => 'bf',
                'product_name_as_published' => 'Feeder', 'product_image_ref_as_published' => $imageRef, 'superlative' => 'Best overall',
                'selection_reason' => 'Reason', 'beastie_score_as_published' => 4.5, 'public_rating_as_published' => 4.3,
                'public_rating_count_as_published' => 10, 'public_signal_as_published' => 'Strong',
            ]);
        }
    }
}
