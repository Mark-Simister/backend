<?php

namespace App\Http\Controllers;


use App\Models\CharacterTag;
use Illuminate\Http\Request;

class CharacterTagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     public function index()
    {
        $characterTags = CharacterTag::latest()->get(); 
        return view('admin.character_tags.index', compact('characterTags')); 
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.character_tags.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:character_tags,name',
        ]);

        CharacterTag::create([
            'name' => $request->name,
            // Slugs are not explicitly needed for this simple tag CRUD,
            // but can be added if needed for other purposes
            // 'slug' => Str::slug($request->name), 
        ]);

        return redirect()->route('admin.character_tags.index')->with('success', 'Character Tag created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(CharacterTag $characterTag)
    {
        return view('admin.character_tags.show', compact('characterTag'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CharacterTag $characterTag)
    {
        return view('admin.character_tags.edit', compact('characterTag'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CharacterTag $characterTag)
    {
        $request->validate([
            'name' => 'required|unique:character_tags,name,' . $characterTag->id,
        ]);

        $characterTag->update([
            'name' => $request->name,
            // Slugs are not explicitly needed for this simple tag CRUD
            // 'slug' => Str::slug($request->name), 
        ]);

        return redirect()->route('admin.character_tags.index')->with('success', 'Character Tag updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CharacterTag $characterTag)
    {
        $characterTag->delete();
        return redirect()->route('admin.character_tags.index')->with('success', 'Character Tag deleted.');
    }
}
