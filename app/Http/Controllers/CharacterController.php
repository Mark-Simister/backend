<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File; 

class CharacterController extends Controller
{
    public function index() {
    $characters = Character::with('channel')->latest()->get();
    return view('admin.characters.index', compact('characters'));
}

public function create() {
    $channels = Channel::all();
    return view('admin.characters.create', compact('channels'));
}

public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'persona' => 'nullable|string',
            'details' => 'nullable|string',
            'channel_id' => 'required|exists:channels,id',

            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:100',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:100',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:100',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:100',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:100',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:100',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:100',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:100',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:100',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:100',
            'brand_reputation_notes' => 'nullable|string',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension(); // Generate a unique filename

            $destinationPath = public_path('/characters'); // Define the destination path within the public folder

            // Create the directory if it doesn't exist
            if (!File::isDirectory($destinationPath)) { // Check if the directory exists
                File::makeDirectory($destinationPath, 0755, true, true); // Create the directory recursively with specified permissions
            }

            $image->move($destinationPath, $imageName); // Move the uploaded file to the public/characters folder
            $validated['image'] = 'characters/' . $imageName; // Save the path relative to the public folder
        }

        Character::create($validated);

        return redirect()->route('admin.characters.index')->with('success', 'Character created successfully.');
    }

public function edit(Character $character) {
    $channels = Channel::all();
    return view('admin.characters.edit', compact('character', 'channels'));
}

public function update(Request $request, Character $character)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'persona' => 'nullable|string',
            'details' => 'nullable|string',
            'channel_id' => 'required|exists:channels,id',

            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validation for the image

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:100',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:100',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:100',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:100',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:100',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:100',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:100',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:100',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:100',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:100',
            'brand_reputation_notes' => 'nullable|string',
        ]);

        // Handle image update
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($character->image && File::exists(public_path($character->image))) { //
                File::delete(public_path($character->image)); // Delete the old image file.
            }

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/characters');

            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $image->move($destinationPath, $imageName);
            $validated['image'] = 'characters/' . $imageName; // Store the new image path.
        } else {
            // If no new image is uploaded, retain the existing image path
            $validated['image'] = $character->image; //
        }

        $character->update($validated);

        return redirect()->route('admin.characters.index')->with('success', 'Character updated successfully.');
    }

public function destroy(Character $character) {
    $character->delete();
    return back()->with('success', 'Character deleted.');
}
}
