<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ChannelController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:channel.view',   only: ['index']),
            new Middleware('permission:channel.create', only: ['create','store']),
            new Middleware('permission:channel.edit',   only: ['edit','update']),
            new Middleware('permission:channel.delete', only: ['destroy']),

        ];
    }
    public function index()
{
    $channels = Channel::with('categories')->latest()->get();
    return view('admin.channels.index', compact('channels'));
}

public function create()
{
    return view('admin.channels.create');
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255|unique:channels,name',
    ]);

    Channel::create($request->only('name'));

    return redirect()->route('admin.channels.index')->with('success', 'Channel created successfully.');
}

public function edit(Channel $channel)
{
    return view('admin.channels.edit', compact('channel'));
}

public function update(Request $request, Channel $channel)
{
    $request->validate([
        'name' => 'required|string|max:255|unique:channels,name,' . $channel->id,
    ]);

    $channel->update($request->only('name'));

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
        $channels = Channel::with('categories')->latest()->get();

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
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    $channel = Channel::create($request->only('name'));

    return response()->json([
        'status' => true,
        'message' => 'Channel created successfully',
        'data' => $channel->load('categories')
    ], 201);
}

public function show_api($id)
{
    $channel = Channel::with('categories')->find($id);

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
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $channel->update($request->only('name'));

        return response()->json([
            'status' => true,
            'message' => 'Channel updated successfully',
            'data' => $channel->load('categories')
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