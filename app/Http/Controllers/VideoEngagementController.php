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
        $user->likedVideos()->syncWithoutDetaching([$video->id]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Video liked',
            'data' => ['video_id' => $video->id, 'liked' => true],
        ], 200);
    }

    // DELETE /videos/{video}/like
    public function unlike(Video $video)
    {
        $user = Auth::user();
        $user->likedVideos()->detach($video->id);

        return response()->json([], 204);
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

    // POST /videos/{video}/watch
    // body: { "last_position_seconds": 120, "watched_at": "2025-09-09T10:00:00Z" }
    public function recordWatch(Request $request, Video $video)
    {
        $validated = $request->validate([
            'last_position_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'watched_at' => ['nullable', 'date'],
        ]);

        $userId = Auth::id();

        $row = VideoWatchHistory::updateOrCreate(
            ['user_id' => $userId, 'video_id' => $video->id],
            [
                'last_position_seconds' => $validated['last_position_seconds'] ?? null,
                'watched_at' => isset($validated['watched_at'])
                    ? Carbon::parse($validated['watched_at'])
                    : now(),
            ]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Watch progress saved',
            'data' => [
                'video_id' => $video->id,
                'last_position_seconds' => $row->last_position_seconds,
                'watched_at' => optional($row->watched_at)->toIso8601String(),
            ],
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

    
}
