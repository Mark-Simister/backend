<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;
use App\Models\Category;
use App\Models\Character;
use App\Models\Video;
use Carbon\Carbon;

class SearchController extends Controller
{
    public function advancedSearch(Request $request, $region)
{
    try {
        // Get search term from the query string
        $searchTerm = $request->input('search_term');
        
        // Get region from the URL parameter
        $regionCode = strtoupper($region);

        // Define allowed regions
        $allowedRegions = ['AU', 'CA', 'UK', 'US'];
        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL'; // Default to 'GLOBAL' if region is not valid
        }

        // Initialize query results
        $results = [];

        // Search in Channels
        $channels = Channel::with('regions')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%$searchTerm%")
                    ->orWhere('primary_color', 'LIKE', "%$searchTerm%")
                    ->orWhere('secondary_color', 'LIKE', "%$searchTerm%")
                    ->orWhere('accent_color', 'LIKE', "%$searchTerm%")
                    ->orWhere('background_color', 'LIKE', "%$searchTerm%");
            })
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->get();

        if ($channels->isNotEmpty()) {
            $results['channels'] = $channels->map(function ($channel) use ($regionCode) {
                // Construct URL with region in route
                $channel->url = route('channels.details', ['channel' => $channel->id, 'region' => $regionCode]);
                return $channel;
            });
        }

        // Search in Categories
        $categories = Category::with('regions')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%$searchTerm%")
                    ->orWhere('slug', 'LIKE', "%$searchTerm%");
            })
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->get();

        if ($categories->isNotEmpty()) {
            $results['categories'] = $categories->map(function ($category) use ($regionCode) {
                $category->url = route('category.details', ['id' => $category->id, 'region' => $regionCode]);
                return $category;
            });
        }

        // Search in Characters
        $characters = Character::with('regions')
            ->where(function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%$searchTerm%")
                    ->orWhere('persona', 'LIKE', "%$searchTerm%")
                    ->orWhere('details', 'LIKE', "%$searchTerm%")
                    ->orWhere('location', 'LIKE', "%$searchTerm%")
                    ->orWhere('species', 'LIKE', "%$searchTerm%")
                    ->orWhere('style_vibe', 'LIKE', "%$searchTerm%")
                    ->orWhere('durability_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('comfort_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('affordability_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('tech_feature_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('eco_friendliness_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('engagement_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('ease_of_use_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('performance_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('brand_reputation_score', 'LIKE', "%$searchTerm%")
                    ->orWhere('sex', 'LIKE', "%$searchTerm%")
                    ->orWhere('page_heading', 'LIKE', "%$searchTerm%")
                    ->orWhere('preferences', 'LIKE', "%$searchTerm%")
                    ->orWhere('loved_pet1', 'LIKE', "%$searchTerm%")
                    ->orWhere('hated_pet1', 'LIKE', "%$searchTerm%");
            })
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->get();

        if ($characters->isNotEmpty()) {
            $results['characters'] = $characters->map(function ($character) use ($regionCode) {
                $character->url = route('characters.withVideos', ['id' => $character->id, 'region' => $regionCode]);
                return $character;
            });
        }

        // Search in Videos
        $videos = Video::with('regions')
            ->where(function ($query) use ($searchTerm) {
                $query->where('title', 'LIKE', "%$searchTerm%")
                    ->orWhere('description', 'LIKE', "%$searchTerm%")
                    ->orWhere('product_name', 'LIKE', "%$searchTerm%")
                    ->orWhere('product_asin_sku', 'LIKE', "%$searchTerm%")
                    ->orWhere('video_url', 'LIKE', "%$searchTerm%")
                    ->orWhere('affiliate_link', 'LIKE', "%$searchTerm%")
                    ->orWhere('rating_type', 'LIKE', "%$searchTerm%")
                    ->orWhere('sponsorship_type', 'LIKE', "%$searchTerm%");
            })
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->get();

        if ($videos->isNotEmpty()) {
            $results['videos'] = $videos->map(function ($video) use ($regionCode) {
                $video->url = route('videos.paidVideosDetail', ['region' => $regionCode, 'id' => $video->id]);
                return $video;
            });
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
