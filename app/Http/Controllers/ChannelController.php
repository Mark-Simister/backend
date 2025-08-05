<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use App\Models\Category;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::with('category')->latest()->get();
        return view('admin.channels.index', compact('channels'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('admin.channels.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);

        Channel::create($request->only('name', 'category_id'));

        return redirect()->route('admin.channels.index')->with('success', 'Channel created successfully.');
    }

    public function edit(Channel $channel)
    {
        $categories = Category::all();
        return view('admin.channels.edit', compact('channel', 'categories'));
    }

    public function update(Request $request, Channel $channel)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
        ]);

        $channel->update($request->only('name', 'category_id'));

        return redirect()->route('admin.channels.index')->with('success', 'Channel updated successfully.');
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();
        return redirect()->route('admin.channels.index')->with('success', 'Channel deleted.');
    }
}
