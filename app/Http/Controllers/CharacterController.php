<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Channel;
use App\Models\Video;
use App\Models\Tag;
use App\Models\CharacterRole;
use App\Models\Subscription;
use App\Models\CharacterTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use Illuminate\Support\Facades\Validator;

class CharacterController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:character.view', only: ['index']),
            new Middleware('permission:character.create', only: ['create', 'store']),
            new Middleware('permission:character.edit', only: ['edit', 'update']),
            new Middleware('permission:character.delete', only: ['destroy']),

        ];
    }
    public function index()
    {
        $characters = Character::with('category')->latest()->get();
        return view('admin.characters.index', compact('characters'));
    }

    public function create()
    {
        $categories = \App\Models\Category::all();
        $character_role = CharacterRole::all();
        $character_tag = CharacterTag::all();

        // Only active regions
        $regions = \App\Models\Region::where('is_active', 1)->get();

        return view('admin.characters.create', compact(
            'categories',
            'character_role',
            'character_tag',
            'regions'
        ));
    }


    public function store(Request $request)
    {
        // dd($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'persona' => 'nullable|string',
            'details' => 'nullable|string',
            //'channel_id' => 'required|exists:channels,id',
            'category_id' => 'required|exists:categories,id',

            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10048',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo|max:20480', // 20 MB

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:100',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:100',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:100',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:100',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:100',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:100',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:100',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:100',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:100',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:100',
            'brand_reputation_notes' => 'nullable|string',

            // New fields
            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'page_heading' => 'nullable|string|max:255',
            'page_sub_heading' => 'nullable|string|max:255',
            'preferences' => 'nullable|string',
            'loved_pet1' => 'nullable|string|max:255',
            'loved_pet2' => 'nullable|string|max:255',
            'loved_pet3' => 'nullable|string|max:255',
            'hated_pet1' => 'nullable|string|max:255',
            'hated_pet2' => 'nullable|string|max:255',
            'hated_pet3' => 'nullable|string|max:255',
            'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')],
            'public_private_toggle' => 'required|boolean',
            'character_launch_date' => 'nullable|date',
            'character_popularity_score' => 'nullable|integer|min:0',
            'editor_notes_content_guidelines' => 'nullable|string',

            'character_tag' => 'nullable|array',
            'character_tag.*' => 'string',
            'character_role' => 'nullable|array',
            'character_role.*' => 'string',
        ]);
        $validated['regions'] = $request->input('regions', []);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            $destinationPath = public_path('/characters');

            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $image->move($destinationPath, $imageName);
            $validated['image'] = 'characters/' . $imageName;
        }
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_' . Str::random(6) . '.' . $video->getClientOriginalExtension();

            $destinationPath = public_path('/character_videos'); // no space
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $video->move($destinationPath, $videoName);
            $validated['video'] = 'character_videos/' . $videoName; // relative public path
        }



        if (empty($validated['character_page_url_slug'])) {
            $baseSlug = Str::slug($validated['name']);
        } else {
            $baseSlug = Str::slug($validated['character_page_url_slug']); // Use the provided slug for the base
        }

        $uniqueSlug = $baseSlug;
        $i = 1;
        while (Character::where('character_page_url_slug', $uniqueSlug)->exists()) {
            $uniqueSlug = $baseSlug . '-' . $i++;
        }
        $validated['character_page_url_slug'] = $uniqueSlug;

        $validated['character_tag'] = $request->has('character_tag') ? implode(',', $validated['character_tag']) : null;

        $validated['character_role'] = $request->has('character_role') ? implode(',', $validated['character_role']) : null;


        $character = Character::create($validated);

        if (!empty($validated['regions'])) {
            $character->regions()->attach($validated['regions']);
        }

        return redirect()->route('admin.characters.index')->with('success', 'Character created successfully.');
    }

    public function edit(Character $character)
    {
        $categories = \App\Models\Category::all();
        $character_role = CharacterRole::all();
        $character_tag = CharacterTag::all();

        $regions = \App\Models\Region::all();
        $selectedRegions = $character->regions->pluck('id')->toArray();

        // Convert comma-separated values into arrays
        $currentTagNames = $character->character_tag
            ? explode(',', $character->character_tag)
            : [];

        $currentRoleNames = $character->character_role
            ? explode(',', $character->character_role)
            : [];

        return view('admin.characters.edit', compact(
            'character',
            'categories',
            'character_role',
            'character_tag',
            'regions',
            'selectedRegions',
            'currentTagNames',
            'currentRoleNames'
        ));
    }


    public function update(Request $request, Character $character)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'persona' => 'nullable|string',
            'details' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',

            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10048',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo|max:20480',

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:100',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:100',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:100',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:100',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:100',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:100',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:100',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:100',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:100',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:100',
            'brand_reputation_notes' => 'nullable|string',

            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'page_heading' => 'nullable|string|max:255',
            'page_sub_heading' => 'nullable|string|max:255',
            'preferences' => 'nullable|string',
            'loved_pet1' => 'nullable|string|max:255',
            'loved_pet2' => 'nullable|string|max:255',
            'loved_pet3' => 'nullable|string|max:255',
            'hated_pet1' => 'nullable|string|max:255',
            'hated_pet2' => 'nullable|string|max:255',
            'hated_pet3' => 'nullable|string|max:255',
            'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')->ignore($character->id)],
            'public_private_toggle' => 'required|boolean',
            'character_launch_date' => 'nullable|date',
            'character_popularity_score' => 'nullable|integer|min:0',
            'editor_notes_content_guidelines' => 'nullable|string',

            'character_tag' => 'nullable|array',
            'character_tag.*' => 'string',
            'character_role' => 'nullable|array',
            'character_role.*' => 'string',
        ]);
        $validated['regions'] = $request->input('regions', []);
        // Handle image update
        if ($request->hasFile('image')) {
            if ($character->image && File::exists(public_path($character->image))) {
                File::delete(public_path($character->image));
            }

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/characters');

            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $image->move($destinationPath, $imageName);
            $validated['image'] = 'characters/' . $imageName;
        } else {
            $validated['image'] = $character->image;
        }

        if ($request->hasFile('video')) {
        // Delete old video if exists
        if ($character->video && File::exists(public_path($character->video))) {
            File::delete(public_path($character->video));
        }

        $video = $request->file('video');
        $videoName = time() . '_' . Str::random(6) . '.' . $video->getClientOriginalExtension();

        $destinationPath = public_path('/character_videos');
        if (!File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true, true);
        }

        $video->move($destinationPath, $videoName);
        $validated['video'] = 'character_videos/' . $videoName;
    }


        // Generate character_page_url_slug
        if (empty($validated['character_page_url_slug'])) {
            $baseSlug = Str::slug($validated['name']);
        } else {
            $baseSlug = Str::slug($validated['character_page_url_slug']);
        }

        $uniqueSlug = $baseSlug;
        $i = 1;
        while (
            Character::where('character_page_url_slug', $uniqueSlug)
                ->where('id', '!=', $character->id)
                ->exists()
        ) {
            $uniqueSlug = $baseSlug . '-' . $i++;
        }
        $validated['character_page_url_slug'] = $uniqueSlug;

        $validated['character_tag'] = $request->has('character_tag') ? implode(',', $validated['character_tag']) : null;
        $validated['character_role'] = $request->has('character_role') ? implode(',', $validated['character_role']) : null;

        $character->update($validated);
        $character->regions()->sync($validated['regions']);

        return redirect()->route('admin.characters.index')->with('success', 'Character updated successfully.');
    }


    public function destroy(Character $character)
    {
        $character->delete();
        return back()->with('success', 'Character deleted.');
    }



    // API's

    /* =========================
     * LIST
     * ========================= */
    public function index_api(Request $request)
    {
        try {
            // Optional pagination (defaults to full list like your Channel API)
            $query = Character::select([
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
                ->with([
                    'category:id,name',
                    'regions:id,region_code'
                ])
                ->latest();

            // If you want pagination: /api/characters?page=1&per_page=20
            if ($request->boolean('paginate')) {
                $perPage = (int) $request->input('per_page', 20);
                $page = (int) $request->input('page', 1);
                $characters = $query->paginate($perPage, ['*'], 'page', $page);
            } else {
                $characters = $query->get();
            }

            $transform = function ($c) {
                $c->image_url = $c->image ? asset($c->image) : null;

                $c->character_tag = $c->character_tag ? explode(',', $c->character_tag) : [];
                $c->character_role = $c->character_role ? explode(',', $c->character_role) : [];

                $c->makeHidden(['image']);

                if ($c->relationLoaded('regions')) {
                    $c->regions->each->makeHidden(['pivot']);
                }
                return $c;
            };

            if ($characters instanceof \Illuminate\Pagination\AbstractPaginator) {
                $characters->getCollection()->transform($transform);
            } else {
                $characters->each($transform);
            }

            return response()->json([
                'status' => true,
                'message' => 'Characters fetched successfully',
                'data' => $characters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch characters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index_by_region_api(Request $request, $region = null)
    {
        try {
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';


            $query = Character::select([
                'id',
                'name',
                'image',
                'category_id',
                'character_page_url_slug',
                'persona',
                'public_private_toggle',
                'created_at',
                'updated_at',
                'character_tag',
                'character_role'
            ])
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->with([
                    'category:id,name',
                    'regions:id,region_code'
                ])
                ->latest();

            // 3) Pagination (optional, same pattern as your index_api)
            if ($request->boolean('paginate')) {
                $perPage = (int) $request->input('per_page', 20);
                $page = (int) $request->input('page', 1);
                $characters = $query->paginate($perPage, ['*'], 'page', $page);
            } else {
                $characters = $query->get();
            }

            $transform = function ($c) {
                $c->image_url = $c->image ? asset($c->image) : null;

                $c->character_tag = $c->character_tag ? explode(',', $c->character_tag) : [];
                $c->character_role = $c->character_role ? explode(',', $c->character_role) : [];

                $c->makeHidden(['image']);

                if ($c->relationLoaded('regions')) {
                    $c->regions->each->makeHidden(['pivot']);
                }

                return $c;
            };

            if ($characters instanceof \Illuminate\Pagination\AbstractPaginator) {
                $characters->getCollection()->transform($transform);
            } else {
                $characters->each($transform);
            }

            return response()->json([
                'status' => true,
                'message' => 'Characters fetched successfully',
                'data' => $characters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch characters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =========================
     * CREATE
     * ========================= */
    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],

            'persona' => ['nullable', 'string'],
            'details' => ['nullable', 'string'],

            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:10048'],

            'location' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0'],
            'species' => ['nullable', 'string', 'max:255'],
            'style_vibe' => ['nullable', 'string', 'max:255'],

            'durability_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'durability_notes' => ['nullable', 'string'],
            'comfort_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'comfort_notes' => ['nullable', 'string'],
            'style_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'style_notes' => ['nullable', 'string'],
            'affordability_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'affordability_notes' => ['nullable', 'string'],
            'tech_feature_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tech_feature_notes' => ['nullable', 'string'],
            'eco_friendliness_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'eco_friendliness_notes' => ['nullable', 'string'],
            'engagement_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'engagement_notes' => ['nullable', 'string'],
            'ease_of_use_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ease_of_use_notes' => ['nullable', 'string'],
            'performance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'performance_notes' => ['nullable', 'string'],
            'brand_reputation_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'brand_reputation_notes' => ['nullable', 'string'],

            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'page_heading' => ['nullable', 'string', 'max:255'],
            'page_sub_heading' => ['nullable', 'string', 'max:255'],
            'preferences' => ['nullable', 'string'],
            'loved_pet1' => ['nullable', 'string', 'max:255'],
            'loved_pet2' => ['nullable', 'string', 'max:255'],
            'loved_pet3' => ['nullable', 'string', 'max:255'],
            'hated_pet1' => ['nullable', 'string', 'max:255'],
            'hated_pet2' => ['nullable', 'string', 'max:255'],
            'hated_pet3' => ['nullable', 'string', 'max:255'],
            'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')],
            'public_private_toggle' => ['required', 'boolean'],
            'character_launch_date' => ['nullable', 'date'],
            'character_popularity_score' => ['nullable', 'integer', 'min:0'],
            'editor_notes_content_guidelines' => ['nullable', 'string'],

            // arrays in API; stored as comma strings
            'character_tag' => ['nullable', 'array'],
            'character_tag.*' => ['string'],
            'character_role' => ['nullable', 'array'],
            'character_role.*' => ['string'],

            // regions via pivot
            'regions' => ['nullable', 'array'],
            'regions.*' => ['integer', 'exists:regions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $dir = public_path('characters');
                if (!File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->move($dir, $filename);
                $data['image'] = 'characters/' . $filename; // store relative path like your form code
            }

            // Slug (unique)
            $baseSlug = !empty($data['character_page_url_slug'])
                ? Str::slug($data['character_page_url_slug'])
                : Str::slug($data['name']);

            $slug = $baseSlug;
            $i = 1;
            while (Character::where('character_page_url_slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }
            $data['character_page_url_slug'] = $slug;

            // tags/roles
            $data['character_tag'] = isset($data['character_tag']) ? implode(',', $data['character_tag']) : null;
            $data['character_role'] = isset($data['character_role']) ? implode(',', $data['character_role']) : null;

            // pull & remove regions list
            $regionIds = $data['regions'] ?? [];
            unset($data['regions']);

            $character = Character::create($data);

            if (!empty($regionIds)) {
                $character->regions()->attach($regionIds);
            }

            // enrich for response
            $character->load(['category:id,name', 'regions:id,region_code']);
            $character->image_url = $character->image ? asset($character->image) : null;
            $character->character_tag = $character->character_tag ? explode(',', $character->character_tag) : [];
            $character->character_role = $character->character_role ? explode(',', $character->character_role) : [];
            $character->makeHidden(['image']);
            if ($character->relationLoaded('regions')) {
                $character->regions->each->makeHidden(['pivot']);
            }

            return response()->json([
                'status' => true,
                'message' => 'Character created successfully',
                'data' => $character
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create character',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =========================
     * SHOW
     * ========================= */
    public function show_api($id)
    {
        $character = Character::with([
            'category:id,name',
            'regions:id,region_code'
        ])->find($id);

        if (!$character) {
            return response()->json([
                'status' => false,
                'message' => 'Character not found',
            ], 404);
        }

        $character->image_url = $character->image ? asset($character->image) : null;
        $character->character_tag = $character->character_tag ? explode(',', $character->character_tag) : [];
        $character->character_role = $character->character_role ? explode(',', $character->character_role) : [];
        $character->makeHidden(['image']);
        if ($character->relationLoaded('regions')) {
            $character->regions->each->makeHidden(['pivot']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Character details fetched successfully',
            'data' => $character
        ]);
    }


    public function showWithVideos(Request $request, $id, $region)
    {
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;

        // dd($user);

        $request->validate([
            'channel_id' => 'sometimes|integer',
            'category_id' => 'sometimes|integer',
        ]);

        $character = Character::with([
            'category:id,name',
            'regions:id,region_code',
        ])->find($id);

        if (!$character) {
            return response()->json([
                'status' => false,
                'message' => 'Character not found.',
                'data' => [],
            ], 404);
        }

        $regionCode = strtoupper($region);
        $allowedRegions = ['AU', 'CA', 'UK', 'US'];
        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }

        $q = Video::with([
            'regions:id,region_code',

            'reviews' => function ($q) {
                $q->select('id', 'video_id', 'user_id', 'rating', 'review', 'status', 'created_at')   // trim columns
                    ->where('status', 'approved')
                    ->latest();
            },
            'reviews.user_api:id,name,profile_image',
        ])
            ->withCount([
                'reviews as rating_count' => function ($q) {
                    $q->where('status', 'approved');
                }
            ])
            ->withAvg([
                'reviews as rating_avg' => function ($q) {
                    $q->where('status', 'approved');
                }
            ], 'rating')
            ->where('status', 'published')
            ->where('character_id', $character->id);

        // dd($user);
        if ($user && $this->hasValidSubscription($user->id)) {
            $q->whereIn('type', ['youtube', 'vimeo']); // Paid + free
        } else {
            $q->where('type', 'youtube'); // Free only
        }

        foreach (['channel_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }

        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });

        $videos = $q->latest()->get();

        $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])
            ->filter()
            ->unique();

        $tagMap = $allTagIds->isNotEmpty()
            ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
            : collect();

        $videos->each(function ($v) use ($tagMap) {
            $ids = collect($v->tag_ids_array ?? []);
            $v->tag_pairs = $ids->map(function ($id) use ($tagMap) {
                $name = $tagMap->get($id);
                return $name ? ['id' => $id, 'name' => $name] : null;
            })->filter()->values()->all();
        });

        $videoData = $videos->map(function ($video) {
            if ($video->relationLoaded('regions')) {
                $video->regions->each->makeHidden(['pivot']);
            }

            $reviews = ($video->reviews ?? collect())->map(function ($rev) {
                $reviewerName = optional($rev->user_api)->name;
                $reviewerImage = optional($rev->user_api)->profile_image
                    ? asset(optional($rev->user_api)->profile_image)
                    : null;

                return [
                    'id' => $rev->id,
                    'rating' => (int) $rev->rating, // stars
                    'review' => $rev->review,
                    'status' => $rev->status,
                    'created_at' => optional($rev->created_at)->toDateTimeString(),
                    'reviewer_name' => $reviewerName,
                    'reviewer_profile_image' => $reviewerImage,
                ];
            })->values();

            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'type' => $video->type,
                'video_url' => $video->video_url,
                'thumbnail_url' => $video->thumbnail_url,
                'character_id' => $video->character_id,
                'channel_id' => $video->channel_id,
                'category_id' => $video->category_id,
                'access_level' => $video->access_level,
                'affiliate_link' => $video->affiliate_link,
                'tags' => $video->tag_pairs,
                'rating_type' => $video->rating_type,
                'sponsorship_type' => $video->sponsorship_type,
                'highlight_tags' => $video->highlight_tags,
                'auto_tags' => $video->auto_tags,
                'created_at' => optional($video->created_at)->toDateTimeString(),
                'updated_at' => optional($video->updated_at)->toDateTimeString(),
                'product_name' => $video->product_name,
                'product_asin_sku' => $video->product_asin_sku,
                'public_rating' => $video->public_rating,
                'review_details' => $video->review_details,
                'character_score' => $video->character_score,
                'editorial_score' => $video->editorial_score,
                'final_beastie_score' => $video->final_beastie_score,
                'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
                'video_type' => $video->video_type,
                'video_platforms' => $video->video_platforms,
                'youtube_id' => $video->youtube_id,
                'wistia_id' => $video->wistia_id,
                'raw_video_path' => $video->raw_video_path,
                'caption_file' => $video->caption_file,
                'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
                'is_draft' => $video->is_draft,
                'status' => $video->status,
                'tag_ids' => $video->tag_ids,
                'is_ai_generated' => $video->is_ai_generated,
                'is_finalized' => $video->is_finalized,
                'qa_passed' => $video->qa_passed,
                'post_schedule_at' => $video->post_schedule_at,
                'review_type' => $video->review_type,
                'sponsored' => $video->sponsored,
                'seo_title' => $video->seo_title,
                'seo_description' => $video->seo_description,
                'hashtags' => $video->hashtags,
                'cta_text' => $video->cta_text,
                'og_image_url' => $video->og_image_url,
                'open_graph_image' => $video->open_graph_image,
                'twitter_title' => $video->twitter_title,
                'twitter_description' => $video->twitter_description,
                'original_price' => $video->original_price,
                'views' => $video->views,
                'likes' => $video->likes,
                'sale_end_date' => $video->sale_end_date,
                'is_amazon_choice' => $video->is_amazon_choice,
                'regions' => $video->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ]),
                'reviews' => $reviews,
                'rating_avg' => $video->rating_avg ? round((float) $video->rating_avg, 2) : null,
                'rating_count' => (int) ($video->rating_count ?? 0),
            ];
        });

        $characterReviewRows = \App\Models\Review::with(['user:id,name,profile_image'])
            ->whereIn('video_id', $videos->pluck('id'))
            ->where('status', 'approved')
            ->latest()
            ->get();

        $characterReviews = $characterReviewRows->map(function ($rev) {
            return [
                'id' => $rev->id,
                'video_id' => $rev->video_id,
                'rating' => (int) $rev->rating,
                'review' => $rev->review,
                'created_at' => optional($rev->created_at)->toDateTimeString(),
                'reviewer_name' => optional($rev->user)->name,
                'reviewer_profile_image' => optional($rev->user)->profile_image
                    ? asset(optional($rev->user)->profile_image)
                    : null,
            ];
        })->values();

        $characterRatingAvg = $characterReviewRows->avg('rating');
        $characterRatingCount = $characterReviewRows->count();

        $characterPayload = [
            'id' => $character->id,
            'name' => $character->name,
            'persona' => $character->persona,
            'details' => $character->details,
            'image' => $character->image ? asset($character->image) : null,
            'created_at' => optional($character->created_at)->toJSON(),
            'updated_at' => optional($character->updated_at)->toJSON(),
            'location' => $character->location,
            'age' => $character->age,
            'species' => $character->species,
            'style_vibe' => $character->style_vibe,
            'durability_score' => $character->durability_score,
            'durability_notes' => $character->durability_notes,
            'comfort_score' => $character->comfort_score,
            'comfort_notes' => $character->comfort_notes,
            'style_score' => $character->style_score,
            'style_notes' => $character->style_notes,
            'affordability_score' => $character->affordability_score,
            'affordability_notes' => $character->affordability_notes,
            'tech_feature_score' => $character->tech_feature_score,
            'tech_feature_notes' => $character->tech_feature_notes,
            'eco_friendliness_score' => $character->eco_friendliness_score,
            'eco_friendliness_notes' => $character->eco_friendliness_notes,
            'engagement_score' => $character->engagement_score,
            'engagement_notes' => $character->engagement_notes,
            'ease_of_use_score' => $character->ease_of_use_score,
            'ease_of_use_notes' => $character->ease_of_use_notes,
            'performance_score' => $character->performance_score,
            'performance_notes' => $character->performance_notes,
            'brand_reputation_score' => $character->brand_reputation_score,
            'brand_reputation_notes' => $character->brand_reputation_notes,
            'sex' => $character->sex,
            'page_heading' => $character->page_heading,
            'page_sub_heading' => $character->page_sub_heading,
            'preferences' => $character->preferences,
            'loved_pet1' => $character->loved_pet1,
            'loved_pet2' => $character->loved_pet2,
            'loved_pet3' => $character->loved_pet3,
            'hated_pet1' => $character->hated_pet1,
            'hated_pet2' => $character->hated_pet2,
            'hated_pet3' => $character->hated_pet3,
            'character_page_url_slug' => $character->character_page_url_slug,
            'public_private_toggle' => $character->public_private_toggle,
            'character_launch_date' => $character->character_launch_date,
            'character_popularity_score' => $character->character_popularity_score,
            'editor_notes_content_guidelines' => $character->editor_notes_content_guidelines,
            'character_tag' => $character->character_tag,
            'character_role' => $character->character_role,
            'category_id' => $character->category_id,
            'image_url' => $character->image_url ?? null,
            'category' => $character->relationLoaded('category') && $character->category ? [
                'id' => $character->category->id,
                'name' => $character->category->name,
            ] : null,
            'regions' => $character->regions
                ? $character->regions->map(fn($r) => ['id' => $r->id, 'region_code' => $r->region_code])
                : [],
            'videos' => $videoData,
            // NEW: character-level reviews + aggregates
            'character_reviews' => $characterReviews,
            'character_rating_avg' => $characterRatingAvg ? round((float) $characterRatingAvg, 2) : null,
            'character_rating_count' => $characterRatingCount,
        ];

        return response()->json([
            'status' => true,
            'message' => ($user && $this->hasValidSubscription($user->id))
                ? 'Character and paid videos (including free) fetched successfully'
                : 'Character and free videos fetched successfully (subscription required for more).',
            'data' => $characterPayload,
        ], 200);
    }

    // private function hasValidSubscription(int $userId): bool
    // {
    //     // implement / reuse your actual subscription check
    //     return method_exists($this, 'subscriptionService')
    //         ? $this->subscriptionService()->isActive($userId)
    //         : false;
    // }
    private function hasValidSubscription(int $userId): bool
    {
        $sub = Subscription::where('user_id', $userId)
            ->latest('subscription_end_date')
            ->first();


        if (!$sub || $sub->trashed()) {
            return false;
        }

        $now = now();

        $statusOkay = in_array($sub->subscription_status, ['active', 'trialing'], true);
        $notCanceled = $sub->subscription_status !== 'canceled' && is_null($sub->canceled_at);

        $withinPaidPeriod = $sub->subscription_end_date && $now->lte($sub->subscription_end_date);
        $withinTrial = $sub->trial_end_date && $now->lte($sub->trial_end_date);

        $timeOkay = $withinPaidPeriod || $withinTrial;

        $paymentOkay = ($sub->payment_status === 'succeeded') || ($sub->subscription_status === 'trialing');
        // dd($sub, $statusOkay, $notCanceled, $withinPaidPeriod,$withinTrial, $paymentOkay);

        return $statusOkay && $notCanceled && $timeOkay && $paymentOkay;
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update_api(Request $request, $id)
    {
        $character = Character::find($id);

        if (!$character) {
            return response()->json([
                'status' => false,
                'message' => 'Character not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],

            'persona' => ['nullable', 'string'],
            'details' => ['nullable', 'string'],

            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:10048'],

            'location' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0'],
            'species' => ['nullable', 'string', 'max:255'],
            'style_vibe' => ['nullable', 'string', 'max:255'],

            'durability_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'durability_notes' => ['nullable', 'string'],
            'comfort_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'comfort_notes' => ['nullable', 'string'],
            'style_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'style_notes' => ['nullable', 'string'],
            'affordability_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'affordability_notes' => ['nullable', 'string'],
            'tech_feature_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tech_feature_notes' => ['nullable', 'string'],
            'eco_friendliness_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'eco_friendliness_notes' => ['nullable', 'string'],
            'engagement_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'engagement_notes' => ['nullable', 'string'],
            'ease_of_use_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ease_of_use_notes' => ['nullable', 'string'],
            'performance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'performance_notes' => ['nullable', 'string'],
            'brand_reputation_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'brand_reputation_notes' => ['nullable', 'string'],

            'sex' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'page_heading' => ['nullable', 'string', 'max:255'],
            'page_sub_heading' => ['nullable', 'string', 'max:255'],
            'preferences' => ['nullable', 'string'],
            'loved_pet1' => ['nullable', 'string', 'max:255'],
            'loved_pet2' => ['nullable', 'string', 'max:255'],
            'loved_pet3' => ['nullable', 'string', 'max:255'],
            'hated_pet1' => ['nullable', 'string', 'max:255'],
            'hated_pet2' => ['nullable', 'string', 'max:255'],
            'hated_pet3' => ['nullable', 'string', 'max:255'],
            'character_page_url_slug' => ['nullable', 'string', 'max:255', Rule::unique('characters', 'character_page_url_slug')->ignore($character->id)],
            'public_private_toggle' => ['required', 'boolean'],
            'character_launch_date' => ['nullable', 'date'],
            'character_popularity_score' => ['nullable', 'integer', 'min:0'],
            'editor_notes_content_guidelines' => ['nullable', 'string'],

            'character_tag' => ['nullable', 'array'],
            'character_tag.*' => ['string'],
            'character_role' => ['nullable', 'array'],
            'character_role.*' => ['string'],

            'regions' => ['nullable', 'array'],
            'regions.*' => ['integer', 'exists:regions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Image replace
            if ($request->hasFile('image')) {
                if ($character->image && File::exists(public_path($character->image))) {
                    File::delete(public_path($character->image));
                }
                $img = $request->file('image');
                $dir = public_path('characters');
                if (!File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
                $filename = time() . '.' . $img->getClientOriginalExtension();
                $img->move($dir, $filename);
                $data['image'] = 'characters/' . $filename;
            } else {
                // keep old image if not provided
                $data['image'] = $character->image;
            }

            // Slug (unique but stable)
            $baseSlug = !empty($data['character_page_url_slug'])
                ? Str::slug($data['character_page_url_slug'])
                : Str::slug($data['name']);

            $slug = $baseSlug;
            $i = 1;
            while (
                Character::where('character_page_url_slug', $slug)
                    ->where('id', '!=', $character->id)
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $i++;
            }
            $data['character_page_url_slug'] = $slug;

            // tags/roles to comma strings
            $data['character_tag'] = isset($data['character_tag']) ? implode(',', $data['character_tag']) : null;
            $data['character_role'] = isset($data['character_role']) ? implode(',', $data['character_role']) : null;

            // regions sync
            $regionIds = $data['regions'] ?? null;
            unset($data['regions']);

            $character->update($data);

            if (is_array($regionIds)) {
                $character->regions()->sync($regionIds);
            }

            // enrich for response
            $character->load(['category:id,name', 'regions:id,region_code']);
            $character->image_url = $character->image ? asset($character->image) : null;
            $character->character_tag = $character->character_tag ? explode(',', $character->character_tag) : [];
            $character->character_role = $character->character_role ? explode(',', $character->character_role) : [];
            $character->makeHidden(['image']);
            if ($character->relationLoaded('regions')) {
                $character->regions->each->makeHidden(['pivot']);
            }

            return response()->json([
                'status' => true,
                'message' => 'Character updated successfully',
                'data' => $character
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update character',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =========================
     * DELETE
     * ========================= */
    public function destroy_api($id)
    {
        $character = Character::find($id);

        if (!$character) {
            return response()->json([
                'status' => false,
                'message' => 'Character not found',
            ], 404);
        }

        try {
            // detach regions to keep pivot table clean
            if (method_exists($character, 'regions')) {
                $character->regions()->detach();
            }

            // delete image if any
            if ($character->image && File::exists(public_path($character->image))) {
                File::delete(public_path($character->image));
            }

            $character->delete();

            return response()->json([
                'status' => true,
                'message' => 'Character deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete character',
                'error' => $e->getMessage()
            ], 500);
        }
    }








}

