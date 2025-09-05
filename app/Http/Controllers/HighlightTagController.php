<?php

namespace App\Http\Controllers;

use App\Models\HighlightTag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class HighlightTagController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:highlight_tag.view', only: ['index']),
            new Middleware('permission:highlight_tag.create', only: ['create', 'store']),
            new Middleware('permission:highlight_tag.edit', only: ['edit', 'update']),
            new Middleware('permission:highlight_tag.delete', only: ['destroy']),

        ];
    }
    public function index()
    {
        $tags = HighlightTag::all();
        return view('admin.highlight_tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.highlight_tags.create');
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'label' => 'required|string',
    //         'emoji' => 'required|string',
    //         'automated' => 'boolean', 
    //     ]);

    //     HighlightTag::create($request->all());
    //     return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag created successfully.');
    // }


    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            // store an image for the emoji, same rules as category image
            'emoji' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5048',
            'automated' => 'sometimes|boolean',
        ]);

        $emojiPath = null;

        if ($request->hasFile('emoji')) {
            $folderPath = public_path('emojie'); // note: 'emojie' folder
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }

            $imageName = time() . '_' . uniqid() . '.' . $request->emoji->extension();
            $request->emoji->move($folderPath, $imageName);
            $emojiPath = 'emojie/' . $imageName; // path stored in DB
        }

        HighlightTag::create([
            'label' => $request->label,
            'emoji' => $emojiPath,
            'automated' => $request->boolean('automated'),
        ]);

        return redirect()
            ->route('admin.highlight_tags.index')
            ->with('success', 'Tag created successfully.');
    }


    public function edit(HighlightTag $highlightTag)
    {
        return view('admin.highlight_tags.edit', compact('highlightTag'));
    }

    // public function update(Request $request, HighlightTag $highlightTag)
    // {
    //     $request->validate([
    //         'label' => 'required|string',
    //         'emoji' => 'required|string',
    //         'automated' => 'boolean',
    //     ]);

    //     $highlightTag->update($request->all());
    //     return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag updated successfully.');
    // }
    public function update(Request $request, HighlightTag $highlightTag)
    {
        // dd($request->all());
        $request->validate([
            'label' => 'required|string|max:255',
            'emoji' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5048',
            'automated' => 'sometimes|boolean',
        ]);

        $emojiPath = $highlightTag->emoji; 

        if ($request->hasFile('emoji')) {
            $folderPath = public_path('emojie');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }

            $imageName = time() . '_' . uniqid() . '.' . $request->emoji->extension();
            $request->emoji->move($folderPath, $imageName);
            $emojiPath = 'emojie/' . $imageName;
        }

        $highlightTag->update([
            'label' => $request->label,
            'emoji' => $emojiPath,
            'automated' => $request->boolean('automated'),
        ]);

        return redirect()
            ->route('admin.highlight_tags.index')
            ->with('success', 'Tag updated successfully.');
    }


    public function destroy(HighlightTag $highlightTag)
    {
        $highlightTag->delete();
        return redirect()->route('admin.highlight_tags.index')->with('success', 'Tag deleted.');
    }
}
