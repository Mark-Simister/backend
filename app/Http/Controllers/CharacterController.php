<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Channel;
use App\Models\CharacterRole;
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

