<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    // Show all themes
    public function index()
    {
        $themes = Theme::latest()->get();
        return view('admin.themes.index', compact('themes'));
    }

    // Show create form
    public function create()
    {
        return view('admin.themes.create');
    }

    // Store new theme
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:themes,name',
            'button_color' => 'nullable|string|max:20',
            'link_color' => 'nullable|string|max:20',
            'dark_bg_color' => 'nullable|string|max:20',
            'light_bg_color' => 'nullable|string|max:20',
        ]);

        Theme::create($request->all());

        return redirect()->route('admin.themes.index')->with('success', 'Theme created successfully.');
    }

    // Edit form
    public function edit(Theme $theme)
    {
        return view('admin.themes.edit', compact('theme'));
    }

    // Update theme
    public function update(Request $request, Theme $theme)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:themes,name,' . $theme->id,
            'button_color' => 'nullable|string|max:20',
            'link_color' => 'nullable|string|max:20',
            'dark_bg_color' => 'nullable|string|max:20',
            'light_bg_color' => 'nullable|string|max:20',
        ]);

        $theme->update($request->all());

        return redirect()->route('admin.themes.index')->with('success', 'Theme updated successfully.');
    }

    // Delete theme
    public function destroy(Theme $theme)
    {
        $theme->delete();

        return redirect()->route('admin.themes.index')->with('success', 'Theme deleted successfully.');
    }

    // === API ===
    public function index_api()
    {
        $themes = Theme::latest()->get();

        return response()->json([
            'status'  => true,
            'message' => 'Themes fetched successfully',
            'data'    => $themes
        ]);
    }
}
