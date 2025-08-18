<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;

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


    // Api's

    public function index_api()
{
    try {
        $channels = Channel::with('category')->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Channels fetched successfully',
            'data' => $channels
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch channels',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function store_api(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255|unique:channels,name',
        'category_id' => 'required|exists:categories,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

        $channel = Channel::create($request->only('name', 'category_id'));

        return response()->json([
            'status' => true,
            'message' => 'Channel created successfully',
            'data' => $channel->load('category')
        ], 201);
    
}

public function show_api($id)
{
    $channel = Channel::with('category')->find($id);

    if (!$channel) {
        return response()->json([
            'status' => false,
            'message' => 'Channel not found',
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'Channel details fetched successfully',
        'data' => $channel
    ]);
}

public function update_api(Request $request, $id)
{
    $channel = Channel::find($id);

    if (!$channel) {
        return response()->json([
            'status' => false,
            'message' => 'Channel not found',
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255|unique:channels,name,' . $id,
        'category_id' => 'required|exists:categories,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $channel->update($request->only('name', 'category_id'));

        return response()->json([
            'status' => true,
            'message' => 'Channel updated successfully',
            'data' => $channel->load('category')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to update channel',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function destroy_api($id)
{
    $channel = Channel::find($id);

    if (!$channel) {
        return response()->json([
            'status' => false,
            'message' => 'Channel not found',
        ], 404);
    }

    try {
        $channel->delete();

        return response()->json([
            'status' => true,
            'message' => 'Channel deleted successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete channel',
            'error' => $e->getMessage()
        ], 500);
    }
}
}