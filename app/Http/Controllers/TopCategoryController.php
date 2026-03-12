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
            ->get();

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
            'videos' => 'nullable|array',
            'videos.*' => 'exists:videos,id',
            'comment_types' => 'nullable',
            'timestamps' => 'nullable|array',
            'timestamps.*.start' => 'required_with:timestamps|string',
            'timestamps.*.end' => 'required_with:timestamps|string',
            'timestamps.*.message' => 'required_with:timestamps|string|max:255',
            'explain_video' => 'nullable|mimes:mp4,mov,avi,webm|max:20000',
            'status' => 'nullable|in:0,1',
        ]);

        // Decode comment types
        $commentTypes = [];
        if ($request->filled('comment_types')) {
            if (is_string($request->comment_types)) {
                $commentTypes = json_decode($request->comment_types, true) ?? [];
            } elseif (is_array($request->comment_types)) {
                $commentTypes = $request->comment_types;
            }
        }

        // Upload video
        if ($request->hasFile('explain_video')) {
            $validated['explain_video'] = $request->file('explain_video')
                ->store('explain_videos', 'public');
        }

        // Save timestamps
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
            ->get();
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
                'videos' => 'nullable|array',
                'videos.*' => 'exists:videos,id',
                'comment_types' => 'nullable',
                'timestamps' => 'nullable|array',
                'timestamps.*.start' => 'required_with:timestamps|string',
                'timestamps.*.end' => 'required_with:timestamps|string',
                'timestamps.*.message' => 'required_with:timestamps|string|max:255',
                'explain_video' => 'nullable|mimes:mp4,mov,avi,webm|max:20000',
                'status' => 'nullable|in:0,1',
            ]);

        $commentTypes = [];
        if ($request->filled('comment_types')) {
            if (is_string($request->comment_types)) {
                $commentTypes = json_decode($request->comment_types, true) ?? [];
            } elseif (is_array($request->comment_types)) {
                $commentTypes = $request->comment_types;
            }
        }

        if ($request->hasFile('explain_video')) {
            if ($topCategory->explain_video) {
                Storage::disk('public')->delete($topCategory->explain_video);
            }
            $validated['explain_video'] = $request->file('explain_video')->store('explain_videos', 'public');
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

        if ($topCategory->explain_video) {
            Storage::disk('public')->delete($topCategory->explain_video);
        }

        $topCategory->delete();

        return redirect()->route('admin.top-categories.index')
            ->with('success', 'Top category deleted successfully.');
    }
}