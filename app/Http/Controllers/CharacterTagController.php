<?php

namespace App\Http\Controllers;


use App\Models\CharacterTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CharacterTagController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:character_tag.view',   only: ['index']),
            new Middleware('permission:character_tag.create', only: ['create','store']),
            new Middleware('permission:character_tag.edit',   only: ['edit','update']),
            new Middleware('permission:character_tag.delete', only: ['destroy']),

        ];
    }

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

    // Character API's

    public function index_api()
    {
        $characterTags = CharacterTag::latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Character tags fetched successfully',
            'data' => $characterTags
        ]);
    }

    public function store_api(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:character_tags,name',
        ]);

        $characterTag = CharacterTag::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Character tag created successfully',
            'data' => $characterTag
        ], 201);
    }

    public function show_api(CharacterTag $characterTag)
    {
        return response()->json([
            'status' => true,
            'message' => 'Character tag details fetched successfully',
            'data' => $characterTag
        ]);
    }

    public function update_api(Request $request, $id)
{
    $characterTag = CharacterTag::find($id);

    if (!$characterTag) {
        return response()->json([
            'status' => false,
            'message' => 'Character tag not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|unique:character_tags,name,' . $characterTag->id,
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    $characterTag->update([
        'name' => $request->name,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Character tag updated successfully',
        'data' => $characterTag
    ]);
}

public function destroy_api($id)
{
    $characterTag = CharacterTag::find($id);

    if (!$characterTag) {
        return response()->json([
            'status' => false,
            'message' => 'Character tag not found'
        ], 404);
    }

    $characterTag->delete();

    return response()->json([
        'status' => true,
        'message' => 'Character tag deleted successfully'
    ]);
}
}
