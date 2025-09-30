<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\CategoryFollow;
use App\Models\VideoWatchHistory;
use App\Models\ProductReview;
use App\Models\ProductReviewWatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class VideoEngagementController extends Controller
{
    // POST /videos/{video}/like
    public function like(Video $video)
    {
        $user = Auth::user();


        if (!$user->likedVideos()->where('video_id', $video->id)->exists()) {
            $user->likedVideos()->attach($video->id);


            $video->increment('likes');
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Video liked',
            'data' => [
                'video_id' => $video->id,
                'liked' => true,
                'likes_count' => $video->likes,
            ],
        ], 200);
    }

    public function unlike(Video $video)
    {
        $user = Auth::user();

        if ($user->likedVideos()->where('video_id', $video->id)->exists()) {
            $user->likedVideos()->detach($video->id);

            // Decrement likes column but never go below 0
            if ($video->likes > 0) {
                $video->decrement('likes');
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Video unliked',
            'data' => [
                'video_id' => $video->id,
                'liked' => false,
                'likes_count' => $video->likes,
            ],
        ], 200);
    }

    public function likesCount(Video $video)
    {
        $count = $video->likes()->count();

        return response()->json([
            'status' => 'ok',
            'video_id' => $video->id,
            'likes_count' => $count,
        ]);
    }

    public function favorite(Video $video)
    {
        $user = Auth::user();
        $user->favoriteVideos()->syncWithoutDetaching([$video->id]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Added to favourites',
            'data' => ['video_id' => $video->id, 'favourited' => true],
        ], 200);
    }

    public function unfavorite(Video $video)
    {
        $user = Auth::user();
        $user->favoriteVideos()->detach($video->id);

        return response()->json([], 204);
    }

    // public function recordWatch(Request $request, $videoId)
    // {

    //     $video = Video::find($videoId);

    //     if (!$video) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Video not found',
    //         ], 404);
    //     }

    //     $user = Auth::user();

    //     if (!$user) {
    //         return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
    //     }

    //     $alreadyWatched = VideoWatchHistory::where('user_id', $user->id)
    //         ->where('video_id', $video->id)
    //         ->exists();

    //     if (!$alreadyWatched) {
    //         VideoWatchHistory::create([
    //             'user_id' => $user->id,
    //             'video_id' => $video->id,
    //         ]);

    //         $video->increment('views');
    //     }

    //     return response()->json([
    //         'status' => 'ok',
    //         'message' => 'Watch recorded',
    //         'data' => [
    //             'video_id' => $video->id,
    //             'views' => $video->views,
    //         ]
    //     ], 200);
    // }
    public function recordWatch(Request $request, $videoId)
    {
        $video = Video::find($videoId);
        if (!$video) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video not found',
            ], 404);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }
        // Increment the view counter EVERY time this endpoint is hit.
        $video->increment('views');
        //    firstOrCreate will insert on first watch and do nothing on repeats.
        VideoWatchHistory::firstOrCreate([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);
        $video->refresh();
        return response()->json([
            'status' => 'ok',
            'message' => 'Watch recorded',
            'data' => [
                'video_id' => $video->id,
                'views' => $video->views,
            ],
        ], 200);
    }


    public function updateWatchHistory(Request $request, $videoId)
    {
        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video not found',
            ], 404);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        // Check if the user has already watched the video
        $watchHistory = VideoWatchHistory::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if ($watchHistory) {
            // update the watch time, position, and completion status
            $watchHistory->update([
                'last_position_seconds' => $request->last_position_seconds,
                'is_completed' => $request->is_completed,
                'watched_at' => now(),
            ]);

            // If video is completed, reset last_position_seconds to 0
            if ($request->is_completed) {
                $watchHistory->update([
                    'last_position_seconds' => 0,
                ]);
            }

            $updatedWatchHistory = VideoWatchHistory::where('user_id', $user->id)
                ->where('video_id', $video->id)
                ->first();

            return response()->json([
                'status' => 'ok',
                'message' => 'Watch history updated',
                'data' => [
                    'video_id' => $updatedWatchHistory->video_id,
                    'last_position_seconds' => $updatedWatchHistory->last_position_seconds,
                    'is_completed' => $updatedWatchHistory->is_completed,
                    'watched_at' => $updatedWatchHistory->watched_at,
                ]
            ], 200);
        }

        // If the user has not watched this video before, create a new record
        $newWatchHistory = VideoWatchHistory::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'last_position_seconds' => $request->last_position_seconds,
            'is_completed' => $request->is_completed,
            'watched_at' => now(),
        ]);

        $newWatchHistory = VideoWatchHistory::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        return response()->json([
            'status' => 'ok',
            'message' => 'Watch history created',
            'data' => [
                'video_id' => $newWatchHistory->video_id,
                'last_position_seconds' => $newWatchHistory->last_position_seconds,
                'is_completed' => $newWatchHistory->is_completed,
                'watched_at' => $newWatchHistory->watched_at,
            ]
        ], 200);
    }



    public function myLastWatched(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $items = VideoWatchHistory::with(['video:id,title,thumbnail_image,thumbnail_url'])
            ->where('user_id', Auth::id())
            ->orderByDesc('watched_at')
            ->paginate($perPage);

        return response()->json($items);
    }

    // GET /me/likes?per_page=20
    public function myLikes(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $videos = Auth::user()
            ->likedVideos()
            ->select('videos.id', 'videos.title', 'videos.thumbnail_image', 'videos.thumbnail_url')
            ->withPivot('created_at')
            ->orderByDesc('video_likes.created_at')
            ->paginate($perPage);

        return response()->json($videos);
    }

    // GET /me/favourites?per_page=20
    public function myFavourites(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $videos = Auth::user()
            ->favoriteVideos()
            ->select('videos.id', 'videos.title', 'videos.thumbnail_image', 'videos.thumbnail_url')
            ->withPivot('created_at')
            ->orderByDesc('video_favorites.created_at')
            ->paginate($perPage);

        return response()->json($videos);
    }

    public function myWatchHistories()
    {
        $user = Auth::user();

        $data = VideoWatchHistory::with('video')
            ->where('user_id', $user->id)
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No watch history found for this user',
            ], 404);
        }

        // Modify the response to include full URLs using asset() helper
        $data->each(function ($history) {
            $history->video->product_thumbnail = $history->video->product_thumbnail ? asset($history->video->product_thumbnail) : null;
            $history->video->thumbnail_image = $history->video->thumbnail_image ? asset($history->video->thumbnail_image) : null;
        });

        return response()->json([
            'status' => 'ok',
            'user_id' => $user->id,
            'watch_history' => $data,
        ]);
    }

    public function followCategory($categoryId)
    {
        $user = Auth::user();

        $alreadyFollowed = CategoryFollow::where('user_id', $user->id)
            ->where('category_id', $categoryId)
            ->exists();

        if ($alreadyFollowed) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already following this category.',
            ], 400);
        }

        CategoryFollow::create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Category followed successfully.',
        ]);
    }

    public function listFollowedCategories()
    {
        $user = Auth::user();

        $followedCategories = CategoryFollow::with('category')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($follow) {
                return [
                    'category_id' => $follow->category->id,
                    'category_name' => $follow->category->name,
                    'image' => asset($follow->category->image),
                ];
            });

        if ($followedCategories->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have not followed any categories yet.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $followedCategories,
        ]);
    }



    public function trackView(Request $request, $productReviewId)
    {
        $user = $request->user('api') ?? $request->user('sanctum');
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $productReview = ProductReview::findOrFail($productReviewId);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Product review not found'
            ], 404);
        }

        // Check if user already watched
        $alreadyWatched = ProductReviewWatch::where('product_review_id', $productReviewId)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyWatched) {
            return response()->json([
                'status' => true,
                'message' => 'User has already viewed this product review',
                'views' => $productReview->views,
            ]);
        }

        $productReview->increment('views');

        ProductReviewWatch::create([
            'product_review_id' => $productReviewId,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product review view tracked successfully',
            'views' => $productReview->views,
        ]);
    }



}
