<?php

namespace App\Http\Controllers;

use App\Models\CharacterInsight;
use App\Models\Video;
use Illuminate\Http\Request;

class CharacterInsightController extends Controller
{
    public function index($videoId)
    {
        $insights = CharacterInsight::where('video_id', $videoId)->get();
        $video = Video::findOrFail($videoId); // fetch video by ID
        $insights = CharacterInsight::where('video_id', $videoId)->get();
        return view('admin.character_insights.index', compact('insights', 'videoId','video'));
    }

    public function create($videoId)
    {
        return view('admin.character_insights.create', compact('videoId'));
    }

    public function store(Request $request, $videoId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'character_insight_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $data = $request->only(['title', 'short_description']);
        $data['video_id'] = $videoId;

        if ($request->hasFile('character_insight_image')) {
            $destinationPath = public_path('character_insights');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $request->file('character_insight_image')->getClientOriginalExtension();
            $request->file('character_insight_image')->move($destinationPath, $filename);
            $data['character_insight_image'] = 'character_insights/' . $filename;
        }

        CharacterInsight::create($data);

        return redirect()->route('admin.videos.character-insights.index', $videoId)
            ->with('success', 'Character Insight created successfully.');
    }

    public function edit($videoId, CharacterInsight $characterInsight)
    {
        return view('admin.character_insights.edit', compact('characterInsight', 'videoId'));
    }

    public function update(Request $request, $videoId, CharacterInsight $characterInsight)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'character_insight_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $data = $request->only(['title', 'short_description']);

        if ($request->hasFile('character_insight_image')) {
            $destinationPath = public_path('character_insights');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $request->file('character_insight_image')->getClientOriginalExtension();
            $request->file('character_insight_image')->move($destinationPath, $filename);
            $data['character_insight_image'] = 'character_insights/' . $filename;
        }

        $characterInsight->update($data);

        return redirect()->route('admin.videos.character-insights.index', $videoId)
            ->with('success', 'Character Insight updated successfully.');
    }

    public function destroy($videoId, CharacterInsight $characterInsight)
    {
        if ($characterInsight->character_insight_image && file_exists(public_path($characterInsight->character_insight_image))) {
            unlink(public_path($characterInsight->character_insight_image));
        }

        $characterInsight->delete();

        return redirect()->route('admin.videos.character-insights.index', $videoId)
            ->with('success', 'Character Insight deleted successfully.');
    }
}
