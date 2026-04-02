<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use App\Models\VideoView;

class VideoViewController extends Controller
{
    // Store video view
    public function store(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'type'     => 'required|in:pet,people,global',
        ]);

        $userId  = auth()->id();
        $videoId = $request->video_id;
        $type    = $request->type;
        $alreadyViewed = VideoView::where('video_id', $videoId)
            ->where('type', $type)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->exists();

        if ($alreadyViewed) {
            return response()->json([
                'status'  => true,
                'message' => 'View already recorded',
            ]);
        }

        VideoView::create([
            'user_id'  => $userId,
            'video_id' => $videoId,
            'type'     => $type,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'View recorded successfully',
        ]);
    }

    //  Get Top Viewers (for tab)
    public function topViewers($type)
    {
        $data = VideoView::select('user_id')
            ->selectRaw('COUNT(*) as total_views')
            ->with('user:id,name')
            ->where('type', $type)
            ->groupBy('user_id')
            ->orderByDesc('total_views')
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'user_name' => $item->user->name ?? 'Anonymous',
                    'total_views' => $item->total_views,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
     
    public function mostViewedVideos(Request $request)
    {
        $type = $request->input('type', 'global');

        $validTypes = ['pet', 'people', 'global'];

        if (!in_array($type, $validTypes)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type parameter.',
            ], 400);
        }

        $videos = DB::table('video_views')
            ->join('videos', 'video_views.video_id', '=', 'videos.id')
            ->leftJoin('users', 'video_views.user_id', '=', 'users.id')
            ->select(
                'videos.id',
                'videos.title',
                DB::raw("CONCAT('" . url('/') . "/', videos.thumbnail_image) as thumbnail"),
                DB::raw('COUNT(video_views.id) as views'),
                DB::raw('MAX(users.name) as user_name')
            )
            ->where('video_views.type', $type)
            ->groupBy('videos.id', 'videos.title', 'videos.thumbnail_image')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Most viewed videos fetched successfully',
            'data' => $videos
        ]);
    }

}