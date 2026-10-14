<?php

namespace Tests\Feature\AdminContent;

use App\HasSubscriptionSections;
use App\Http\Controllers\ChannelController;
use App\Models\ApiUser;
use App\Models\Channel;
use App\Models\User;
use App\Models\Video;
use App\Support\Media\ChannelCardImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BR-CHANNEL-MEDIA-ADAPTER-A0: public channel-discovery listings take card artwork from
 * the managed-media registry (the active `card_image` attachment only) and otherwise fall
 * back to the legacy `channels.image` column. Channel detail (the channel-page hero and
 * intro-video poster source) and every other serialisation keep the legacy value.
 * Every row here is synthetic.
 *
 * Fixture channels:
 *  1 Alpha   - legacy image + active card_image          -> managed URL
 *  2 Beta    - legacy image + active hero_artwork only   -> legacy (roles are separate)
 *  3 Gamma   - no legacy image + active card_image       -> managed URL
 *  4 Delta   - legacy image + pending and retired cards  -> legacy
 *  5 Epsilon - legacy image, no attachments              -> legacy
 */
class ChannelCardImageAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const MEDIA = 'https://media.example.test/storage/media';

    /** Keys of a signed-in `recommended_channels` entry (unchanged from the baseline). */
    private const RECOMMENDED_KEYS = ['id', 'name', 'image_url', 'primary_color', 'secondary_color', 'accent_color',
        'background_color', 'text_color', 'hover_color', 'highlight_color', 'cta', 'channel_category', 'created_at',
        'updated_at', 'watch_count'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.media.url' => self::MEDIA,
            // The public video/channel routes consult the api (JWT) guard for an optional user.
            'jwt.secret' => bin2hex(random_bytes(32)),
        ]);

        if (DB::getDriverName() === 'sqlite') {
            // MySQL's FIND_IN_SET, used by the shared video query, for the in-memory test DB only.
            DB::connection()->getPdo()->sqliteCreateFunction('FIND_IN_SET', function ($needle, $haystack) {
                $position = array_search((string) $needle, explode(',', (string) $haystack), true);

                return $position === false ? 0 : $position + 1;
            }, 2);
        }

        $this->seedFixture();
    }

    private function seedFixture(): void
    {
        DB::table('regions')->insert([
            ['id' => 1, 'region_name' => 'Global', 'currency' => 'USD', 'region_code' => 'GLOBAL'],
            ['id' => 2, 'region_name' => 'United States', 'currency' => 'USD', 'region_code' => 'US'],
        ]);

        $channels = [[1, 'Alpha', 'channel/alpha.png', 'pet'], [2, 'Beta', 'channel/beta.png', 'people'],
            [3, 'Gamma', null, 'pet'], [4, 'Delta', 'channel/delta.png', 'people'], [5, 'Epsilon', 'channel/eps.png', 'pet']];
        foreach ($channels as [$id, $name, $image, $type]) {
            DB::table('channels')->insert([
                'id' => $id, 'name' => $name, 'image' => $image, 'accent_color' => '#11223' . $id,
                'channel_category' => $type, 'created_at' => "2026-10-01 10:00:0{$id}", 'updated_at' => "2026-10-01 10:00:0{$id}",
            ]);
            DB::table('channel_region')->insert(['channel_id' => $id, 'region_id' => 1]);
            DB::table('categories')->insert([
                'id' => $id, 'name' => "Category {$id}", 'slug' => "category-{$id}", 'channel_id' => $id,
                'created_at' => "2026-10-01 10:00:0{$id}", 'updated_at' => "2026-10-01 10:00:0{$id}",
            ]);
            DB::table('category_region')->insert(['category_id' => $id, 'region_id' => 1]);
        }

        foreach ([1, 2, 3] as $id) {
            DB::table('characters')->insert([
                'id' => $id, 'name' => "Host {$id}", 'category_id' => $id,
                'created_at' => "2026-10-01 10:00:0{$id}", 'updated_at' => "2026-10-01 10:00:0{$id}",
            ]);
            DB::table('character_region')->insert(['character_id' => $id, 'region_id' => 1]);
            DB::table('videos')->insert([
                'id' => $id, 'title' => "Video {$id}", 'type' => 'youtube', 'video_url' => "https://www.youtube.com/watch?v=abcdefghij{$id}",
                'character_id' => $id, 'channel_id' => $id, 'category_id' => $id, 'status' => 'published',
                'highlight_tags' => '"2,3"', 'created_at' => "2026-10-01 10:00:0{$id}", 'updated_at' => "2026-10-01 10:00:0{$id}",
            ]);
            DB::table('video_region')->insert(['video_id' => $id, 'region_id' => 1]);
        }

        $this->attach(1, 1, 'card_image', 'active');
        $this->attach(2, 2, 'hero_artwork', 'active');
        $this->attach(3, 3, 'card_image', 'active');
        $this->attach(4, 4, 'card_image', 'pending');
        $this->attach(5, 4, 'card_image', 'retired');
    }

    private function asset(int $id, array $overrides = []): void
    {
        $sha = str_repeat(dechex($id % 16), 64);
        DB::table('media_assets')->insert(array_merge([
            'id' => $id, 'kind' => 'image', 'storage' => 'managed', 'disk' => 'media',
            'path' => 'image/' . substr($sha, 0, 2) . "/{$sha}-{$id}.webp", 'sha256' => $sha,
            'mime_type' => 'image/webp', 'bytes' => 1000 + $id, 'width' => 1280, 'height' => 720, 'alt_text' => "Asset {$id}",
        ], $overrides));
    }

    private function attach(int $assetId, int $channelId, string $role, string $state, array $assetOverrides = []): void
    {
        $this->asset($assetId, $assetOverrides);
        DB::table('media_attachments')->insert([
            'media_asset_id' => $assetId, 'owner_type' => 'channel', 'owner_key' => (string) $channelId, 'role' => $role,
            'position' => 0, 'state' => $state, 'active_slot' => $state === 'active' ? "channel:{$channelId}:{$role}" : null,
        ]);
    }

    private function managedUrl(int $assetId): string
    {
        $sha = str_repeat(dechex($assetId % 16), 64);

        return self::MEDIA . '/image/' . substr($sha, 0, 2) . "/{$sha}-{$assetId}.webp";
    }

    /** @return array<string, ?string> */
    private function expected(): array
    {
        return [
            'Alpha' => $this->managedUrl(1),
            'Beta' => asset('channel/beta.png'),
            'Gamma' => $this->managedUrl(3),
            'Delta' => asset('channel/delta.png'),
            'Epsilon' => asset('channel/eps.png'),
        ];
    }

    /** Every channel-shaped object (id + name + image_url, fixture name) in a response, by name. */
    private function channelImages(array $json): array
    {
        $found = [];
        $walk = function ($node) use (&$walk, &$found) {
            if (! is_array($node)) {
                return;
            }
            if (array_key_exists('image_url', $node) && isset($node['id'], $node['name'])
                && in_array($node['name'], ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta'], true)) {
                $found[$node['name']][] = $node['image_url'];
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($json);

        return $found;
    }

    private function assertChannelImages(string $uri, array $expected, int $minimumChannels): void
    {
        $response = $this->getJson($uri)->assertOk();
        $images = $this->channelImages($response->json());

        $this->assertGreaterThanOrEqual($minimumChannels, count($images), "{$uri}: channels found");
        foreach ($images as $name => $urls) {
            foreach (array_unique($urls, SORT_REGULAR) as $url) {
                $this->assertSame($expected[$name], $url, "{$uri}: {$name}");
            }
        }
    }

    /** @return array<string, ?string> The baseline `asset(channels.image)` value. */
    private function legacy(): array
    {
        return [
            'Alpha' => asset('channel/alpha.png'), 'Beta' => asset('channel/beta.png'), 'Gamma' => null,
            'Delta' => asset('channel/delta.png'), 'Epsilon' => asset('channel/eps.png'),
        ];
    }

    public function test_public_channel_listings_resolve_the_active_card_image_and_fall_back_to_legacy(): void
    {
        $expected = $this->expected();

        $this->assertChannelImages('/api/channels', $expected, 5);
        $this->assertChannelImages('/api/channels/region/GLOBAL', $expected, 5);
        $this->assertChannelImages('/api/channels-pets/region/GLOBAL', $expected, 3);
        $this->assertChannelImages('/api/channels-people/region/GLOBAL', $expected, 2);
        $this->assertChannelImages('/api/region_new/GLOBAL', $expected, 5);
    }

    public function test_channel_detail_keeps_the_legacy_image_even_with_an_active_card_image(): void
    {
        // Alpha and Gamma have active card images; detail must still serve the legacy value.
        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma', 4 => 'Delta', 5 => 'Epsilon'] as $id => $name) {
            $this->getJson("/api/channel-detail/{$id}/GLOBAL")
                ->assertOk()
                ->assertJsonPath('data.name', $name)
                ->assertJsonPath('data.image_url', $this->legacy()[$name]);
        }
    }

    public function test_the_intro_video_poster_source_is_unchanged(): void
    {
        // The channel page uses detail image_url as the hero and as the poster of an MP4
        // intro video. Neither the image nor the video reference may change.
        DB::table('channels')->where('id', 1)->update(['video' => 'channel_videos/alpha-intro.mp4']);
        $this->attach(6, 1, 'hero_artwork', 'active');

        $this->getJson('/api/channel-detail/1/GLOBAL')
            ->assertOk()
            ->assertJsonPath('data.image_url', asset('channel/alpha.png'))
            ->assertJsonPath('data.video_url', asset('channel_videos/alpha-intro.mp4'));
    }

    public function test_video_listing_payloads_use_the_resolver(): void
    {
        $this->assertChannelImages('/api/hot-this-week/GLOBAL', $this->expected(), 3);

        // These routes use MySQL-only SQL (a double-quoted string literal), so they are
        // exercised when the suite runs on MySQL, which the qualification always does.
        if (DB::getDriverName() === 'mysql') {
            $this->assertChannelImages('/api/trending-videos/GLOBAL', $this->expected(), 3);
            $this->assertChannelImages('/api/top-this-week/GLOBAL', $this->expected(), 3);
            $this->assertChannelImages('/api/top-deals/GLOBAL', $this->expected(), 3);
            $this->assertChannelImages('/api/categories-pet/region/GLOBAL', $this->expected(), 1);
            $this->assertChannelImages('/api/categories-people/region/GLOBAL', $this->expected(), 1);

            // Category detail: its channel cards use card artwork; the category's own parent
            // channel (`data.channel`, used for theme colours) is a nested model and stays legacy.
            foreach ([1 => 'Alpha', 3 => 'Gamma'] as $categoryId => $name) {
                $json = $this->getJson("/api/categories-detail/region/GLOBAL/{$categoryId}")->assertOk()->json();
                $this->assertSame($name, $json['data']['channel']['name']);
                $this->assertSame($this->legacy()[$name], $json['data']['channel']['image_url']);
                unset($json['data']['channel']);
                $images = $this->channelImages($json);
                $this->assertArrayHasKey($name, $images);
                foreach ($images as $channel => $urls) {
                    $this->assertSame([$this->expected()[$channel]], array_values(array_unique($urls, SORT_REGULAR)), "categories-detail {$categoryId}: {$channel}");
                }
            }
        }
    }

    public function test_subscription_section_mappers_use_the_resolver(): void
    {
        $sections = new class {
            use HasSubscriptionSections;

            public function channel($channel): array
            {
                return $this->mapChannel($channel);
            }

            public function video($video): array
            {
                return $this->mapVideo($video);
            }
        };

        foreach ($this->expected() as $name => $url) {
            $channel = Channel::query()->where('name', $name)->firstOrFail();
            $this->assertSame($url, $sections->channel($channel)['image_url'], "mapChannel {$name}");
        }

        foreach ([1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma'] as $videoId => $name) {
            $video = Video::query()->with(['regions', 'channel'])->findOrFail($videoId);
            $this->assertSame($this->expected()[$name], $sections->video($video)['channel']['image_url'], "mapVideo {$name}");
        }
    }

    public function test_hero_artwork_and_card_images_are_never_substituted_for_each_other(): void
    {
        // Beta has only hero_artwork: legacy in listings. Give Epsilon both roles: listings
        // use card_image (hero ignored); detail uses neither and keeps the legacy value.
        $this->attach(6, 5, 'hero_artwork', 'active');
        $this->attach(7, 5, 'card_image', 'active');

        $images = $this->channelImages($this->getJson('/api/channels')->assertOk()->json());
        $this->assertSame([asset('channel/beta.png')], array_values(array_unique($images['Beta'])));
        $this->assertSame([$this->managedUrl(7)], array_values(array_unique($images['Epsilon'])));

        $this->getJson('/api/channel-detail/2/GLOBAL')->assertOk()->assertJsonPath('data.image_url', asset('channel/beta.png'));
        $this->getJson('/api/channel-detail/5/GLOBAL')->assertOk()->assertJsonPath('data.image_url', asset('channel/eps.png'));
    }

    public function test_unrelated_serialisations_keep_the_legacy_image(): void
    {
        // Nested channel objects (categories) and the model's default serialisation - used by
        // admin, console and queued work - are not discovery listings and stay legacy.
        $images = $this->channelImages($this->getJson('/api/categories')->assertOk()->json());
        $this->assertGreaterThanOrEqual(5, count($images));
        foreach ($images as $name => $urls) {
            $this->assertSame([$this->legacy()[$name]], array_values(array_unique($urls, SORT_REGULAR)), "/api/categories: {$name}");
        }

        $alpha = Channel::query()->findOrFail(1);
        $this->assertSame(asset('channel/alpha.png'), $alpha->image_url);
        $this->assertSame(asset('channel/alpha.png'), $alpha->toArray()['image_url']);
        $this->assertSame(asset('channel/alpha.png'), json_decode($alpha->toJson(), true)['image_url']);
        $this->assertNull(Channel::query()->findOrFail(3)->toArray()['image_url']);

        // Opting in is explicit and per instance.
        $this->assertSame($this->managedUrl(1), Channel::query()->findOrFail(1)->useCardImage()->toArray()['image_url']);
        $this->assertSame(asset('channel/alpha.png'), Channel::query()->findOrFail(1)->toArray()['image_url']);
    }

    /** Signs in a synthetic API user (factory data only) on the JWT `api` guard. */
    private function signIn(): ApiUser
    {
        $user = ApiUser::query()->findOrFail(User::factory()->create()->id);
        $this->actingAs($user, 'api');

        return $user;
    }

    private function requiresMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The category endpoints use MySQL-only SQL; the qualification runs this test on MySQL.');
        }
    }

    /** @return array<string, ?string> image_url by channel name, sorted by name */
    private function imagesByName(array $rows): array
    {
        $images = array_column($rows, 'image_url', 'name');
        ksort($images);

        return $images;
    }

    /** Channel detail is the channel-page hero and intro-video poster source: always legacy. */
    private function assertDetailIsLegacy(int $channelId, string $name): void
    {
        $this->getJson("/api/channel-detail/{$channelId}/GLOBAL")
            ->assertOk()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.image_url', $this->legacy()[$name]);
    }

    public function test_signed_in_pet_category_recommendations_use_card_images(): void
    {
        $this->requiresMysql();

        // The recommendation branch runs only for a signed-in viewer.
        $this->assertSame([], $this->getJson('/api/categories-pet/region/GLOBAL')->assertOk()->json('data.recommended_channels'));

        $this->signIn();
        $rows = $this->getJson('/api/categories-pet/region/GLOBAL')->assertOk()->json('data.recommended_channels');

        $this->assertSame(['Alpha' => $this->managedUrl(1), 'Epsilon' => asset('channel/eps.png'), 'Gamma' => $this->managedUrl(3)],
            $this->imagesByName($rows));
        $this->assertSame(self::RECOMMENDED_KEYS, array_keys($rows[0]));
        $this->assertDetailIsLegacy(1, 'Alpha');
        $this->assertDetailIsLegacy(3, 'Gamma');
    }

    public function test_signed_in_people_category_recommendations_use_card_images(): void
    {
        $this->requiresMysql();

        // Beta already has an active hero_artwork; give it an active card image as well.
        $this->attach(6, 2, 'card_image', 'active');

        $this->assertSame([], $this->getJson('/api/categories-people/region/GLOBAL')->assertOk()->json('data.recommended_channels'));

        $this->signIn();
        $rows = $this->getJson('/api/categories-people/region/GLOBAL')->assertOk()->json('data.recommended_channels');

        // Beta: the card image, not the hero. Delta: pending and retired cards are ignored.
        $this->assertSame(['Beta' => $this->managedUrl(6), 'Delta' => asset('channel/delta.png')], $this->imagesByName($rows));
        $this->assertSame(self::RECOMMENDED_KEYS, array_keys($rows[0]));
        $this->assertDetailIsLegacy(2, 'Beta');
        $this->assertDetailIsLegacy(4, 'Delta');
    }

    public function test_signed_in_category_detail_recommendations_use_card_images(): void
    {
        $this->requiresMysql();

        $this->assertSame([], $this->getJson('/api/categories-detail/region/GLOBAL/1')->assertOk()->json('recommended_channels'));

        $this->signIn();
        $json = $this->getJson('/api/categories-detail/region/GLOBAL/1')->assertOk()->json();

        $this->assertSame([
            'Alpha' => $this->managedUrl(1), 'Beta' => asset('channel/beta.png'), 'Delta' => asset('channel/delta.png'),
            'Epsilon' => asset('channel/eps.png'), 'Gamma' => $this->managedUrl(3),
        ], $this->imagesByName($json['recommended_channels']));
        // This endpoint's entries also carry the baseline `final_beastie_score`.
        $this->assertSame([...self::RECOMMENDED_KEYS, 'final_beastie_score'], array_keys($json['recommended_channels'][0]));
        // The category's own parent channel (used for theme colours) is a nested model: legacy.
        $this->assertSame(asset('channel/alpha.png'), $json['data']['channel']['image_url']);
        $this->assertDetailIsLegacy(1, 'Alpha');
    }

    public function test_my_lair_followed_channels_use_card_images(): void
    {
        $this->getJson('/api/channel/follows')->assertUnauthorized();

        $user = $this->signIn();
        foreach ([1, 2, 3, 4] as $channelId) {
            DB::table('follow_channels')->insert([
                'channel_id' => $channelId, 'user_id' => $user->id,
                'created_at' => '2026-10-02 00:00:00', 'updated_at' => '2026-10-02 00:00:00',
            ]);
        }

        $rows = $this->getJson('/api/channel/follows')->assertOk()->json('data');

        $this->assertSame([
            'Alpha' => $this->managedUrl(1), 'Beta' => asset('channel/beta.png'),
            'Delta' => asset('channel/delta.png'), 'Gamma' => $this->managedUrl(3),
        ], $this->imagesByName($rows));
        $this->assertSame(['id', 'name', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color',
            'hover_color', 'highlight_color', 'cta', 'created_at', 'updated_at', 'image_url'], array_keys($rows[0]));

        // Signed in and following: the channel page's hero and intro-video poster source stay legacy.
        DB::table('channels')->where('id', 1)->update(['video' => 'channel_videos/alpha-intro.mp4']);
        $this->getJson('/api/channel-detail/1/GLOBAL')
            ->assertOk()
            ->assertJsonPath('data.image_url', asset('channel/alpha.png'))
            ->assertJsonPath('data.video_url', asset('channel_videos/alpha-intro.mp4'));
    }

    public function test_the_region_filter_action_resolves_card_images(): void
    {
        // routes/api.php registers GET /region/{x} twice; RegionController::showByCode (line 219)
        // is matched before ChannelController::filter_region_api (line 235), so over HTTP this
        // path serves region metadata. Any channel object it returns must still follow the rule.
        foreach ($this->channelImages($this->getJson('/api/region/GLOBAL')->assertOk()->json()) as $name => $urls) {
            $this->assertSame([$this->expected()[$name]], array_values(array_unique($urls, SORT_REGULAR)), "/api/region: {$name}");
        }

        // The action itself, on its own request. Unfiltered, it lists every GLOBAL channel.
        $request = Request::create('/api/region/GLOBAL', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $request);
        $json = $this->app->make(ChannelController::class)->filter_region_api($request, 'GLOBAL')->getData(true);

        $expected = $this->expected();
        ksort($expected);
        $this->assertSame($expected, $this->imagesByName($json['data']['channels']));
        $this->assertDetailIsLegacy(1, 'Alpha');
    }

    public function test_pending_retired_archived_and_non_image_attachments_are_ignored(): void
    {
        DB::table('channels')->insert([
            ['id' => 6, 'name' => 'Zeta', 'image' => 'channel/zeta.png', 'channel_category' => 'pet'],
            ['id' => 7, 'name' => 'Eta', 'image' => null, 'channel_category' => 'people'],
        ]);
        // An archived asset and a non-image asset behind active card_image attachments.
        $this->attach(8, 6, 'card_image', 'active', ['archived_at' => '2026-10-03 00:00:00']);
        $this->attach(9, 7, 'card_image', 'active', ['kind' => 'video', 'storage' => 'external', 'disk' => null, 'path' => null,
            'provider' => 'vimeo', 'provider_asset_id' => '123456789', 'mime_type' => null, 'width' => null, 'height' => null]);

        $images = $this->channelImages($this->getJson('/api/channels')->assertOk()->json());
        $this->assertSame([asset('channel/delta.png')], array_values(array_unique($images['Delta'])), 'pending and retired ignored');
        $this->assertSame([asset('channel/zeta.png')], array_values(array_unique($images['Zeta'])), 'archived asset ignored');
        $this->assertSame([null], array_values(array_unique($images['Eta'], SORT_REGULAR)), 'non-image asset ignored');
    }

    public function test_an_unset_media_url_fails_closed_to_the_legacy_image(): void
    {
        config(['filesystems.disks.media.url' => null]);

        $images = $this->channelImages($this->getJson('/api/channels')->assertOk()->json());
        $this->assertSame([asset('channel/alpha.png')], array_values(array_unique($images['Alpha'])));
        $this->assertSame([null], array_values(array_unique($images['Gamma'], SORT_REGULAR)));
    }

    public function test_card_images_are_resolved_in_one_batched_lookup_per_request(): void
    {
        $count = function (string $uri): array {
            $queries = [];
            DB::listen(function ($query) use (&$queries) {
                $queries[] = $query->sql;
            });
            $this->getJson($uri)->assertOk();
            DB::flushQueryLog();

            return [
                'attachments' => count(array_filter($queries, fn ($sql) => str_contains($sql, 'media_attachments'))),
                'assets' => count(array_filter($queries, fn ($sql) => str_contains($sql, 'media_assets'))),
            ];
        };

        $small = $count('/api/channels');

        // Ten more channels, each with an active card image.
        for ($id = 10; $id < 20; $id++) {
            DB::table('channels')->insert(['id' => $id, 'name' => "Extra {$id}", 'image' => null, 'channel_category' => 'pet']);
            DB::table('channel_region')->insert(['channel_id' => $id, 'region_id' => 1]);
            $this->attach($id + 10, $id, 'card_image', 'active');
        }
        $large = $count('/api/channels');

        $this->assertSame(['attachments' => 1, 'assets' => 1], $small);
        $this->assertSame($small, $large, 'query count does not grow with the number of channels');

        // Paths that are not discovery listings never reach the media lookup.
        $this->assertSame(['attachments' => 0, 'assets' => 0], $count('/api/channel-detail/1/GLOBAL'));
        $this->assertSame(['attachments' => 0, 'assets' => 0], $count('/api/categories'));
    }

    public function test_without_card_images_responses_keep_the_legacy_values_and_keys(): void
    {
        DB::table('media_attachments')->delete();

        $legacy = [
            'Alpha' => asset('channel/alpha.png'), 'Beta' => asset('channel/beta.png'), 'Gamma' => null,
            'Delta' => asset('channel/delta.png'), 'Epsilon' => asset('channel/eps.png'),
        ];
        foreach (['/api/channels', '/api/channels/region/GLOBAL', '/api/channels-pets/region/GLOBAL',
            '/api/channels-people/region/GLOBAL', '/api/region_new/GLOBAL', '/api/categories', '/api/hot-this-week/GLOBAL'] as $uri) {
            $this->assertChannelImages($uri, $legacy, 2);
        }

        $item = $this->getJson('/api/channels')->assertOk()->json('data.0');
        $this->assertSame(['id', 'name', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color',
            'hover_color', 'highlight_color', 'cta', 'created_at', 'updated_at', 'image_url', 'regions'], array_keys($item));
        $this->assertSame(asset('channel/eps.png'), $this->getJson('/api/channel-detail/5/GLOBAL')->json('data.image_url'));
    }

    public function test_the_resolver_returns_null_for_unknown_or_missing_channel_ids(): void
    {
        $resolver = new ChannelCardImages();

        $this->assertNull($resolver->urlFor(null));
        $this->assertNull($resolver->urlFor(''));
        $this->assertNull($resolver->urlFor(999));
        $this->assertSame($this->managedUrl(1), $resolver->urlFor(1));
        $this->assertSame($this->managedUrl(1), $resolver->urlFor('1'));
    }
}
