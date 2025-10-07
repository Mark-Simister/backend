<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Character;
use App\Models\Video;
use App\Models\Tag;
use App\Models\HighlightTag;
use App\Models\Subscription;
use Carbon\Carbon;

class SearchController extends Controller
{
    private function hasValidSubscription(int $userId): bool
    {
        $sub = Subscription::where('user_id', $userId)
            ->latest('subscription_end_date')
            ->first();


        if (!$sub || $sub->trashed()) {
            return false;
        }

        $now = now();

        $statusOkay = in_array($sub->subscription_status, ['active', 'trialing'], true);
        $notCanceled = $sub->subscription_status !== 'canceled' && is_null($sub->canceled_at);

        $withinPaidPeriod = $sub->subscription_end_date && $now->lte($sub->subscription_end_date);
        $withinTrial = $sub->trial_end_date && $now->lte($sub->trial_end_date);

        $timeOkay = $withinPaidPeriod || $withinTrial;

        $paymentOkay = ($sub->payment_status === 'succeeded') || ($sub->subscription_status === 'trialing');
        // dd($sub, $statusOkay, $notCanceled, $withinPaidPeriod, $withinTrial, $paymentOkay);

        return $statusOkay && $notCanceled && $timeOkay && $paymentOkay;
    }
    public function advancedSearch(Request $request, $region)
    {
        try {


            // $searchTerm = $request->input('search_term');
            $searchTerm = trim($request->input('search_term'));
            $regionCode = strtoupper($region);

            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }
            // $user = $request->user();
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $hasValidSubscription = $user ? $this->hasValidSubscription($user->id) : false;

            $results = [];

            $channels = Channel::with('regions')
                ->where(function ($query) use ($searchTerm) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(image) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(primary_color) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(secondary_color) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(accent_color) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(background_color) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->get();


            if ($channels->isNotEmpty()) {
                $results['channels'] = $channels->map(function ($channel) use ($regionCode) {
                    $channel->url = route('channels.details', ['channel' => $channel->id, 'region' => $regionCode]);
                    return $channel;
                });
            }

            // Search in Categories
            $categories = Category::with('regions')
                ->where(function ($query) use ($searchTerm) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(slug) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(image) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->get();

            if ($categories->isNotEmpty()) {
                $results['categories'] = $categories->map(function ($category) use ($regionCode) {
                    // Generate full URLs for image using asset() helper
                    $category->image = $category->image ? asset($category->image) : null;

                    $category->url = route('categories.byRegion', ['region' => $regionCode]);
                    return $category;
                });
            }

            // Search in Characters
            $characters = Character::with('regions')
                ->where(function ($query) use ($searchTerm) {
                    // Case-insensitive search using LOWER() for each relevant field
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(image) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(persona) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(details) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(location) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(age) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(species) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(style_vibe) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(durability_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(durability_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(comfort_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(comfort_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(style_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(style_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(affordability_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(affordability_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(tech_feature_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(tech_feature_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(eco_friendliness_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(eco_friendliness_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(engagement_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(engagement_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(ease_of_use_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(ease_of_use_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(performance_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(performance_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(brand_reputation_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(brand_reputation_notes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(sex) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(page_heading) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(page_sub_heading) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(preferences) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(loved_pet1) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(loved_pet2) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(loved_pet3) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(hated_pet1) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(hated_pet2) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(hated_pet3) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_page_url_slug) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(public_private_toggle) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_launch_date) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_popularity_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(editor_notes_content_guidelines) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_tag) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_role) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->get();


            if ($characters->isNotEmpty()) {
                $results['characters'] = $characters->map(function ($character) use ($regionCode) {
                    // Generate full URLs for image using asset() helper
                    $character->image = $character->image ? asset($character->image) : null;

                    $character->url = route('characters.withVideos', ['id' => $character->id, 'region' => $regionCode]);
                    return $character;
                });
            }

            // Search in Videos
            $videos = Video::with('regions')
                ->where(function ($query) use ($searchTerm) {
                    $query->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(type) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(video_url) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(thumbnail_url) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(access_level) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(tags) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(rating_type) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(sponsorship_type) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(highlight_tags) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(product_name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(product_asin_sku) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(public_rating) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(review_details) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(character_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(editorial_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(final_beastie_score) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(product_thumbnail) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(video_type) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(video_platforms) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(youtube_id) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(wistia_id) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(raw_video_path) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(caption_file) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(thumbnail_image) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(status) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(tag_ids) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(review_type) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(seo_title) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(seo_description) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(hashtags) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(cta_text) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(twitter_title) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(twitter_description) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(views) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(watch) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(likes) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                        ->orWhereRaw('LOWER(sale_end_date) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                });

            $tags = Tag::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])->get();
            if ($tags->isNotEmpty()) {
                $tagIds = $tags->pluck('id')->toArray();
                $videos->orWhere(function ($query) use ($tagIds) {
                    foreach ($tagIds as $tagId) {
                        $query->orWhereRaw('LOWER(tag_ids) LIKE ?', ['%' . strtolower($tagId) . '%']);
                    }
                });
            }

            // Fetch highlight tags by label
            $highlightTags = HighlightTag::whereRaw('LOWER(label) LIKE ?', ['%' . strtolower($searchTerm) . '%'])->get();

            if ($highlightTags->isNotEmpty()) {
                $highlightTagIds = $highlightTags->pluck('id')->toArray();

                $videos->orWhere(function ($query) use ($highlightTagIds) {
                    foreach ($highlightTagIds as $highlightTagId) {
                        $query->orWhereRaw('FIND_IN_SET(?, videos.highlight_tags)', [$highlightTagId]);
                    }
                });
            }


            if (!$hasValidSubscription) {
                $videos->where('type', 'youtube');
            }

            $videos = $videos->get();


            if ($videos->isNotEmpty()) {
                $results['videos'] = $videos->map(function ($video) use ($regionCode) {

                    // Generate full URLs for thumbnail_image and product_thumbnail using asset() helper
                    $video->thumbnail_image = $video->thumbnail_image ? asset($video->thumbnail_image) : null;
                    $video->product_thumbnail = $video->product_thumbnail ? asset($video->product_thumbnail) : null;

                    $video->url = route('paidVideosDetail', ['region' => $regionCode, 'id' => $video->id]);
                    return $video;
                });
            }
            if (empty($results)) {
                return response()->json([
                    'status' => true,
                    'message' => 'No results found',
                    'data' => [],
                ]);
            }

            // Return the response
            return response()->json([
                'status' => true,
                'message' => 'Search results fetched successfully',
                'data' => $results,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch search results',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}
