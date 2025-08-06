<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CharacterRole;
use Illuminate\Support\Str;

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
}
