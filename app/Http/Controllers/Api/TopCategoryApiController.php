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
            ->get([
                'id',
                'category_type',
                'title',
                'overview_title',
                'overview_description',
                'thumbnail',
                'explain_video',
                'video_timestamps',
                'video_ids',
                'comment_types'
            ]);

        $data = $topCategories->map(function ($category) {

            // === Videos ===
                $videos = Video::whereIn('id', $category->video_ids)
                    ->select('id', 'title', 'video_url', 'thumbnail_image')
                    ->get()
                    ->map(function ($video) {
                        return [
                            'id' => $video->id,
                            'title' => $video->title,
                            'video_url' => $video->video_url,
                            'thumbnail' => $video->thumbnail_image 
                                ? asset($video->thumbnail_image)
                                : null,
                        ];
                    });

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
            
            $relatedCategories = TopCategory::where('status', 1)
            ->where('category_type', $category->category_type)
            ->where('id', '!=', $category->id)
            ->latest()
            ->take(10)
            ->get(['id', 'title', 'thumbnail']);

            return [
                'id' => $category->id,
                'category_type' => $category->category_type,
                'title' => $category->title,
                'overview_title' => $category->overview_title,
                'overview_description' => $category->overview_description,
                 'thumbnail' => $category->thumbnail
                    ? asset($category->thumbnail)
                    : null,

                'explain_video' => $category->explain_video
                    ? asset($category->explain_video)
                    : null,
                'video_timestamps' => $category->video_timestamps ?? [],

                'videos' => $videos,
                'comments' => $comments,

                'related_categories' => $relatedCategories->map(function ($rc) {
                    return [
                        'id' => $rc->id,
                        'title' => $rc->title,
                        'thumbnail' => $rc->thumbnail 
                            ? asset($rc->thumbnail) 
                            : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Top categories fetched successfully',
            'data' => $data,
        ]);
    }
}