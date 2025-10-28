<?php

namespace App\Http\Controllers;

use App\Models\Blooper;
use App\Models\Character;
use Illuminate\Http\Request;

class BlooperController extends Controller
{
    public function index(Character $character)
    {
        $bloopers = $character->bloopers;
        return view('admin.bloopers.index', compact('character', 'bloopers'));
    }

    public function create(Character $character)
    {
        return view('admin.bloopers.create', compact('character'));
    }

    public function store(Request $request, Character $character)
    {
        $request->validate([
            'video' => 'required|mimes:mp4,mov,avi|max:51200', // max 50MB
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // max 10MB
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stars' => 'nullable|integer|min:0|max:5',
        ]);

        // Handle video upload
        $videoPath = null;
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_' . $video->getClientOriginalName();
            $video->move(public_path('bloopers'), $videoName);
            $videoPath = 'bloopers/' . $videoName;
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('bloopers'), $imageName);
            $imagePath = 'bloopers/' . $imageName;
        }

        $character->bloopers()->create([
            'video' => $videoPath,
            'image' => $imagePath,
            'name' => $request->name,
            'description' => $request->description,
            'stars' => $request->stars ?? 0,
        ]);

        return redirect()->route('admin.bloopers.index', $character)->with('success', 'Blooper added successfully!');
    }
    public function edit(Character $character, Blooper $blooper)
{
    return view('admin.bloopers.edit', compact('character','blooper'));
}

    public function update(Request $request, Character $character, Blooper $blooper)
{
    $request->validate([
        'video' => 'nullable|mimes:mp4,mov,avi|max:51200',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        'name'  => 'required|string|max:255',
        'description' => 'nullable|string',
        'stars' => 'nullable|integer|min:0|max:5',
    ]);

    // Replace video if uploaded
    if ($request->hasFile('video')) {
        if ($blooper->video && file_exists(public_path($blooper->video))) {
            @unlink(public_path($blooper->video));
        }
        $video = $request->file('video');
        $videoName = time().'_'.$video->getClientOriginalName();
        $video->move(public_path('bloopers'), $videoName);
        $blooper->video = 'bloopers/'.$videoName;
    }

    // Replace image if uploaded
    if ($request->hasFile('image')) {
        if ($blooper->image && file_exists(public_path($blooper->image))) {
            @unlink(public_path($blooper->image));
        }
        $image = $request->file('image');
        $imageName = time().'_'.$image->getClientOriginalName();
        $image->move(public_path('bloopers'), $imageName);
        $blooper->image = 'bloopers/'.$imageName;
    }

    $blooper->name = $request->name;
    $blooper->description = $request->description;
    $blooper->stars = $request->stars ?? 0;
    $blooper->save();

    return redirect()->route('admin.bloopers.index', $character)
        ->with('success', 'Blooper updated successfully!');
}


    public function destroy(Blooper $blooper)
    {
        if (file_exists(public_path($blooper->video))) {
            unlink(public_path($blooper->video));
        }

        $blooper->delete();

        return back()->with('success', 'Blooper deleted successfully!');
    }
}
