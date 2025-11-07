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
use App\Models\VideoLike;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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




    public function listLikedVideos()
    {
        $user = Auth::user();
        $likes = VideoLike::with('video')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        // Map to a clean payload (skip rows where the related video is missing)
        $likedVideos = $likes->filter(fn($like) => $like->video)->map(function ($like) {
            $v = $like->video;

            $thumbnail = $v->thumbnail_url
                ?: ($v->thumbnail_image ? asset($v->thumbnail_image) : null);

            $finalBeastieScore = null;
            if (!is_null($v->final_beastie_score)) {
                $finalBeastieScore = round($v->final_beastie_score / 2, 1);
            }

            return [
                'video_id'        => $v->id,
                'title'           => $v->title,
                'description'     => $v->description,
                'type'            => $v->type,
                'video_type'      => $v->video_type,
                'video_platforms' => $v->video_platforms,
                'video_url'       => $v->video_url,
                'youtube_id'      => $v->youtube_id,
                'wistia_id'       => $v->wistia_id,
                'thumbnail'       => $thumbnail,
                'likes'           => $v->likes,
                'liked_at'        => optional($like->created_at)->toIso8601String(),
                'final_beastie_score' => $finalBeastieScore,
            ];
        })->values();

        if ($likedVideos->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have not saved any videos yet.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => $likedVideos,
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
        // if (!$user) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Unauthenticated',
        //     ], 401);
        // }
        // Increment the view counter EVERY time this endpoint is hit.
        $video->increment('views');
        if ($user) {
            //    firstOrCreate will insert on first watch and do nothing on repeats.
            VideoWatchHistory::firstOrCreate([
                'user_id' => $user->id,
                'video_id' => $video->id,
            ]);
        }

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


    // public function updateWatchHistory(Request $request, $videoId)
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

    //     // Check if the user has already watched the video
    //     $watchHistory = VideoWatchHistory::where('user_id', $user->id)
    //         ->where('video_id', $video->id)
    //         ->first();

    //     if ($watchHistory) {
    //         // update the watch time, position, and completion status
    //         $watchHistory->update([
    //             'last_position_seconds' => $request->last_position_seconds,
    //             'is_completed' => $request->is_completed,
    //             'watched_at' => now(),
    //         ]);

    //         // If video is completed, reset last_position_seconds to 0
    //         if ($request->is_completed) {
    //             $watchHistory->update([
    //                 'last_position_seconds' => 0,
    //             ]);
    //         }

    //         $updatedWatchHistory = VideoWatchHistory::where('user_id', $user->id)
    //             ->where('video_id', $video->id)
    //             ->first();

    //         return response()->json([
    //             'status' => 'ok',
    //             'message' => 'Watch history updated',
    //             'data' => [
    //                 'video_id' => $updatedWatchHistory->video_id,
    //                 'last_position_seconds' => $updatedWatchHistory->last_position_seconds,
    //                 'is_completed' => $updatedWatchHistory->is_completed,
    //                 'watched_at' => $updatedWatchHistory->watched_at,
    //             ]
    //         ], 200);
    //     }

    //     // If the user has not watched this video before, create a new record
    //     $newWatchHistory = VideoWatchHistory::create([
    //         'user_id' => $user->id,
    //         'video_id' => $video->id,
    //         'last_position_seconds' => $request->last_position_seconds,
    //         'is_completed' => $request->is_completed,
    //         'watched_at' => now(),
    //     ]);

    //     $newWatchHistory = VideoWatchHistory::where('user_id', $user->id)
    //         ->where('video_id', $video->id)
    //         ->first();

    //     return response()->json([
    //         'status' => 'ok',
    //         'message' => 'Watch history created',
    //         'data' => [
    //             'video_id' => $newWatchHistory->video_id,
    //             'last_position_seconds' => $newWatchHistory->last_position_seconds,
    //             'is_completed' => $newWatchHistory->is_completed,
    //             'watched_at' => $newWatchHistory->watched_at,
    //         ]
    //     ], 200);
    // }

    public function updateWatchHistory(Request $request, $videoId)
    {
        $video = Video::find($videoId);
        if (!$video) {
            return response()->json(['status' => 'error', 'message' => 'Video not found'], 404);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        // Accept either client-style or DB-style field names
        $data = $request->validate([
            'position_seconds'        => ['nullable', 'numeric', 'min:0'],
            'last_position_seconds'   => ['nullable', 'numeric', 'min:0'],
            'watched_seconds'         => ['nullable', 'numeric', 'min:0'],
            'total_watched_seconds'   => ['nullable', 'numeric', 'min:0'],
            'is_completed'            => ['sometimes', 'boolean'],
            'reason'                  => ['sometimes', 'in:start,progress,pause,ended,close'],
        ]);

        $now         = now();
        $isCompleted = (bool)($data['is_completed'] ?? false);

        // Coalesce names -> internal variables
        $position     = (int)($data['position_seconds'] ?? $data['last_position_seconds'] ?? 0);
        $sessionDelta = (int)($data['watched_seconds'] ?? $data['total_watched_seconds'] ?? 0);
        $reason       = $data['reason'] ?? 'progress';

        $history = DB::transaction(function () use ($user, $video, $now, $isCompleted, $position, $sessionDelta, $reason) {
            $history = VideoWatchHistory::where('user_id', $user->id)
                ->where('video_id', $video->id)
                ->lockForUpdate()
                ->first();

            if (!$history) {
                $history = VideoWatchHistory::create([
                    'user_id'               => $user->id,
                    'video_id'              => $video->id,
                    'last_position_seconds' => 0,
                    'total_watched_seconds' => 0,
                    'is_completed'          => false,
                    'watched_at'            => $now,
                    'completed_at'          => null,
                ]);

                if ($reason === 'start') {
                    $video->increment('views');
                }
            }

            // Accumulate forward time
            $history->total_watched_seconds = max(0, (int)$history->total_watched_seconds) + max(0, $sessionDelta);

            // Update position (zero out on completion)
            $history->last_position_seconds = $isCompleted ? 0 : max(0, $position);

            // Completion fields
            $history->is_completed = $isCompleted;
            $history->completed_at = $isCompleted ? ($history->completed_at ?? $now) : null;

            // Touch last watched
            $history->watched_at = $now;

            $history->save();

            return $history;
        });

        return response()->json([
            'status'  => 'ok',
            'message' => 'Watch history upserted',
            'data'    => [
                'video_id'               => $history->video_id,
                'last_position_seconds'  => (int)$history->last_position_seconds,
                'total_watched_seconds'  => (int)$history->total_watched_seconds,
                'is_completed'           => (bool)$history->is_completed,
                'completed_at'           => $history->completed_at,
                'watched_at'             => $history->watched_at,
                'resume_at'              => (int)$history->last_position_seconds,
            ],
        ], 200);
    }

    public function getWatchHistory($videoId)
    {
        $video = \App\Models\Video::find($videoId);
        if (!$video) {
            return response()->json(['status' => 'error', 'message' => 'Video not found'], 404);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $history = \App\Models\VideoWatchHistory::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if (!$history) {
            return response()->json([
                'status' => 'ok',
                'message' => 'No watch history found',
                'data' => [
                    'video_id' => (int)$video->id,
                    'last_position_seconds' => 0,
                    'total_watched_seconds' => 0,
                    'is_completed' => false,
                    'completed_at' => null,
                    'watched_at' => null,
                    'resume_at' => 0,
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Watch history fetched successfully',
            'data' => [
                'video_id' => (int)$history->video_id,
                'last_position_seconds' => (int)$history->last_position_seconds,
                'total_watched_seconds' => (int)$history->total_watched_seconds,
                'is_completed' => (bool)$history->is_completed,
                'completed_at' => $history->completed_at,
                'watched_at' => $history->watched_at,
                'resume_at' => (int)$history->last_position_seconds,
            ],
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
        // dd($user);

        $data = VideoWatchHistory::with('video')
            ->where('user_id', $user->id)
            ->where('is_completed', 1)
            ->get();

        // Modify the response to include full URLs using asset() helper
        // $data->each(function ($history) {
        //     $history->video->product_thumbnail = $history->video->product_thumbnail ? asset($history->video->product_thumbnail) : null;
        //     $history->video->thumbnail_image = $history->video->thumbnail_image ? asset($history->video->thumbnail_image) : null;
        // });
        $data->each(function ($history) {
            $video = $history->video;
            if (!$video) {
                return;
            }

            //  Convert final_beastie_score from 1–10 → 1–5
            $video->final_beastie_score = !is_null($video->final_beastie_score)
                ? round($video->final_beastie_score / 2, 1)
                : null;

            //  Make image URLs absolute
            $video->product_thumbnail = $video->product_thumbnail
                ? asset($video->product_thumbnail)
                : null;

            $video->thumbnail_image = $video->thumbnail_image
                ? asset($video->thumbnail_image)
                : null;
        });


        return response()->json([
            'status' => 'ok',
            'user_id' => $user->id,
            'watch_history' => $data,
        ]);
    }
    public function myWatchHistoriesContinueWatching()
    {
        $user = Auth::user();

        $data = VideoWatchHistory::with('video')
            ->where('user_id', $user->id)
            ->where('is_completed', 0)
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

        $followedCategories = CategoryFollow::with('category.channel') // 👈 include the related channel
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($follow) {
                $category = $follow->category;
                $channel = $category?->channel;

                return [
                    'category_id'   => $category->id,
                    'category_name' => $category->name,
                    'image'         => asset($category->image),

                    // 👇 include related channel data (if exists)
                    'channel' => $channel ? [
                        'id'                => $channel->id,
                        'name'              => $channel->name,
                        'image_url'         => $channel->image_url,
                        'primary_color'     => $channel->primary_color,
                        'secondary_color'   => $channel->secondary_color,
                        'accent_color'      => $channel->accent_color,
                        'background_color'  => $channel->background_color,
                        'text_color'        => $channel->text_color,
                        'hover_color'       => $channel->hover_color,
                        'highlight_color'   => $channel->highlight_color,
                        'cta'               => $channel->cta,
                        'channel_category'  => $channel->channel_category,
                        'created_at'        => optional($channel->created_at)?->toDateTimeString(),
                        'updated_at'        => optional($channel->updated_at)?->toDateTimeString(),
                    ] : null,
                ];
            });

        return response()->json([
            'status' => 'ok',
            'data'   => $followedCategories,
        ]);
    }


    public function unfollowCategory($categoryId)
    {
        $user = Auth::user();

        CategoryFollow::where('user_id', $user->id)
            ->where('category_id', $categoryId)
            ->delete();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Category unfollowed successfully.',
            'data'    => [
                'category_id' => (int) $categoryId,
                'followed'    => false,
            ],
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
