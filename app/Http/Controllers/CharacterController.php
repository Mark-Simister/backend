<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Channel;
use App\Models\Video;
use App\Models\Tag;
use App\Models\CharacterRole;
use App\Models\Subscription;
use App\Models\CharacterTag;
use App\Models\ProductReview;
use App\Models\Category;
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
            'thumbnail_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10048',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo|max:20480', // 20 MB

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:5',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:5',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:5',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:5',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:5',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:5',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:5',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:5',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:5',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:5',
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
            'character_popularity_score' => 'nullable|integer|min:0|max:5',
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

        if ($request->hasFile('thumbnail_image')) {
            $thumbnail = $request->file('thumbnail_image');
            $thumbnailName = time() . '_thumb.' . $thumbnail->getClientOriginalExtension();

            $destinationPath = public_path('/characters'); // same folder as main image
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $thumbnail->move($destinationPath, $thumbnailName);
            $validated['thumbnail_image'] = 'characters/' . $thumbnailName;
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

    public function getRegions($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        $regions = $category->regions;  // Assuming there's a `regions()` relationship defined in the `Category` model
        return response()->json($regions);
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
            'thumbnail_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10048',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo|max:20480',

            'location' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0',
            'species' => 'nullable|string|max:255',
            'style_vibe' => 'nullable|string|max:255',

            'durability_score' => 'nullable|integer|min:0|max:5',
            'durability_notes' => 'nullable|string',

            'comfort_score' => 'nullable|integer|min:0|max:5',
            'comfort_notes' => 'nullable|string',

            'style_score' => 'nullable|integer|min:0|max:5',
            'style_notes' => 'nullable|string',

            'affordability_score' => 'nullable|integer|min:0|max:5',
            'affordability_notes' => 'nullable|string',

            'tech_feature_score' => 'nullable|integer|min:0|max:5',
            'tech_feature_notes' => 'nullable|string',

            'eco_friendliness_score' => 'nullable|integer|min:0|max:5',
            'eco_friendliness_notes' => 'nullable|string',

            'engagement_score' => 'nullable|integer|min:0|max:5',
            'engagement_notes' => 'nullable|string',

            'ease_of_use_score' => 'nullable|integer|min:0|max:5',
            'ease_of_use_notes' => 'nullable|string',

            'performance_score' => 'nullable|integer|min:0|max:5',
            'performance_notes' => 'nullable|string',

            'brand_reputation_score' => 'nullable|integer|min:0|max:5',
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
            'character_popularity_score' => 'nullable|integer|min:0|max:5',
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

        // Handle thumbnail image update
        if ($request->hasFile('thumbnail_image')) {
            // Delete old thumbnail if exists
            if ($character->thumbnail_image && File::exists(public_path($character->thumbnail_image))) {
                File::delete(public_path($character->thumbnail_image));
            }

            $thumbnail = $request->file('thumbnail_image');
            $thumbnailName = time() . '_thumb.' . $thumbnail->getClientOriginalExtension();
            $destinationPath = public_path('/characters');

            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $thumbnail->move($destinationPath, $thumbnailName);
            $validated['thumbnail_image'] = 'characters/' . $thumbnailName;
        } else {
            $validated['thumbnail_image'] = $character->thumbnail_image;
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
                'thumbnail_image',
                'category_id',
                'character_page_url_slug',
                'public_private_toggle',
                'created_at',
                'updated_at',
                'character_tag',
                'character_role'
            ])
                ->where('public_private_toggle', 0)
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
                'thumbnail_image',
                'category_id',
                'character_page_url_slug',
                'persona',
                'public_private_toggle',
                'created_at',
                'updated_at',
                'character_tag',
                'character_role'
            ])
                ->where('public_private_toggle', 0)
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
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }

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
            'thumbnail_image' => $character->thumbnail_image ? asset($character->thumbnail_image) : null,
            'character_video_url' => $character->video ? asset($character->video) : null,
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
    public function showWithVideosNew(Request $request, $id, $region)
    {
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }
        $isSubscribed = $user && $this->hasValidSubscription($user->id);

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
        // $featured_product_reviews = ProductReview::where('character_id', $character->id)
        //     ->where('is_featured', 1)
        //     ->with('video')
        //     ->get()
        //     ->map(function ($review) {
        //         $video = $review->video;

        //         return [
        //             "id" => $review->id,
        //             "character_id" => $review->character_id,
        //             "video_id" => $review->video_id,
        //             "review_url" => $review->review_url,
        //             "is_featured" => $review->is_featured,
        //             "is_active" => $review->is_active,
        //             "thumbnail_image" => optional($video->thumbnail_image)
        //                 ? asset($video->thumbnail_image)
        //                 : null,
        //             "title" => optional($video)->title,
        //             "description" => optional($video)->description,
        //             "type" => optional($video)->type,
        //             "video_url" => optional($video)->video_url,
        //             "created_at" => $review->created_at,
        //             "updated_at" => $review->updated_at,
        //         ];
        //     });
        $featured_product_reviews = Video::where('character_id', $character->id)
            ->where('is_featured', 1)
            // ->with('channel') // Include the related channel data
            ->with(['channel:id,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta']) // add this
            ->get()
            ->map(function ($video) {
                $finalBeastieScore = $video->final_beastie_score
                    ? round($video->final_beastie_score / 2, 2)
                    : null;
                return [
                    "id" => $video->id,
                    "character_id" => $video->character_id,
                    "video_id" => $video->id,
                    "is_featured" => $video->is_featured,
                    "is_active" => $video->status == 'published', // Assuming 'status' field defines active state
                    "thumbnail_image" => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
                    "title" => $video->title,
                    "description" => $video->description,
                    "type" => $video->type,
                    "video_url" => $video->video_url,
                    "final_beastie_score" => $finalBeastieScore,
                    "created_at" => $video->created_at,
                    "updated_at" => $video->updated_at,
                    "primary_color"    => optional($video->channel)->primary_color,
                    "secondary_color"  => optional($video->channel)->secondary_color,
                    "accent_color"     => optional($video->channel)->accent_color,
                    "background_color" => optional($video->channel)->background_color,
                    "text_color"       => optional($video->channel)->text_color,
                    "hover_color"      => optional($video->channel)->hover_color,
                    "highlight_color"  => optional($video->channel)->highlight_color,
                    "cta"              => optional($video->channel)->cta,
                ];
            });

        // $product_reviews = ProductReview::where('character_id', $character->id)
        //     ->where('is_featured', 0)
        //     ->with('video')
        //     ->get()
        //     ->map(function ($review) {
        //         $video = $review->video;

        //         return [
        //             "id" => $review->id,
        //             "character_id" => $review->character_id,
        //             "video_id" => $review->video_id,
        //             "review_url" => $review->review_url,
        //             "is_featured" => $review->is_featured,
        //             "is_active" => $review->is_active,
        //             "thumbnail_image" => optional($video->thumbnail_image)
        //                 ? asset($video->thumbnail_image)
        //                 : null,
        //             "title" => optional($video)->title,
        //             "description" => optional($video)->description,
        //             "type" => optional($video)->type,
        //             "video_url" => optional($video)->video_url,
        //             "created_at" => $review->created_at,
        //             "updated_at" => $review->updated_at,
        //         ];
        //     });

        $product_reviews = Video::where('character_id', $character->id)
            ->where('is_featured', 0)
            // ->with('channel') // Include the related channel data
            ->with(['channel:id,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta']) // add this
            ->get()
            ->map(function ($video) {
                $finalBeastieScore = $video->final_beastie_score
                    ? round($video->final_beastie_score / 2, 2)
                    : null;
                return [
                    "id" => $video->id,
                    "character_id" => $video->character_id,
                    "video_id" => $video->id,
                    "is_featured" => $video->is_featured,
                    "is_active" => $video->status == 'published', // Assuming 'status' field defines active state
                    "thumbnail_image" => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
                    "title" => $video->title,
                    "description" => $video->description,
                    "type" => $video->type,
                    "video_url" => $video->video_url,
                    "final_beastie_score" => $finalBeastieScore,
                    "created_at" => $video->created_at,
                    "updated_at" => $video->updated_at,
                    "primary_color"    => optional($video->channel)->primary_color,
                    "secondary_color"  => optional($video->channel)->secondary_color,
                    "accent_color"     => optional($video->channel)->accent_color,
                    "background_color" => optional($video->channel)->background_color,
                    "text_color"       => optional($video->channel)->text_color,
                    "hover_color"      => optional($video->channel)->hover_color,
                    "highlight_color"  => optional($video->channel)->highlight_color,
                    "cta"              => optional($video->channel)->cta,
                ];
            });


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
            'channel:id,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta',
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
        // if ($user && $this->hasValidSubscription($user->id)) {
        //     $q->whereIn('type', ['youtube', 'vimeo']); // Paid + free
        // } else {
        //     $q->where('type', 'youtube'); // Free only
        // }


        foreach (['channel_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }

        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });

        $videos = $q->latest()->get();
        // dd($isSubscribed);

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

        // $videoData = $videos->map(function ($video) {
        $videoData = $videos->map(function ($video) use ($isSubscribed) {
            if ($video->relationLoaded('regions')) {
                $video->regions->each->makeHidden(['pivot']);
            }

            $isPaidVideo = in_array($video->type, ['vimeo']);

            $paidFlag = $isPaidVideo;


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
                'paid' => $paidFlag,
                'is_subscribed' => $isSubscribed,
                'primary_color'    => optional($video->channel)->primary_color,
                'secondary_color'  => optional($video->channel)->secondary_color,
                'accent_color'     => optional($video->channel)->accent_color,
                'background_color' => optional($video->channel)->background_color,
                'text_color'       => optional($video->channel)->text_color,
                'hover_color'      => optional($video->channel)->hover_color,
                'highlight_color'  => optional($video->channel)->highlight_color,
                'cta'              => optional($video->channel)->cta,
            ];
        });

        // dd($videoData);
        $bloopers = $character->bloopers()->get()->map(function ($blooper) {
            return [
                'id' => $blooper->id,
                'name' => $blooper->name,
                'description' => $blooper->description,
                'stars' => (int) $blooper->stars,
                'video_url' => $blooper->video ? asset($blooper->video) : null,
                'image_url' => $blooper->image ? asset($blooper->image) : null,
                'created_at' => optional($blooper->created_at)->toDateTimeString(),
                'updated_at' => optional($blooper->updated_at)->toDateTimeString(),
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


        // Check if any video has 'paid' flag set to true
        $paidFlagNew = $videoData->contains(function ($video) {
            return $video['paid'] === true;
        });



        $characterPayload = [
            'id' => $character->id,
            'name' => $character->name,
            'persona' => $character->persona,
            'details' => $character->details,
            'image' => $character->image ? asset($character->image) : null,
            'character_thumbnail_image' => $character->thumbnail_image ? asset($character->thumbnail_image) : null,
            'character_video_url' => $character->video ? asset($character->video) : null,
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
            'paid' => $paidFlagNew,
            'is_subscribed' => $isSubscribed,
            'category' => $character->relationLoaded('category') && $character->category ? [
                'id' => $character->category->id,
                'name' => $character->category->name,
            ] : null,
            'regions' => $character->regions
                ? $character->regions->map(fn($r) => ['id' => $r->id, 'region_code' => $r->region_code])
                : [],
            'videos' => $videoData,
            'bloopers' => $bloopers,
            'character_reviews' => $characterReviews,
            'character_rating_avg' => $characterRatingAvg ? round((float) $characterRatingAvg, 2) : null,
            'character_rating_count' => $characterRatingCount,
            'featured_product_reviews' => $featured_product_reviews,
            'product_reviews' => $product_reviews,
        ];

        return response()->json([
            'status' => true,
            'message' => ($user && $this->hasValidSubscription($user->id))
                ? 'Character and paid videos (including free) fetched successfully'
                : 'Character and free videos fetched successfully (subscription required for more).',
            'data' => $characterPayload,
        ], 200);
    }

    public function getAllProductReviews(Request $request, $region)
    {
        return $this->fetchReviewsByRegion($request, $region, 0); // 0 → non-featured
    }

    public function getAllFeaturedReviews(Request $request, $region)
    {
        return $this->fetchReviewsByRegion($request, $region, 1); // 1 → featured
    }


    /**
     * Helper to fetch product reviews based on region + featured flag
     */
    protected function fetchReviewsByRegion(Request $request, $region, $isFeatured)
    {
        try {
            // Normalize region code
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;
            $isSubscribed = $user && $this->hasValidSubscription($userId);

            $reviews = ProductReview::query()
                ->where('is_featured', $isFeatured)
                ->where('is_active', 1)
                ->with(['video', 'character.regions'])
                ->whereHas('character.regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->get()
                // ->map(function ($review) {
                ->map(function ($review) use ($isSubscribed) {
                    $video = $review->video;

                    $paidFlag = $video && $video->type === 'vimeo';

                    return [
                        "id" => $review->id,
                        "character_id" => $review->character_id,
                        "video_id" => $review->video_id,
                        "review_url" => $review->review_url,
                        "is_featured" => $review->is_featured,
                        "is_active" => $review->is_active,
                        "thumbnail_image" => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
                        "title" => $video?->title,
                        "description" => $video?->description,
                        "type" => $video?->type,
                        "video_url" => $video?->video_url,
                        "paid" => $paidFlag,
                        "is_subscribed" => $isSubscribed,
                        "created_at" => $review->created_at,
                        "updated_at" => $review->updated_at,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => $reviews->isEmpty()
                    ? ($isFeatured ? 'No featured product reviews found' : 'No product reviews found')
                    : ($isFeatured ? 'Featured product reviews fetched successfully' : 'All product reviews fetched successfully'),
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch product reviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function getLatestProductReviews(Request $request, $region)
    // {
    //     return $this->fetchLatestReviewsByRegion($request, $region, 5); // limit 5
    // }
    public function getLatestProductReviews(Request $request, $region)
    {
        // ?limit=6 (omit or <=0 => no limit i.e., return all)
        $limit = $request->has('limit') ? (int) $request->query('limit') : null;
        $limit = ($limit && $limit > 0) ? min($limit, 50) : null; // cap to 50, null = no limit

        return $this->fetchLatestReviewsByRegion($request, $region, $limit);
    }


    // protected function fetchLatestReviewsByRegion(Request $request, $region, $limit = 5)
    protected function fetchLatestReviewsByRegion(Request $request, $region, $limit = null)
    {
        try {
            // Normalize region code
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Get logged-in user and subscription status
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;
            $isSubscribed = $user && $this->hasValidSubscription($userId);

            // $reviews = ProductReview::query()
            $query = ProductReview::query()
                ->where('is_active', 1)
                ->with(['video', 'character.regions'])
                ->whereHas('character.regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                // ->orderBy('created_at', 'desc')
                // ->limit($limit)
                // ->get()
                ->orderByDesc('created_at')
                ->when($limit, fn($q) => $q->limit($limit));
            $reviews = $query->get()
                ->map(function ($review) use ($isSubscribed) {
                    $video = $review->video;

                    $paidFlag = $video && $video->type === 'vimeo';

                    return [
                        "id" => $review->id,
                        "character_id" => $review->character_id,
                        "video_id" => $review->video_id,
                        "review_url" => $review->review_url,
                        "is_featured" => $review->is_featured,
                        "is_active" => $review->is_active,
                        "thumbnail_image" => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
                        "title" => $video?->title,
                        "description" => $video?->description,
                        "type" => $video?->type,
                        "video_url" => $video?->video_url,
                        "paid" => $paidFlag,
                        "is_subscribed" => $isSubscribed,
                        "created_at" => $review->created_at,
                        "updated_at" => $review->updated_at,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => $reviews->isEmpty()
                    ? 'No product reviews found for this region'
                    : 'Latest product reviews fetched successfully',
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch product reviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMostViewedProductReviews(Request $request, $region)
    {
        return $this->fetchMostViewedReviewsByRegion($request, $region, 5); // limit 5
    }

    protected function fetchMostViewedReviewsByRegion(Request $request, $region, $limit = 5)
    {
        try {
            // Normalize region code
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Get logged-in user and subscription status
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;
            $isSubscribed = $user && $this->hasValidSubscription($userId);

            $reviews = ProductReview::query()
                ->where('is_active', 1)
                ->with(['video', 'character.regions'])
                ->whereHas('character.regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->orderBy('views', 'desc') // Most viewed first
                ->limit($limit)
                ->get()
                ->map(function ($review) use ($isSubscribed) {
                    $video = $review->video;

                    $paidFlag = $video && $video->type === 'vimeo';

                    return [
                        "id" => $review->id,
                        "character_id" => $review->character_id,
                        "video_id" => $review->video_id,
                        "review_url" => $review->review_url,
                        "is_featured" => $review->is_featured,
                        "is_active" => $review->is_active,
                        "thumbnail_image" => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
                        "title" => $video?->title,
                        "description" => $video?->description,
                        "type" => $video?->type,
                        "video_url" => $video?->video_url,
                        "paid" => $paidFlag,
                        "is_subscribed" => $isSubscribed,
                        "views" => $review->views,
                        "created_at" => $review->created_at,
                        "updated_at" => $review->updated_at,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => $reviews->isEmpty()
                    ? 'No product reviews found for this region'
                    : 'Most viewed product reviews fetched successfully',
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch product reviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getProductReviewCharacters(Request $request, $region)
    {
        try {
            // Normalize region code
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Get logged-in user and subscription status
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;
            $isSubscribed = $user && $this->hasValidSubscription($userId);

            // Get distinct characters that have product reviews in this region
            $characters = Character::query()
                ->whereHas('productReviews', function ($q) {
                    $q->where('is_active', 1);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->with([
                    'videos' => function ($q) use ($regionCode) {
                        $q->where('status', 'published')
                            ->whereHas('regions', fn($r) => $r->where('region_code', $regionCode));
                    }
                ])
                ->get()
                ->map(function ($character) use ($isSubscribed) {

                    $paidFlag = $character->videos->contains(fn($v) => $v->type === 'vimeo');

                    return [
                        "id" => $character->id,
                        "name" => $character->name,
                        "image" => $character->image ? asset($character->image) : null,
                        "character_page_url_slug" => $character->character_page_url_slug,
                        "persona" => $character->persona,
                        "details" => $character->details,
                        "category_id" => $character->category_id,
                        "paid" => $paidFlag,
                        "is_subscribed" => $isSubscribed,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => $characters->isEmpty()
                    ? 'No characters found for this region'
                    : 'Characters fetched successfully',
                'data' => $characters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch characters',
                'error' => $e->getMessage(),
            ], 500);
        }
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
