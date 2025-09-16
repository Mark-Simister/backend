<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\GlobalColor;

class GlobalColorController extends Controller
{
    public function index()
    {
        $colors = GlobalColor::latest()->get();
        return view('admin.global_colors.index', compact('colors'));
    }

    public function create()
    {
        return view('admin.global_colors.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:global_colors,name',
            'hex_value' => 'required|string|size:7',
            'usage' => 'nullable|string|max:255',
        ]);

        GlobalColor::create($validated);

        return redirect()->route('admin.global-colors.index')->with('success', 'Color created successfully.');
    }

    public function edit(GlobalColor $global_color)
    {
        return view('admin.global_colors.edit', compact('global_color'));
    }

    public function update(Request $request, GlobalColor $global_color)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:global_colors,name,' . $global_color->id,
            'hex_value' => 'required|string|size:7',
            'usage' => 'nullable|string|max:255',
        ]);

        $global_color->update($validated);

        return redirect()->route('admin.global-colors.index')->with('success', 'Color updated successfully.');
    }

    public function destroy(GlobalColor $global_color)
    {
        $global_color->delete();
        return redirect()->route('admin.global-colors.index')->with('success', 'Color deleted successfully.');
    }

    // API

    public function index_api()
    {
        $colors = GlobalColor::latest()->get();

        return response()->json([
            'status'  => true,
            'message' => 'Global colors fetched successfully',
            'data'    => $colors
        ]);
    }
}
