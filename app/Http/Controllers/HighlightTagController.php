<?php

namespace App\Http\Controllers;

use App\Models\HighlightTag;
use Illuminate\Http\Request;

class HighlightTagController extends Controller
{
    public function index()
    {
        $tags = HighlightTag::all();
        return view('admin.highlight_tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.highlight_tags.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string',
            'emoji' => 'required|string',
            'automated' => 'boolean',
        ]);

        HighlightTag::create($request->all());
        return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag created successfully.');
    }

    public function edit(HighlightTag $highlightTag)
    {
        return view('admin.highlight_tags.edit', compact('highlightTag'));
    }

    public function update(Request $request, HighlightTag $highlightTag)
    {
        $request->validate([
            'label' => 'required|string',
            'emoji' => 'required|string',
            'automated' => 'boolean',
        ]);

        $highlightTag->update($request->all());
        return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag updated successfully.');
    }

    public function destroy(HighlightTag $highlightTag)
    {
        $highlightTag->delete();
        return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag deleted.');
    }
}
