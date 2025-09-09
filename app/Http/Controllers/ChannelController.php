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
use Illuminate\Support\Arr;
use App\Models\Character;
use App\Models\Video;
use App\Models\Tag;
use App\Models\HighlightTag;

class ChannelController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:channel.view', only: ['index']),
            new Middleware('permission:channel.create', only: ['create', 'store']),
            new Middleware('permission:channel.edit', only: ['edit', 'update']),
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
//         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
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
            'name' => 'required|unique:channels,name',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
            'regions' => 'array|nullable',
            'regions.*' => 'integer|exists:regions,id', // validate each region id
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
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'image' => $imagePath,
            ]);

            // Insert pivot rows via ChannelRegion model
            $regionIds = collect($request->input('regions', []))
                ->filter()             // remove nulls
                ->unique()             // avoid duplicates
                ->values();

            if ($regionIds->isNotEmpty()) {
                $rows = $regionIds->map(fn($rid) => [
                    'channel_id' => $channel->id,
                    'region_id' => $rid,
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
//         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
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
            'name' => 'required|unique:channels,name,' . $channel->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
            'regions' => 'nullable|array',
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
                'name' => $request->name,
                'slug' => Str::slug($request->name),
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
                $rows = $regionIds->map(fn($rid) => [
                    'channel_id' => $channel->id,
                    'region_id' => $rid,
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
                'status' => true,
                'message' => 'Channels fetched successfully',
                'data' => $channels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index_by_region_api(Request $request, $region = null)
    {
        try {
            // region can come from URL or body/query
            $input = strtoupper($region ?? $request->input('region', ''));

            $allowed = ['AU', 'CA', 'UK', 'US'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // Only channels that have the requested region
            $channels = \App\Models\Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->with([
                    'regions:id,region_code' // keep full regions list in payload (unchanged shape)
                ])
                ->latest()
                ->get();

            // enrich + hide fields (same as your index_api)
            $channels->each(function ($channel) {
                $channel->image_url = $channel->image ? asset($channel->image) : null;
                $channel->makeHidden(['image']);
                if ($channel->relationLoaded('regions')) {
                    $channel->regions->each->makeHidden(['pivot']);
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'Channels fetched successfully',
                'data' => $channels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function filter_region_api(Request $request, $region = null)
    {

        $parseTags = function ($raw) {
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && ($raw[0] === '[' || str_contains($raw, ','))) {
                    $arr = $raw[0] === '[' ? json_decode($raw, true) : explode(',', $raw);
                } else {
                    $arr = $raw === '' ? [] : [$raw];
                }
            } elseif (is_array($raw)) {
                $arr = $raw;
            } else {
                $arr = [];
            }
            $arr = array_map(fn($t) => is_string($t) ? trim($t) : $t, $arr);
            $arr = array_values(array_filter($arr, fn($t) => is_string($t) && $t !== ''));
            return array_values(array_unique($arr));
        };

        $parseIds = function ($raw) {
            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw !== '' && $raw[0] === '[') {
                    $parts = json_decode($raw, true);
                } else {
                    $parts = explode(',', $raw);
                }
            } elseif (is_array($raw)) {
                $parts = $raw;
            } else {
                $parts = [];
            }
            $ids = [];
            foreach ($parts as $p) {
                $id = (int) trim((string) $p);
                if ($id > 0)
                    $ids[$id] = true;
            }
            return array_values(array_unique(array_keys($ids))); // unique ints
        };

        $applyVideoTagFilter = function ($q, array $tags, bool $matchAll) {
            if (empty($tags))
                return;
            $q->where(function ($sub) use ($tags, $matchAll) {
                foreach ($tags as $idx => $tag) {
                    $expr = "JSON_CONTAINS(CAST(videos.tags AS JSON), ?)";
                    $param = json_encode($tag, JSON_UNESCAPED_UNICODE);
                    if ($matchAll) {
                        $sub->whereRaw($expr, [$param]);
                    } else {
                        $idx === 0
                            ? $sub->whereRaw($expr, [$param])
                            : $sub->orWhereRaw($expr, [$param]);
                    }
                }
            });
        };

        $applyVideoHighlightFilter = function ($q, array $ids, bool $matchAll) {
            if (empty($ids))
                return;
            $q->where(function ($sub) use ($ids, $matchAll) {
                foreach ($ids as $idx => $id) {
                    $expr = "FIND_IN_SET(?, videos.highlight_tags)";
                    if ($matchAll) {
                        $sub->whereRaw($expr, [$id]);
                    } else {
                        $idx === 0
                            ? $sub->whereRaw($expr, [$id])
                            : $sub->orWhereRaw($expr, [$id]);
                    }
                }
            });
        };

        $input = strtoupper($region ?? $request->input('region', ''));
        $allowed = ['AU', 'CA', 'UK', 'US']; // extend as needed
        $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

        // Parse comma-separated input for multiple IDs
        $channelIds = $parseIds($request->input('channel', []));
        $categoryIds = $parseIds($request->input('category', []));
        $characterIds = $parseIds($request->input('character', []));

        $tagsInputRaw = $request->input('tags', $request->input('tag', []));
        $hlInputRaw = $request->input('highlight', $request->input('highlight_tags', $request->input('highlights', [])));

        $tagsFilter = $parseTags($tagsInputRaw);
        $hlFilter = $parseIds($hlInputRaw);
        $matchAll = $request->boolean('match_all', false);
        $tagIdsFilter = $parseIds($request->input('tag_ids', []));

        $hasTagOrHlOrTagIds = !empty($tagsFilter) || !empty($hlFilter) || !empty($tagIdsFilter);

        $videoWhere = function ($q) use ($regionCode, $applyVideoTagFilter, $applyVideoHighlightFilter, $tagsFilter, $hlFilter, $matchAll, $tagIdsFilter) {
            $q->whereHas('regions', fn($r) => $r->where('region_code', $regionCode));
            $applyVideoTagFilter($q, $tagsFilter, $matchAll);
            $applyVideoHighlightFilter($q, $hlFilter, $matchAll);
           
            if (!empty($tagIdsFilter)) {
                $q->where(function ($sub) use ($tagIdsFilter, $matchAll) {
                    foreach ($tagIdsFilter as $idx => $tagId) {
                        $expr = 'FIND_IN_SET(?, videos.tag_ids)';
                        if ($matchAll) {
                            $sub->whereRaw($expr, [$tagId]);        // AND
                        } else {
                            $idx === 0
                                ? $sub->whereRaw($expr, [$tagId])   // first
                                : $sub->orWhereRaw($expr, [$tagId]); // OR others
                        }
                    }
                });
            }
        };

        $channelsQuery = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->with(['regions:id,region_code'])
            ->latest();

        if (!empty($channelIds)) {
            $channelsQuery->whereIn('id', $channelIds);
        } elseif ($hasTagOrHlOrTagIds) {
            $channelsQuery->whereHas('categories.characters.videos', $videoWhere);
        }

        $channels = $channelsQuery->get()->each(function ($ch) {
            $ch->image_url = $ch->image ? asset($ch->image) : null;
            $ch->makeHidden(['image']);
            if ($ch->relationLoaded('regions'))
                $ch->regions->each->makeHidden(['pivot']);
        });

        $channelIdsForChildren = empty($channelIds) ? $channels->pluck('id')->all() : $channelIds;

        $categoriesQuery = Category::select('id', 'name', 'slug', 'image', 'channel_id', 'created_at', 'updated_at')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->when(!empty($channelIdsForChildren), fn($q) => $q->whereIn('channel_id', $channelIdsForChildren))
            ->with(['regions:id,region_code'])
            ->latest();

        if (!empty($categoryIds)) {
            $categoriesQuery->whereIn('id', $categoryIds);
        } elseif ($hasTagOrHlOrTagIds) {
            $categoriesQuery->whereHas('characters.videos', $videoWhere);
        }

        $categories = $categoriesQuery->get()->each(function ($cat) {
            $cat->image_url = $cat->image ? asset($cat->image) : null;
            $cat->makeHidden(['image']);
            if ($cat->relationLoaded('regions'))
                $cat->regions->each->makeHidden(['pivot']);
        });

        $categoryIdsForChildren = empty($categoryIds) ? $categories->pluck('id')->all() : $categoryIds;

        $charactersQuery = Character::select([
            'id',
            'name',
            'image',
            'category_id',
            'character_page_url_slug',
            'public_private_toggle',
            'created_at',
            'updated_at',
            'character_tag',
            'character_role'
        ])
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->when(!empty($categoryIdsForChildren), fn($q) => $q->whereIn('category_id', $categoryIdsForChildren))
            ->with(['regions:id,region_code'])
            ->latest();

        if (!empty($characterIds)) {
            $charactersQuery->whereIn('id', $characterIds);
        } elseif ($hasTagOrHlOrTagIds) {
            $charactersQuery->whereHas('videos', $videoWhere);
        }

        $characters = $charactersQuery->get()->each(function ($c) {
            $c->image_url = $c->image ? asset($c->image) : null;
            $c->character_tag = $c->character_tag ? explode(',', $c->character_tag) : [];
            $c->character_role = $c->character_role ? explode(',', $c->character_role) : [];
            $c->makeHidden(['image']);
            if ($c->relationLoaded('regions'))
                $c->regions->each->makeHidden(['pivot']);
        });

        $characterIdsForChildren = empty($characterIds) ? $characters->pluck('id')->all() : $characterIds;

        $videosQuery = Video::select([
            'id',
            'title',
            'thumbnail_url',
            'character_id',
            'tag_ids',
            'highlight_tags',
            'created_at',
            'updated_at'
        ])
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->when(!empty($characterIdsForChildren), fn($q) => $q->whereIn('character_id', $characterIdsForChildren))
            ->latest();

        $applyVideoTagFilter($videosQuery, $tagsFilter, $matchAll);
        $applyVideoHighlightFilter($videosQuery, $hlFilter, $matchAll);
     
        if (!empty($tagIdsFilter)) {
            $videosQuery->where(function ($sub) use ($tagIdsFilter, $matchAll) {
                foreach ($tagIdsFilter as $idx => $tagId) {
                    $expr = 'FIND_IN_SET(?, videos.tag_ids)';
                    if ($matchAll) {
                        $sub->whereRaw($expr, [$tagId]);        // AND
                    } else {
                        $idx === 0
                            ? $sub->whereRaw($expr, [$tagId])   // first
                            : $sub->orWhereRaw($expr, [$tagId]); // OR others
                    }
                }
            });
        }
        
        $videos = $videosQuery->get();

        $tagIds = [];
        foreach ($videos as $v) {
            // Split tag_ids (comma-separated string) into an array of IDs
            $vTagIds = explode(',', (string) $v->tag_ids);
            // foreach ($vTagIds as $id) {
            //     $tagIds[$id] = true;  // Add unique tag ID to the set
            // }
            foreach ($vTagIds as $id) {
    $id = (int) trim((string) $id);
    if ($id > 0) {
        $tagIds[$id] = true; // Add unique numeric tag ID to the set
    }
}
        }

        // Fetch tag names for the tag_ids
        $tagsWithNames = Tag::whereIn('id', array_keys($tagIds))
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->toArray();

        $tagsIdsList = collect(array_keys($tagIds))
    ->filter() // remove zeros/nulls just in case
    ->map(fn($id) => ['id' => (int) $id, 'name' => ($tagsWithNames[$id] ?? '')])
    ->values()
    ->all();

        $tagSet = [];
        $highlightTagIds = [];
        foreach ($videos as $v) {
            $vTags = is_array($v->tags) ? $v->tags : (is_string($v->tags) ? json_decode($v->tags, true) : []);
            if (is_array($vTags)) {
                foreach ($vTags as $t) {
                    if (is_string($t) && $t !== '')
                        $tagSet[$t] = true;
                }
            }
            foreach (explode(',', (string) $v->highlight_tags) as $rawId) {
                $id = (int) trim($rawId);
                if ($id > 0)
                    $highlightTagIds[$id] = true;
            }
        }

        $availableTags = array_keys($tagSet);
        sort($availableTags);

        $availableHighlightTags = [];
        if (!empty($highlightTagIds)) {
            $ids = array_keys($highlightTagIds);
            $availableHighlightTags = HighlightTag::select('id', 'label', 'emoji')
                ->whereIn('id', $ids)
                ->orderBy('label')
                ->get()
                ->map(fn($ht) => ['id' => $ht->id, 'label' => $ht->label, 'emoji' => asset($ht->emoji)])
                ->values();
        }


        // Format videos and include tag names
        $videosPayload = $videos->map(function ($v) use ($tagsWithNames) {
            // Parse tag_ids (comma-separated string) into an array
            $vTagIds = explode(',', (string) $v->tag_ids);
            $highlightIds = collect(explode(',', (string) $v->highlight_tags))
                ->filter()->map(fn($x) => (int) trim($x))->filter()->values()->all();

            // Fetch tag names for the tag_ids
            // $tagDetails = array_map(function ($id) use ($tagsWithNames) {
            //     // Ensure tag name exists, else return empty string
            //     $name = $tagsWithNames[$id] ?? '';
            //     return ['id' => $id, 'name' => $name];
            // }, $vTagIds);
            $tagDetails = array_map(function ($id) use ($tagsWithNames) {
                $id = (int) trim((string) $id);
                $name = $tagsWithNames[$id] ?? '';
                return ['id' => $id, 'name' => $name];
            }, $vTagIds);

            return [
                'id' => $v->id,
                'title' => $v->title,
                'thumbnail_url' => $v->thumbnail_url ? asset($v->thumbnail_url) : null,
                'character_id' => $v->character_id,
                'tags' => $tagDetails, 
                'highlight_tag_ids' => $highlightIds,
                'created_at' => optional($v->created_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Region filter fetched successfully',
            'data' => [
                'channels' => $channels,
                'categories' => $categories,
                'characters' => $characters,
                'videos' => $videosPayload,
                'highlight_tags' => $availableHighlightTags,   
                'tags_ids' => $tagsIdsList,
            ],
        ]);
    }








    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:channels,name',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10048',
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10048',
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