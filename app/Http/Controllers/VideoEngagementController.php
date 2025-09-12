<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoWatchHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    // DELETE /videos/{video}/unlike
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
                'likes_count' => $video->likes, // latest likes count
            ],
        ], 200);
    }

    // likes/video/count
    public function likesCount(Video $video)
    {
        $count = $video->likes()->count(); // using hasMany VideoLike relation

        return response()->json([
            'status' => 'ok',
            'video_id' => $video->id,
            'likes_count' => $count,
        ]);
    }

    // POST /videos/{video}/favorite
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

    // DELETE /videos/{video}/favorite
    public function unfavorite(Video $video)
    {
        $user = Auth::user();
        $user->favoriteVideos()->detach($video->id);

        return response()->json([], 204);
    }

    public function recordWatch(Request $request, Video $video)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        $alreadyWatched = VideoWatchHistory::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->exists();

        if (!$alreadyWatched) {
            VideoWatchHistory::create([
                'user_id' => $user->id,
                'video_id' => $video->id,
            ]);

            // Increment video views
            $video->increment('views');
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Watch recorded',
            'data' => [
                'video_id' => $video->id,
                'views' => $video->views,
            ]
        ], 200);
    }



    // GET /me/last-watched?per_page=20
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
    // dd($data, $user);
        ->where('user_id', $user->id)
        ->get();
    if ($data->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No watch history found for this user',
        ], 404);
    }

    return response()->json([
        'status' => 'ok',
        'user_id' => $user->id,
        'watch_history' => $data,
        
    ]);
}
}
