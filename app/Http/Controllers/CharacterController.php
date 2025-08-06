<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Channel;
use App\Models\CharacterRole;
use App\Models\CharacterTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CharacterController extends Controller
{
    public function index() {
    $characters = Character::with('channel')->latest()->get();
    return view('admin.characters.index', compact('characters'));
}

public function create() {
    $channels = Channel::all();
    $character_role = CharacterRole::all();
    $character_tag = CharacterTag::all();
    return view('admin.characters.create', compact('channels','character_role','character_tag'));
}

public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'persona' => 'nullable|string',
            'details' => 'nullable|string',
            'channel_id' => 'required|exists:channels,id',

            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

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
            
            // New fields
            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'page_heading' => 'nullable|string|max:255',
            'page_sub_heading' => 'nullable|string|max:255',
            'preferences' => 'nullable|string',
            'loved_pet1' => 'nullable|string|max:255',
            'loved_pet2' => 'nullable|string|max:255',
            'loved_pet3' => 'nullable|string|max:255',
            'hated_pet1' => 'nullable|string|max:255',
            'hated_pet2' => 'nullable|string|max:255',
            'hated_pet3' => 'nullable|string|max:255',
            'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')],
            'public_private_toggle' => 'required|boolean',
            'character_launch_date' => 'nullable|date',
            'character_popularity_score' => 'nullable|integer|min:0',
            'editor_notes_content_guidelines' => 'nullable|string',
          
            'character_tag' => 'nullable|array', 
            'character_tag.*' => 'string', 
            'character_role' => 'nullable|array', 
            'character_role.*' => 'string', 
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension(); 

            $destinationPath = public_path('/characters');

            // Create the directory if it doesn't exist
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true); 
            }

            $image->move($destinationPath, $imageName); 
            $validated['image'] = 'characters/' . $imageName; 
        }

  
        if (empty($validated['character_page_url_slug'])) {
            $baseSlug = Str::slug($validated['name']);
        } else {
            $baseSlug = Str::slug($validated['character_page_url_slug']); // Use the provided slug for the base
        }
        
        $uniqueSlug = $baseSlug;
        $i = 1;
        while (Character::where('character_page_url_slug', $uniqueSlug)->exists()) {
            $uniqueSlug = $baseSlug . '-' . $i++;
        }
        $validated['character_page_url_slug'] = $uniqueSlug;

        $validated['character_tag'] = $request->has('character_tag') ? implode(',', $validated['character_tag']) : null;
        
        $validated['character_role'] = $request->has('character_role') ? implode(',', $validated['character_role']) : null;


        Character::create($validated);

        return redirect()->route('admin.characters.index')->with('success', 'Character created successfully.');
    }

// public function edit(Character $character)
//     {
//         $channels = Channel::all(); 
//         $character_roles = CharacterRole::all();
//         $character_tags = CharacterTag::all();   
//         $currentTagNames = $character->character_tag ? explode(',', $character->character_tag) : [];
        
//         $currentRoleNames = $character->character_role ? explode(',', $character->character_role) : [];

//         return view('admin.characters.edit', compact(
//             'character',
//             'channels',
//             'character_roles',
//             'character_tags',
//             'currentTagNames', 
//             'currentRoleNames' 
//         ));
//     }
public function edit(Character $character)
{
    $channels = Channel::all(); 
    $character_role = CharacterRole::all();
    $character_tag = CharacterTag::all();

    // Explode comma-separated strings into arrays for multi-select
    $currentTagNames = $character->character_tag ? explode(',', $character->character_tag) : [];
    $currentRoleNames = $character->character_role ? explode(',', $character->character_role) : [];

    return view('admin.characters.edit', compact(
        'character',
        'channels',
        'character_role',
        'character_tag',
        'currentTagNames',
        'currentRoleNames'
    ));
}


public function update(Request $request, Character $character)
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

        'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
        'page_heading' => 'nullable|string|max:255',
        'page_sub_heading' => 'nullable|string|max:255',
        'preferences' => 'nullable|string',
        'loved_pet1' => 'nullable|string|max:255',
        'loved_pet2' => 'nullable|string|max:255',
        'loved_pet3' => 'nullable|string|max:255',
        'hated_pet1' => 'nullable|string|max:255',
        'hated_pet2' => 'nullable|string|max:255',
        'hated_pet3' => 'nullable|string|max:255',
        'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')->ignore($character->id)],
        'public_private_toggle' => 'required|boolean',
        'character_launch_date' => 'nullable|date',
        'character_popularity_score' => 'nullable|integer|min:0',
        'editor_notes_content_guidelines' => 'nullable|string',

        'character_tag' => 'nullable|array',
        'character_tag.*' => 'string',
        'character_role' => 'nullable|array',
        'character_role.*' => 'string',
    ]);

    // Handle image update
    if ($request->hasFile('image')) {
        if ($character->image && File::exists(public_path($character->image))) {
            File::delete(public_path($character->image));
        }

        $image = $request->file('image');
        $imageName = time() . '.' . $image->getClientOriginalExtension();
        $destinationPath = public_path('/characters');

        if (!File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true, true);
        }

        $image->move($destinationPath, $imageName);
        $validated['image'] = 'characters/' . $imageName;
    } else {
        $validated['image'] = $character->image;
    }

    // Generate character_page_url_slug
    if (empty($validated['character_page_url_slug'])) {
        $baseSlug = Str::slug($validated['name']);
    } else {
        $baseSlug = Str::slug($validated['character_page_url_slug']);
    }

    $uniqueSlug = $baseSlug;
    $i = 1;
    while (Character::where('character_page_url_slug', $uniqueSlug)
        ->where('id', '!=', $character->id)
        ->exists()) {
        $uniqueSlug = $baseSlug . '-' . $i++;
    }
    $validated['character_page_url_slug'] = $uniqueSlug;

    $validated['character_tag'] = $request->has('character_tag') ? implode(',', $validated['character_tag']) : null;
    $validated['character_role'] = $request->has('character_role') ? implode(',', $validated['character_role']) : null;

    $character->update($validated);

    return redirect()->route('admin.characters.index')->with('success', 'Character updated successfully.');
}


public function destroy(Character $character) {
    $character->delete();
    return back()->with('success', 'Character deleted.');
}
}
