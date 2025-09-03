<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Region;
use App\Models\ChannelRegion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

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
    // $regions = Region::all();
    $regions = Region::where('is_active', 1)->get();
    return view('admin.channels.create', compact('regions'));
}

// public function store(Request $request)
// {
//     $request->validate([
//         'name' => 'required|unique:channels,name',
//         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
//         'regions' => 'array|nullable',
//     ]);

//     $imagePath = null;
//     if ($request->hasFile('image')) {
//         $folderPath = public_path('channel');
//         if (!file_exists($folderPath)) {
//             mkdir($folderPath, 0777, true);
//         }
//         $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
//         $request->image->move($folderPath, $imageName);
//         $imagePath = 'channel/' . $imageName;
//     }

//     $channel = Channel::create([
//         'name' => $request->name,
//         'slug' => Str::slug($request->name),
//         'image' => $imagePath,
//     ]);

//     // Attach regions
//     if ($request->has('regions')) {
//         $channel->regions()->attach($request->regions);
//     }

//     return redirect()->route('admin.channels.index')->with('success', 'Channel created successfully!');
// }

public function store(Request $request)
{
    $request->validate([
        'name'        => 'required|unique:channels,name',
        'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        'regions'     => 'array|nullable',
        'regions.*'   => 'integer|exists:regions,id', // validate each region id
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $folderPath = public_path('channel');
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }
        $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
        $request->image->move($folderPath, $imageName);
        $imagePath = 'channel/' . $imageName;
    }

    DB::transaction(function () use ($request, $imagePath) {
        $channel = Channel::create([
            'name'  => $request->name,
            'slug'  => Str::slug($request->name),
            'image' => $imagePath,
        ]);

        // Insert pivot rows via ChannelRegion model
        $regionIds = collect($request->input('regions', []))
            ->filter()             // remove nulls
            ->unique()             // avoid duplicates
            ->values();

        if ($regionIds->isNotEmpty()) {
            $rows = $regionIds->map(fn ($rid) => [
                'channel_id' => $channel->id,
                'region_id'  => $rid,
            ])->all();

            ChannelRegion::insert($rows);
 
        }
    });

    return redirect()
        ->route('admin.channels.index')
        ->with('success', 'Channel created successfully!');
}

public function edit(Channel $channel)
{
    // Get active regions OR regions already assigned to this channel
    $regions = Region::where('is_active', 1)
        ->orWhereIn('id', $channel->regions->pluck('id'))
        ->get();

    $selectedRegions = $channel->regions->pluck('id')->toArray();

    return view('admin.channels.edit', compact('channel', 'regions', 'selectedRegions'));
}

// public function update(Request $request, Channel $channel)
// {
//     $request->validate([
//         'name' => 'required|unique:channels,name,' . $channel->id,
//         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
//         'regions' => 'nullable|array',
//         'regions.*' => 'exists:regions,id',
//     ]);

//     $imagePath = $channel->image;

//     if ($request->hasFile('image')) {
//         $folderPath = public_path('channel');
//         if (!file_exists($folderPath)) {
//             mkdir($folderPath, 0777, true);
//         }

//         // delete old image if exists
//         if ($channel->image && file_exists(public_path($channel->image))) {
//             unlink(public_path($channel->image));
//         }

//         $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
//         $request->image->move($folderPath, $imageName);
//         $imagePath = 'channel/' . $imageName;
//     }

//     $channel->update([
//         'name' => $request->name,
//         'slug' => Str::slug($request->name),
//         'image' => $imagePath,
//     ]);

//     // Sync selected regions
//     $channel->regions()->sync($request->regions ?? []);

//     return redirect()->route('admin.channels.index')->with('success', 'Channel updated successfully!');
// }

