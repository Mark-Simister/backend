<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopCategory;
use App\Models\Video;
use App\Models\LeaderboardComment;

class TopCategoryApiController extends Controller
{
    public function index()
    {
        $topCategories = TopCategory::where('status', 1)
            ->latest()
            ->take(5)
            ->get();

        $data = $topCategories->map(function ($category) {

            // === Videos ===
            $videos = [];
            if (is_array($category->video_ids) && count($category->video_ids) > 0) {
                $videos = Video::whereIn('id', $category->video_ids)
                    ->select('id', 'title', 'video_url')
                    ->get();
            }

            // === Comments ===
            $comments = [];
            if (is_array($category->comment_types) && count($category->comment_types) > 0) {
                $comments = LeaderboardComment::with('user:id,name')
                    ->whereIn('type', $category->comment_types)
                    ->select('id', 'user_id', 'comment', 'type')
                    ->orderByDesc('id')
                    ->take(10)
                    ->get()
                    ->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'user_id' => $c->user_id,
                            'user_name' => $c->user->name ?? 'Anonymous',
                            'comment' => $c->comment,
                            'type' => $c->type,
                        ];
                    });
            }

            return [
                'id' => $category->id,
                'category_type' => $category->category_type,
                'title' => $category->title,

                'explain_video' => $category->explain_video
                    ? asset('storage/' . $category->explain_video)
                    : null,

                // NEW
                'video_timestamps' => $category->video_timestamps ?? [],

                'videos' => $videos,
                'comments' => $comments,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Top categories fetched successfully',
            'data' => $data,
        ]);
    }
}