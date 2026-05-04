<?php

namespace App\Http\Controllers;

use App\Models\TopCategory;
use App\Models\Video;
use Illuminate\Http\Request;
use App\Models\LeaderboardComment;
use Illuminate\Support\Facades\Storage;

class TopCategoryController extends Controller
{
    public function index()
    {
        $topCategories = TopCategory::latest()->paginate(10);
        return view('admin.top_categories.index', compact('topCategories'));
    }

    public function create()
    {
        $videos = Video::select('videos.*', 'channels.channel_category as video_cat')
            ->leftJoin('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereNotNull('videos.video_url')
            ->get()
            ->map(function ($video) {
                $video->thumbnail_url = $video->thumbnail_image 
                    ? asset($video->thumbnail_image) 
                    : null;
                return $video;
            });

        $comments = LeaderboardComment::with('user')
            ->select('id', 'comment', 'type', 'user_id')
            ->get();

        return view('admin.top_categories.create', compact('videos', 'comments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_type' => 'required|in:pet,people',
            'title' => 'required|string|max:255',
            'overview_title' => 'nullable|string|max:255',        
            'overview_description' => 'nullable|string',
            'thumbnail' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048', 
            'videos' => 'required|array|min:1|max:5',
            'videos.*' => 'exists:videos,id',
            'comment_types' => 'nullable',
            'timestamps' => 'nullable|array',
            'timestamps.*.start' => 'required_with:timestamps|string',
            'timestamps.*.end' => 'required_with:timestamps|string',
            'timestamps.*.message' => 'required_with:timestamps|string|max:255',
            'explain_video' => 'required|file|mimes:mp4,mov,avi,webm|max:51200',
            'status' => 'nullable|in:0,1',
        ]);

        // Upload Thumbnail
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $filename = time() . '_thumb_' . $file->getClientOriginalName();
            $file->move(public_path('top_category_thumbnails'), $filename);

            $validated['thumbnail'] = 'top_category_thumbnails/' . $filename;
        }

        // Decode comment types
        $commentTypes = [];
        if ($request->filled('comment_types')) {
            $commentTypes = is_string($request->comment_types)
                ? json_decode($request->comment_types, true) ?? []
                : $request->comment_types;
        }

        // Upload video
        if ($request->hasFile('explain_video')) {
            $file = $request->file('explain_video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('explain_videos'), $filename);

            $validated['explain_video'] = 'explain_videos/' . $filename;
        }

        $validated['video_timestamps'] = $request->timestamps ?? [];
        $validated['video_ids'] = $request->videos ?? [];
        $validated['comment_types'] = $commentTypes;
        $validated['status'] = $request->status ?? 1;

        TopCategory::create($validated);

        return redirect()->route('admin.top-categories.index')
            ->with('success', 'Top category created successfully.');
    }

    public function edit($id)
    {
        $topCategory = TopCategory::findOrFail($id);

        $videos = Video::select('videos.*', 'channels.channel_category as video_cat')
            ->leftJoin('channels', 'channels.id', '=', 'videos.channel_id')
            ->whereNotNull('videos.video_url')
            ->get()
            ->map(function ($video) {
                $video->thumbnail_url = $video->thumbnail_image 
                    ? asset($video->thumbnail_image) 
                    : null;
                return $video;
            });

        $comments = LeaderboardComment::with('user')
            ->select('id', 'comment', 'type', 'user_id')
            ->get();

        return view('admin.top_categories.edit', compact('topCategory', 'videos', 'comments'));
    }

    public function update(Request $request, $id)
    {
        $topCategory = TopCategory::findOrFail($id);

        $validated = $request->validate([
            'category_type' => 'required|in:pet,people',
            'title' => 'required|string|max:255',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 
            'overview_title' => 'nullable|string|max:255',          
            'overview_description' => 'nullable|string',
            'videos' => 'nullable|array',
            'videos.*' => 'exists:videos,id',
            'comment_types' => 'nullable',
            'timestamps' => 'nullable|array',
            'timestamps.*.start' => 'required_with:timestamps|string',
            'timestamps.*.end' => 'required_with:timestamps|string',
            'timestamps.*.message' => 'required_with:timestamps|string|max:255',
            'explain_video' => $topCategory->explain_video
                ? 'nullable|file|mimes:mp4,mov,avi,webm|max:51200'
                : 'required|file|mimes:mp4,mov,avi,webm|max:51200',
            'status' => 'nullable|in:0,1',
        ]);

        // Upload Thumbnail (Update)
        if ($request->hasFile('thumbnail')) {

            if ($topCategory->thumbnail && file_exists(public_path($topCategory->thumbnail))) {
                unlink(public_path($topCategory->thumbnail));
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_thumb_' . $file->getClientOriginalName();
            $file->move(public_path('top_category_thumbnails'), $filename);

            $validated['thumbnail'] = 'top_category_thumbnails/' . $filename;
        }

        // Decode comment types
        $commentTypes = [];
        if ($request->filled('comment_types')) {
            $commentTypes = is_string($request->comment_types)
                ? json_decode($request->comment_types, true) ?? []
                : $request->comment_types;
        }

        // Upload video
        if ($request->hasFile('explain_video')) {
            if ($topCategory->explain_video && file_exists(public_path($topCategory->explain_video))) {
                unlink(public_path($topCategory->explain_video));
            }

            $file = $request->file('explain_video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('explain_videos'), $filename);

            $validated['explain_video'] = 'explain_videos/' . $filename;
        }

        $validated['video_ids'] = $request->videos ?? [];
        $validated['comment_types'] = $commentTypes;
        $validated['status'] = $request->status ?? 1;
        $validated['video_timestamps'] = $request->timestamps ?? [];

        $topCategory->update($validated);

        return redirect()->route('admin.top-categories.index')
            ->with('success', 'Top category updated successfully.');
    }

    public function destroy($id)
    {
        $topCategory = TopCategory::findOrFail($id);

        if ($topCategory->thumbnail && file_exists(public_path($topCategory->thumbnail))) {
            unlink(public_path($topCategory->thumbnail));
        }

        if ($topCategory->explain_video && file_exists(public_path($topCategory->explain_video))) {
            unlink(public_path($topCategory->explain_video));
        }

        $topCategory->delete();

        return redirect()->route('admin.top-categories.index')
            ->with('success', 'Top category deleted successfully.');
    }
}