public function update(Request $request, Channel $channel)
{
    $request->validate([
        'name'      => 'required|unique:channels,name,' . $channel->id,
        'image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        'regions'   => 'nullable|array',
        'regions.*' => 'exists:regions,id',
    ]);

    $imagePath = $channel->image;

    if ($request->hasFile('image')) {
        $folderPath = public_path('channel');
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        // delete old image if exists
        if ($channel->image && file_exists(public_path($channel->image))) {
            unlink(public_path($channel->image));
        }

        $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
        $request->image->move($folderPath, $imageName);
        $imagePath = 'channel/' . $imageName;
    }

    DB::transaction(function () use ($request, $channel, $imagePath) {
        // Update channel itself
        $channel->update([
            'name'  => $request->name,
            'slug'  => Str::slug($request->name),
            'image' => $imagePath,
        ]);

        // Remove old region links
        ChannelRegion::where('channel_id', $channel->id)->delete();

        // Insert new region links
        $regionIds = collect($request->input('regions', []))
            ->filter()
            ->unique()
            ->values();

        if ($regionIds->isNotEmpty()) {
            $rows = $regionIds->map(fn ($rid) => [
                'channel_id' => $channel->id,
                'region_id'  => $rid,
            ])->all();

            ChannelRegion::insert($rows);
        }
    });

    return redirect()
        ->route('admin.channels.index')
        ->with('success', 'Channel updated successfully!');
}


    public function destroy(Channel $channel)
{
    // delete image if exists
    if ($channel->image && file_exists(public_path($channel->image))) {
        unlink(public_path($channel->image));
    }

    $channel->delete();

    return redirect()->route('admin.channels.index')->with('success', 'Channel deleted successfully!');
}


    // Api's

//     public function index_api()
// {
//     try {
//         $channels = Channel::with('categories')->latest()->get();

//         // prepend image URL
//         $channels->map(function ($channel) {
//             if ($channel->image) {
//                 $channel->image_url = asset('channel/' . $channel->image);
//             } else {
//                 $channel->image_url = null;
//             }
//             return $channel;
//         });

//         return response()->json([
//             'status' => true,
//             'message' => 'Channels fetched successfully',
//             'data' => $channels
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Failed to fetch channels',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }
public function index_api()
{
    try {
        // Eager-load only what you need
        $channels = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
            ->with([
                'regions:id,region_code' // adjust columns if needed
            ])
            ->latest()
            ->get();

        // enrich + hide fields
        $channels->each(function ($channel) {
            // add image_url (but don't expose 'image')
            $channel->image_url = $channel->image ? asset($channel->image) : null;

            // hide top-level 'image'
            $channel->makeHidden(['image']);

            // hide 'pivot' on related regions
            if ($channel->relationLoaded('regions')) {
                $channel->regions->each->makeHidden(['pivot']);
            }
        });

        return response()->json([
            'status'  => true,
            'message' => 'Channels fetched successfully',
            'data'    => $channels,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Failed to fetch channels',
            'error'   => $e->getMessage()
        ], 500);
    }
}




public function store_api(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255|unique:channels,name',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $request->only('name');

    // handle image upload
    if ($request->hasFile('image')) {
        $filename = time() . '.' . $request->image->extension();
        $request->image->move(public_path('channel'), $filename);
        $data['image'] = $filename;
    }

    $channel = Channel::create($data);

    if ($channel->image) {
        $channel->image_url = asset('channel/' . $channel->image);
    }

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

    if ($channel->image) {
        $channel->image_url = asset('channel/' . $channel->image);
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
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $data = $request->only('name');

        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($channel->image && file_exists(public_path('channel/' . $channel->image))) {
                unlink(public_path('channel/' . $channel->image));
            }
            $filename = time() . '.' . $request->image->extension();
            $request->image->move(public_path('channel'), $filename);
            $data['image'] = $filename;
        }

        $channel->update($data);

        if ($channel->image) {
            $channel->image_url = asset('channel/' . $channel->image);
        }

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
        // delete image if exists
        if ($channel->image && file_exists(public_path('channel/' . $channel->image))) {
            unlink(public_path('channel/' . $channel->image));
        }

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