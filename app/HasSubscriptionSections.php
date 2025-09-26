<?php

namespace App;

use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Video;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait HasSubscriptionSections
{
    /** Reuse your subscription logic */
    protected function hasValidSubscription(int $userId): bool
    {
        $sub = Subscription::where('user_id', $userId)
            ->latest('subscription_end_date')
            ->first();

        if (!$sub || $sub->trashed()) {
            return false;
        }

        $now = now();
        $statusOkay   = in_array($sub->subscription_status, ['active', 'trialing'], true);
        $notCanceled  = $sub->subscription_status !== 'canceled' && is_null($sub->canceled_at);
        $withinPaid   = $sub->subscription_end_date && $now->lte($sub->subscription_end_date);
        $withinTrial  = $sub->trial_end_date && $now->lte($sub->trial_end_date);
        $timeOkay     = $withinPaid || $withinTrial;
        $paymentOkay  = ($sub->payment_status === 'succeeded') || ($sub->subscription_status === 'trialing');

        return $statusOkay && $notCanceled && $timeOkay && $paymentOkay;
    }

    /** AU|CA|UK|US else GLOBAL */
    protected function resolveRegionCode(?string $region): string
    {
        $code = strtoupper((string) $region);
        return in_array($code, ['AU','CA','UK','US'], true) ? $code : 'GLOBAL';
    }

    /** Apply video type constraints depending on subscription */
    protected function applySubscriptionVideoType(Builder $q, ?int $userId): void
    {
        if ($userId && $this->hasValidSubscription($userId)) {
            $q->whereIn('type', ['youtube', 'vimeo']);
        } else {
            $q->where('type', 'youtube');
        }
    }

    /** Core query builder used by both "paid" and "free" endpoints */
    protected function buildVideosQuery(Request $request, string $regionCode, int $highlightTag, ?int $userId): Builder
    {
        $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published');

        // subscription-aware type filter
        $this->applySubscriptionVideoType($q, $userId);

        // optional filters
        foreach (['channel_id', 'character_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }

        // highlight tag (2 = top deals, 3 = trending)
        $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [$highlightTag]);

        // region
        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });

        return $q->latest();
    }
    protected function buildVideosQueryNew(Request $request, string $regionCode, int $highlightTag, ?int $userId, ?string $channelCategory = null): Builder
{
    $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
        ->where('status', 'published');

    // subscription-aware type filter
    $this->applySubscriptionVideoType($q, $userId);

    // optional filters
    foreach (['channel_id', 'character_id', 'category_id'] as $f) {
        if ($request->filled($f)) {
            $q->where($f, $request->get($f));
        }
    }

    // highlight tag (2 = top deals, 3 = trending)
    $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [$highlightTag]);

    // region
    $q->whereHas('regions', function ($query) use ($regionCode) {
        $query->where('region_code', $regionCode);
    });

    // filter by channel category if $channelCategory is provided
    if ($channelCategory) {
        $q->whereHas('channel', function ($query) use ($channelCategory) {
            $query->where('channel_category', $channelCategory);
        });
    }

    return $q->latest();
}


    protected function buildVideosQueryWithoutSubscription(Request $request, string $regionCode, int $highlightTag): Builder
{
    $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
        ->where('status', 'published');

    // Optional filters (channel, character, category)
    foreach (['channel_id', 'character_id', 'category_id'] as $f) {
        if ($request->filled($f)) {
            $q->where($f, $request->get($f));
        }
    }

    // Highlight tag (2 = top deals, 3 = trending)
    $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [$highlightTag]);

    // Region filter
    $q->whereHas('regions', function ($query) use ($regionCode) {
        $query->where('region_code', $regionCode);
    });

    return $q->latest();
}

    

    /** Map one video row to output shape */
    protected function mapVideo($video): array
    {
        if ($video->relationLoaded('regions')) {
            $video->regions->each->makeHidden(['pivot']);
        }

        return [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'type' => $video->type,
            'video_url' => $video->video_url ?? '',
            'thumbnail_url' => $video->thumbnail_url,
            'character_id' => $video->character_id,
            'channel_id' => $video->channel_id,
            'category_id' => $video->category_id,
            'access_level' => $video->access_level,
            'affiliate_link' => $video->affiliate_link,
            'tags' => $video->tag_pairs ?? [],
            'rating_type' => $video->rating_type ?? null,
            'sponsorship_type' => $video->sponsorship_type ?? null,
            'highlight_tags' => $video->highlight_tags,
            'auto_tags' => $video->auto_tags ?? null,
            'created_at' => $video->created_at->toDateTimeString(),
            'updated_at' => $video->updated_at->toDateTimeString(),
            'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
            'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
            'regions' => $video->regions->map(fn($r) => [
                'id' => $r->id,
                'region_code' => $r->region_code,
            ]),
        ];
    }

    /** Hydrate tag_pairs for a set of videos */
    protected function attachTagPairs(Collection $videos): void
    {
        $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])
            ->filter()
            ->unique();

        $tagMap = $allTagIds->isNotEmpty()
            ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
            : collect();

        $videos->each(function ($v) use ($tagMap) {
            $ids = collect($v->tag_ids_array ?? []);
            $v->tag_pairs = $ids->map(function ($id) use ($tagMap) {
                $name = $tagMap->get($id);
                return $name ? ['id' => $id, 'name' => $name] : null;
            })->filter()->values()->all();
        });
    }

    /**
     * Public helper to fetch section payloads for a region,
     * subscription-aware. Returns arrays ready to drop in the response.
     */
    protected function getSubscriptionSections(Request $request, string $region): array
    {
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        $userId = $user?->id;
        $regionCode = $this->resolveRegionCode($region);

        // Build both queries (2 = top deals, 3 = trending)
        $qTopDeals = $this->buildVideosQuery($request, $regionCode, 2, $userId);
        $qTrending = $this->buildVideosQuery($request, $regionCode, 3, $userId);

        $topDeals = $qTopDeals->get();
        $trending = $qTrending->get();

        // add tag_pairs for both sets
        $this->attachTagPairs($topDeals);
        $this->attachTagPairs($trending);

        return [
            'top_deals' => $topDeals->map(fn($v) => $this->mapVideo($v))->values(),
            'trending_products' => $trending->map(fn($v) => $this->mapVideo($v))->values(),
            'is_paid_user' => (bool) ($userId && $this->hasValidSubscription($userId)),
        ];
    }

    protected function getSubscriptionSectionsNew(Request $request, string $region, ?string $type = null): array
{
    $user = $request->user('api') ?? $request->user('sanctum') ?? null;
    $userId = $user?->id;
    $regionCode = $this->resolveRegionCode($region);

    // Pass $type to the buildVideosQuery method
    $qTopDeals = $this->buildVideosQueryNew($request, $regionCode, 2, $userId, $type);
    $qTrending = $this->buildVideosQueryNew($request, $regionCode, 3, $userId, $type);

    $topDeals = $qTopDeals->get();
    $trending = $qTrending->get();

    $this->attachTagPairs($topDeals);
    $this->attachTagPairs($trending);

    return [
        'top_deals' => $topDeals->map(fn($v) => $this->mapVideo($v))->values(),
        'trending_products' => $trending->map(fn($v) => $this->mapVideo($v))->values(),
        'is_paid_user' => (bool) ($userId && $this->hasValidSubscription($userId)),
    ];
}


    protected function buildChannelByRegionQuery(string $regionCode)
{
    return Channel::select('id', 'name', 'image', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
        ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
        ->with(['regions:id,region_code'])
        ->latest();
}
protected function buildChannelByRegionQueryNew(string $regionCode, ?string $channelCategory = null)
{
    $query = Channel::select(
            'id',
            'name',
            'image',
            'primary_color',
            'secondary_color',
            'accent_color',
            'background_color',
            'created_at',
            'updated_at'
        )
        ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
        ->with(['regions:id,region_code'])
        ->latest();

    // Filter by channel_category if provided
    if ($channelCategory) {
        $query->where('channel_category', $channelCategory);
    }

    return $query;
}

protected function mapChannel($channel): array
{
    // expose image_url and hide raw image
    $imageUrl = $channel->image ? asset($channel->image) : null;
    if ($channel->relationLoaded('regions')) {
        $channel->regions->each->makeHidden(['pivot']);
    }

    return [
        'id' => $channel->id,
        'name' => $channel->name,
        'image_url' => $imageUrl,
        'created_at' => optional($channel->created_at)?->toDateTimeString(),
        'updated_at' => optional($channel->updated_at)?->toDateTimeString(),
        'regions' => $channel->regions->map(fn($r) => [
            'id' => $r->id,
            'region_code' => $r->region_code,
        ]),
    ];
}
protected function getChannelsForRegion(Request $request, string $region): array
{
    $regionCode = $this->resolveRegionCode($region);
    $channels = $this->buildChannelByRegionQuery($regionCode)->get();

    return $channels->map(fn($c) => $this->mapChannel($c))->values()->all();
}
protected function getChannelsForRegionNew(Request $request, string $region, ?string $type = null): array
{
    $regionCode = $this->resolveRegionCode($region);
    $channels = $this->buildChannelByRegionQueryNew($regionCode, $type)->get();

    return $channels->map(fn($c) => $this->mapChannel($c))->values()->all();
}

}
