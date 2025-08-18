<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CharacterRole;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CharacterRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $characterRoles = CharacterRole::latest()->get();
        return view('admin.character_roles.index', compact('characterRoles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.character_roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:character_roles,name',
        ]);

        CharacterRole::create([
            'name' => $request->name,
            // 'slug' => Str::slug($request->name), // Add slug if needed
        ]);

        return redirect()->route('admin.character_roles.index')->with('success', 'Character Role created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(CharacterRole $characterRole)
    {
        return view('admin.character_roles.show', compact('characterRole'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CharacterRole $characterRole)
    {
        return view('admin.character_roles.edit', compact('characterRole'));
    }

    public function update(Request $request, CharacterRole $characterRole)
    {
        $request->validate([
            'name' => 'required|unique:character_roles,name,' . $characterRole->id,
        ]);

        $characterRole->update([
            'name' => $request->name,
            // 'slug' => Str::slug($request->name), // Add slug if needed
        ]);

        return redirect()->route('admin.character_roles.index')->with('success', 'Character Role updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CharacterRole $characterRole)
    {
        $characterRole->delete();
        return redirect()->route('admin.character_roles.index')->with('success', 'Character Role deleted.');
    }

    // Character Role Api's

    public function index_api()
    {
        $characterRoles = CharacterRole::latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Character roles fetched successfully',
            'data' => $characterRoles
        ]);
    }

    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:character_roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $characterRole = CharacterRole::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Character role created successfully',
            'data' => $characterRole
        ], 201);
    }

    public function show_api($id)
    {
        $characterRole = CharacterRole::find($id);

        if (!$characterRole) {
            return response()->json([
                'status' => false,
                'message' => 'Character role not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Character role details fetched successfully',
            'data' => $characterRole
        ]);
    }

    public function update_api(Request $request, $id)
    {
        $characterRole = CharacterRole::find($id);

        if (!$characterRole) {
            return response()->json([
                'status' => false,
                'message' => 'Character role not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:character_roles,name,' . $characterRole->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $characterRole->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Character role updated successfully',
            'data' => $characterRole
        ]);
    }

    public function destroy_api($id)
    {
        $characterRole = CharacterRole::find($id);

        if (!$characterRole) {
            return response()->json([
                'status' => false,
                'message' => 'Character role not found'
            ], 404);
        }

        $characterRole->delete();

        return response()->json([
            'status' => true,
            'message' => 'Character role deleted successfully'
        ]);
    }
}
