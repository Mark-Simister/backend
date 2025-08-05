<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Character;
use App\Models\Channel;
use App\Models\Category;
use Illuminate\Http\Request;

class VideoController extends Controller
{
     public function index()
    {
        $videos = Video::latest()->get();
        return view('admin.videos.index', compact('videos'));
    }

    public function create()
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        return view('admin.videos.create', compact('channels', 'characters', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:youtube,vimeo',
            'video_url' => 'required|string',
            'character_id' => 'required|exists:characters,id',
            'channel_id' => 'required|exists:channels,id',
            'category_id' => 'required|exists:categories,id',
            'access_level' => 'required|in:public,premium,early_access',
        ]);

        Video::create($request->all());

        return redirect()->route('admin.videos.index')->with('success', 'Video created successfully.');
    }

    public function edit(Video $video)
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        return view('admin.videos.edit', compact('video', 'channels', 'characters', 'categories'));
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:youtube,vimeo',
            'video_url' => 'required|string',
            'character_id' => 'required|exists:characters,id',
            'channel_id' => 'required|exists:channels,id',
            'category_id' => 'required|exists:categories,id',
            'access_level' => 'required|in:public,premium,early_access',
        ]);

        $video->update($request->all());

        return redirect()->route('admin.videos.index')->with('success', 'Video updated successfully.');
    }

    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted.');
    }
}
