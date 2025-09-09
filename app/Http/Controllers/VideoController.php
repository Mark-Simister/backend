<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Subscription;
use App\Models\Video;
use App\Models\Character;
use App\Models\Channel;
use App\Models\Category;
use App\Models\HighlightTag;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Tag;

class VideoController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:video.view', only: ['index']),
            new Middleware('permission:video.create', only: ['create', 'store']),
            new Middleware('permission:video.edit', only: ['edit', 'update']),
            new Middleware('permission:video.delete', only: ['destroy']),

        ];
    }
    public function index()
    {
        $videos = Video::latest()->get();
        return view('admin.videos.index', compact('videos'));
    }

    public function create()
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        $highlight_tags = HighlightTag::all();
        $regions = \App\Models\Region::where('is_active', 1)->get();
        return view('admin.videos.create', compact('channels', 'characters', 'categories', 'highlight_tags', 'regions'));
    }

    public function store(Request $request)
    {
        // dd($request);
        //  Use Validator instead of request->validate
        $validator = \Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:youtube,vimeo',
            'video_url' => 'required|url',

            // Step 2 fields
            'character_id' => 'nullable|exists:characters,id',
            'channel_id' => 'nullable|exists:channels,id',
            'category_id' => 'nullable|exists:categories,id',
            'access_level' => 'required|in:public,premium,early_access',
            'regions' => 'nullable|array',
            'regions.*' => 'exists:regions,id',

            // Step 3 optional fields
            'affiliate_link' => 'nullable|url',
            'thumbnail_url' => 'nullable',
            // 'thumbnail_url'    => 'nullable|required_without:thumbnail_image|url',
            'thumbnail_image' => 'nullable|image|mimes:jpg,jpeg,png|max:10048',
            // 'thumbnail_image'  => 'nullable|required_without:thumbnail_url|image|mimes:jpg,jpeg,png|max:10048',

            // Step 4 meta fields

            // 'tags' => 'nullable|string',
            // 'tags.*' => 'string',
            'tag_ids' => ['nullable', 'string', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
            'rating_type' => 'required|in:rating,review',
            'sponsorship_type' => 'required|in:sponsored,unsponsored',
            'public_rating' => 'nullable|numeric|min:1|max:5',
            'review_details' => 'nullable|string',
            // 'highlight_tags'   => 'nullable|string', //  fixed
            'highlight_tags' => 'nullable|array',  // Validate as an array
            'highlight_tags.*' => 'exists:highlight_tags,id', // Validate each ID exists in the highlight_tags table

            'auto_tags' => 'nullable|string', //  fixed
            'video_type' => 'required|in:short,full_review,reel,live,compilation',
            'video_platforms' => 'nullable|array',
            'video_platforms.*' => 'string',

            'raw_video_file' => 'nullable|file|mimes:mp4,mov,avi|max:51200',
            'caption_file' => 'nullable|file|mimes:vtt,srt,txt|max:1024',

            'status' => 'required|in:draft,published',
            'is_ai_generated' => 'boolean',
            'is_finalized' => 'boolean',
            'qa_passed' => 'boolean',
            'post_schedule_at' => 'nullable|date',

            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'hashtags' => 'nullable|string', //  fixed
            'cta_text' => 'nullable|string|max:255',
            'og_image_url' => 'nullable|url',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',

            'product_name' => 'nullable|string|max:255',
            'product_asin_sku' => 'nullable|string|max:255',
            'character_score' => 'nullable|numeric',
            'editorial_score' => 'nullable|numeric',
            'final_beastiescore' => 'nullable|string|max:255',
            'product_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10048', // For thumbnail image
        ]);


        // if ($validator->fails()) {
        //     dd($validator->errors()->all());
        // }
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        if ($validated['rating_type'] === 'rating') {
            // Only store public_rating for 'rating' type
            $validated['public_rating'] = $request->input('public_rating');
            $validated['review_details'] = null; // Make sure review_details is null if rating is selected
        } elseif ($validated['rating_type'] === 'review') {
            // Only store review_details for 'review' type
            $validated['review_details'] = $request->input('review_details');
            $validated['public_rating'] = null; // Make sure public_rating is null if review is selected
        }
        $tagIdsCsv = (string) $request->input('tag_ids', '');
        $tagIds = collect(explode(',', $tagIdsCsv))
            ->map(fn($s) => trim($s))
            ->filter()
            ->map(fn($s) => (int) $s)
            ->unique()
            ->values();

        if ($tagIds->isNotEmpty()) {
            $existing = Tag::whereIn('id', $tagIds)->pluck('id');
            $missing = $tagIds->diff($existing);

            if ($missing->isNotEmpty()) {
                return back()
                    ->withErrors(['tag_ids' => 'Unknown tag IDs: ' . $missing->implode(', ')])
                    ->withInput();
            }

            // store clean CSV like "1,2,5"
            $validated['tag_ids'] = $existing->implode(',');
        } else {
            $validated['tag_ids'] = null;
        }


        $highlight_tags = $request->highlight_tags ?? [];
        $auto_tags = $request->auto_tags ? array_map('trim', explode(',', $request->auto_tags)) : [];
        $hashtags = $request->hashtags ? array_map('trim', explode(',', $request->hashtags)) : [];

        // Replace in validated array
        $validated['highlight_tags'] = $highlight_tags;
        $validated['auto_tags'] = $auto_tags;
        $validated['hashtags'] = $hashtags;

        // Normalize post_schedule_at to MySQL DATETIME if provided
        if (!empty($validated['post_schedule_at'])) {
            $validated['post_schedule_at'] = \Carbon\Carbon::parse($validated['post_schedule_at'])->format('Y-m-d H:i:s');
        }

        // dd('Validated Successfully ', $validated);

        //  Handle Thumbnail Upload
        if ($request->hasFile('thumbnail_image')) {
            $destinationPath = public_path('thumbnails');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $filename = time() . '_' . uniqid() . '.' . $request->file('thumbnail_image')->getClientOriginalExtension();
            $request->file('thumbnail_image')->move($destinationPath, $filename);

            $validated['thumbnail_url'] = null;
            $validated['thumbnail_image'] = 'thumbnails/' . $filename;
        } else {
            unset($validated['thumbnail_image']);
        }

        //  Handle raw video upload
        if ($request->hasFile('raw_video_file')) {
            $filename = time() . '_' . uniqid() . '.' . $request->file('raw_video_file')->getClientOriginalExtension();
            $request->file('raw_video_file')->move(public_path('videos/raw'), $filename);
            $validated['raw_video_file'] = 'videos/raw/' . $filename;
        }

        //  Handle caption upload
        if ($request->hasFile('caption_file')) {
            $filename = time() . '_' . uniqid() . '.' . $request->file('caption_file')->getClientOriginalExtension();
            $request->file('caption_file')->move(public_path('videos/captions'), $filename);
            $validated['caption_file'] = 'videos/captions/' . $filename;
        }

        // Handle product_thumbnail upload
        if ($request->hasFile('product_thumbnail')) {
            $destinationPath = public_path('thumbnails');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $thumbnailFilename = time() . '_' . uniqid() . '.' . $request->file('product_thumbnail')->getClientOriginalExtension();
            $request->file('product_thumbnail')->move($destinationPath, $thumbnailFilename);

            $validated['product_thumbnail'] = 'thumbnails/' . $thumbnailFilename;
        }

        // Convert arrays into comma-separated strings (keep tag_ids as-is)
        foreach (['highlight_tags', 'auto_tags', 'hashtags'] as $field) {
            if (isset($validated[$field]) && is_array($validated[$field])) {
                $validated[$field] = implode(',', $validated[$field]);
            }
        }


        // Keep video_platforms as JSON to satisfy DB CHECK constraint
        if (isset($validated['video_platforms']) && is_array($validated['video_platforms'])) {
            // ensure simple array of strings, remove empties/spaces
            $validated['video_platforms'] = json_encode(array_values(array_filter(array_map('trim', $validated['video_platforms']))));
        }

        $video = Video::create($validated);

        // Attach regions if any
        if ($request->has('regions')) {
            $video->regions()->attach($request->regions);
        }

        return redirect()->route('admin.videos.index')->with('success', 'Video created successfully.');
    }

    public function edit(Video $video)
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        $highlight_tags = HighlightTag::all();
        $regions = \App\Models\Region::all();
        $selectedRegions = $video->regions->pluck('id')->toArray();

        // --- normalize video_platforms to array of strings ---
        $selectedPlatforms = [];
        if (!empty($video->video_platforms)) {
            if (is_array($video->video_platforms)) {
                $selectedPlatforms = $video->video_platforms;
            } elseif (is_string($video->video_platforms)) {
                $decoded = json_decode($video->video_platforms, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $selectedPlatforms = $decoded;
                    } elseif (is_string($decoded) && $decoded !== '') {
                        // handles case where DB has JSON-encoded string: "\"YouTube,Instagram\""
                        $selectedPlatforms = array_map('trim', explode(',', $decoded));
                    }
                } else {
                    // CSV fallback
                    $selectedPlatforms = array_map('trim', explode(',', $video->video_platforms));
                }
            }
        }

        // --- normalize highlight_tags to array of IDs (strings/ints) ---
        $videoHighlightTags = [];
        if (!empty($video->highlight_tags)) {
            if (is_array($video->highlight_tags)) {
                $videoHighlightTags = $video->highlight_tags;
            } elseif (is_string($video->highlight_tags)) {
                $decoded = json_decode($video->highlight_tags, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $videoHighlightTags = $decoded;
                    } elseif ($decoded !== null && $decoded !== '') {
                        // DB might have a single json string: "\"7\"" or number: 7
                        $videoHighlightTags = [$decoded];
                    }
                } else {
                    // CSV fallback (e.g. "7" or "1,5,8,9")
                    $videoHighlightTags = array_filter(
                        array_map('trim', explode(',', $video->highlight_tags)),
                        fn($v) => $v !== ''
                    );
                }
            }
        }

        $selectedTags = [];
        if (!empty($video->tag_ids)) {
            $selectedTags = array_filter(
                array_map('trim', explode(',', $video->tag_ids)),
                fn($v) => $v !== ''
            );
        }

        return view('admin.videos.edit', compact(
            'video',
            'channels',
            'characters',
            'categories',
            'highlight_tags',
            'selectedPlatforms',
            'videoHighlightTags',
            'regions',
            'selectedRegions',
            'selectedTags'
        ));
    }

    public function editSeo(Video $video)
    {
        [$channels, $characters, $categories, $highlight_tags, $selectedPlatforms, $videoHighlightTags] =
            $this->prepareEditData($video);

        return view('admin.videos.edit-seo', compact(
            'video',
            'channels',
            'characters',
            'categories',
            'highlight_tags',
            'selectedPlatforms',
            'videoHighlightTags'
        ));
    }

    public function updateSeo(Request $request, Video $video)
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'hashtags' => ['nullable', 'string'], // gets json-encoded by mutator
            'cta_text' => ['nullable', 'string', 'max:255'],
            'og_image_url' => ['nullable', 'string', 'max:255'],
            'open_graph_image' => ['nullable', 'string', 'max:255'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string', 'max:280'],
        ]);

        // Only update SEO fields
        $video->fill($request->only([
            'seo_title',
            'seo_description',
            'hashtags',
            'cta_text',
            'og_image_url',
            'open_graph_image',
            'twitter_title',
            'twitter_description',
        ]));

        $video->save();

        return redirect()
            ->route('admin.videos.edit.seo', $video)
            ->with('success', 'SEO fields updated.');
    }
    private function prepareEditData(Video $video): array
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        $highlight_tags = HighlightTag::all();

        // --- normalize video_platforms to array of strings ---
        $selectedPlatforms = [];
        if (!empty($video->video_platforms)) {
            if (is_array($video->video_platforms)) {
                $selectedPlatforms = $video->video_platforms;
            } elseif (is_string($video->video_platforms)) {
                $decoded = json_decode($video->video_platforms, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $selectedPlatforms = $decoded;
                    } elseif (is_string($decoded) && $decoded !== '') {
                        $selectedPlatforms = array_map('trim', explode(',', $decoded));
                    }
                } else {
                    $selectedPlatforms = array_map('trim', explode(',', $video->video_platforms));
                }
            }
        }

        // --- normalize highlight_tags to array of IDs ---
        $videoHighlightTags = [];
        if (!empty($video->highlight_tags)) {
            if (is_array($video->highlight_tags)) {
                $videoHighlightTags = $video->highlight_tags;
            } elseif (is_string($video->highlight_tags)) {
                $decoded = json_decode($video->highlight_tags, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) {
                        $videoHighlightTags = $decoded;
                    } elseif ($decoded !== null && $decoded !== '') {
                        $videoHighlightTags = [$decoded];
                    }
                } else {
                    $videoHighlightTags = array_filter(
                        array_map('trim', explode(',', $video->highlight_tags)),
                        fn($v) => $v !== ''
                    );
                }
            }
        }

        return [$channels, $characters, $categories, $highlight_tags, $selectedPlatforms, $videoHighlightTags];
    }

    public function editProduct(Video $video)
    {
        [$channels, $characters, $categories, $highlight_tags, $selectedPlatforms, $videoHighlightTags] =
            $this->prepareEditData($video);

        return view('admin.videos.edit-product', compact(
            'video',
            'channels',
            'characters',
            'categories',
            'highlight_tags',
            'selectedPlatforms',
            'videoHighlightTags'
        ));
    }

    public function updateProduct(Request $request, Video $video)
    {
        $validated = $request->validate([
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_asin_sku' => ['nullable', 'string', 'max:255'],
            'public_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_details' => ['nullable', 'string'],
            'character_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'editorial_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'final_beastie_score' => ['nullable', 'numeric', 'min:0', 'max:10'],

            // now validating as an IMAGE (file), not a string
            'product_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
        ]);

        // Update non-file product fields first
        $video->fill($request->only([
            'product_name',
            'product_asin_sku',
            'public_rating',
            'review_details',
            'character_score',
            'editorial_score',
            'final_beastie_score',
        ]));

        // Handle thumbnail upload (save to public/thumbnails and store path e.g. "thumbnails/...")
        if ($request->hasFile('product_thumbnail')) {
            $destinationPath = public_path('thumbnails');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0777, true);
            }

            $file = $request->file('product_thumbnail');
            $thumbnailFilename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $thumbnailFilename);

            // Optionally delete old local file if it was in /public/thumbnails
            if (!empty($video->product_thumbnail)) {
                $old = public_path($video->product_thumbnail);
                if (Str::startsWith($video->product_thumbnail, 'thumbnails/') && File::exists($old)) {
                    File::delete($old);
                }
            }

            $video->product_thumbnail = 'thumbnails/' . $thumbnailFilename;
        }

        $video->save();

        return redirect()
            ->route('admin.videos.edit.product', $video)
            ->with('success', 'Product fields updated.');
    }


    public function update(Request $request, Video $video)
    {
        // Use Validator 
        $validator = \Validator::make($request->all(), [
            // Step 1
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:youtube,vimeo',
            'video_url' => 'required|url',

            // Step 2
            'character_id' => 'nullable|exists:characters,id',
            'channel_id' => 'nullable|exists:channels,id',
            'category_id' => 'nullable|exists:categories,id',
            'access_level' => 'required|in:public,premium,early_access',
            'regions' => 'nullable|array',
            'regions.*' => 'exists:regions,id',

            // Step 3
            'affiliate_link' => 'nullable|url',
            // 'thumbnail_url'    => 'nullable|url|required_without:thumbnail_image', // Required without image
            'thumbnail_url' => 'nullable|url', // Required without image
            // 'thumbnail_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:10048|required_without:thumbnail_url', // Required without URL
            'thumbnail_image' => 'nullable|image|mimes:jpg,jpeg,png|max:10048',

            // Other Fields
            // 'tags' => 'nullable|string',
            // 'tags.*' => 'string',
            'tag_ids' => ['nullable', 'string', 'regex:/^\s*$|^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
            'rating_type' => 'required|in:rating,review',
            'public_rating' => 'nullable|numeric|min:1|max:5',
            'review_details' => 'nullable|string',
            'sponsorship_type' => 'required|in:sponsored,unsponsored',
            'highlight_tags' => 'nullable|array',
            'highlight_tags.*' => 'exists:highlight_tags,id',

            'auto_tags' => 'nullable|string',
            'video_type' => 'required|in:short,full_review,reel,live,compilation',
            'video_platforms' => 'nullable|array',
            'video_platforms.*' => 'string',

            'raw_video_file' => 'nullable|file|mimes:mp4,mov,avi|max:51200',
            'caption_file' => 'nullable|file|mimes:vtt,srt,txt|max:1024',

            'status' => 'required|in:draft,published',
            'is_ai_generated' => 'boolean',
            'is_finalized' => 'boolean',
            'qa_passed' => 'boolean',
            'post_schedule_at' => 'nullable|date',

            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'hashtags' => 'nullable|string',
            'cta_text' => 'nullable|string|max:255',
            'og_image_url' => 'nullable|url',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string|max:500',

            'product_name' => 'nullable|string|max:255',
            'product_asin_sku' => 'nullable|string|max:255',
            'character_score' => 'nullable|numeric',
            'editorial_score' => 'nullable|numeric',
            'final_beastiescore' => 'nullable|string|max:255',
            'product_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $tagIdsCsv = (string) $request->input('tag_ids', '');
        $tagIds = collect(explode(',', $tagIdsCsv))
            ->map(fn($s) => trim($s))
            ->filter()
            ->map(fn($s) => (int) $s)
            ->unique()
            ->values();

        if ($tagIds->isNotEmpty()) {
            $existing = Tag::whereIn('id', $tagIds)->pluck('id');
            $missing = $tagIds->diff($existing);

            if ($missing->isNotEmpty()) {
                return back()
                    ->withErrors(['tag_ids' => 'Unknown tag IDs: ' . $missing->implode(', ')])
                    ->withInput();
            }

            $validated['tag_ids'] = $existing->implode(',');
        } else {
            $validated['tag_ids'] = null;
        }


        if ($validated['rating_type'] === 'rating') {
            $validated['public_rating'] = $request->input('public_rating');
            $validated['review_details'] = null;
        } elseif ($validated['rating_type'] === 'review') {
            $validated['review_details'] = $request->input('review_details');
            $validated['public_rating'] = null;
        }

        $highlight_tags = $request->highlight_tags
            ? $request->highlight_tags
            : [];
        $auto_tags = $request->auto_tags
            ? array_map('trim', explode(',', $request->auto_tags))
            : [];
        $hashtags = $request->hashtags
            ? array_map('trim', explode(',', $request->hashtags))
            : [];

        $validated['highlight_tags'] = $highlight_tags;
        $validated['auto_tags'] = $auto_tags;
        $validated['hashtags'] = $hashtags;

        if (!empty($validated['post_schedule_at'])) {
            $validated['post_schedule_at'] = \Carbon\Carbon::parse($validated['post_schedule_at'])->format('Y-m-d H:i:s');
        }

        // Handle thumbnail logic
        if ($request->hasFile('thumbnail_image')) {
            // If a new thumbnail image is uploaded
            $directory = public_path('thumbnails');
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $file = $request->file('thumbnail_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);

            $validated['thumbnail_image'] = 'thumbnails/' . $filename;
            $validated['thumbnail_url'] = null;
        } elseif ($request->filled('thumbnail_url')) {

            $validated['thumbnail_image'] = null;
        } else {

            if (!$video->thumbnail_image) {
                unset($validated['thumbnail_image']);
            }

            if (!$video->thumbnail_url) {
                unset($validated['thumbnail_url']);
            }
        }

        // Handle product_thumbnail upload
        if ($request->hasFile('product_thumbnail')) {
            $destinationPath = public_path('thumbnails');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $thumbnailFilename = time() . '_' . uniqid() . '.' . $request->file('product_thumbnail')->getClientOriginalExtension();
            $request->file('product_thumbnail')->move($destinationPath, $thumbnailFilename);

            $validated['product_thumbnail'] = 'thumbnails/' . $thumbnailFilename;
        }

        // Handle raw video upload
        if ($request->hasFile('raw_video_file')) {
            $filename = time() . '_' . uniqid() . '.' . $request->file('raw_video_file')->getClientOriginalExtension();
            $request->file('raw_video_file')->move(public_path('videos/raw'), $filename);
            $validated['raw_video_file'] = 'videos/raw/' . $filename;
        }

        // Handle caption upload
        if ($request->hasFile('caption_file')) {
            $filename = time() . '_' . uniqid() . '.' . $request->file('caption_file')->getClientOriginalExtension();
            $request->file('caption_file')->move(public_path('videos/captions'), $filename);
            $validated['caption_file'] = 'videos/captions/' . $filename;
        }

        // Convert arrays into comma-separated strings instead of JSON
        // foreach (['tags', 'highlight_tags', 'auto_tags', 'hashtags'] as $field) {
        //     if (isset($validated[$field]) && is_array($validated[$field])) {
        //         $validated[$field] = implode(',', $validated[$field]);
        //     }
        // }

        // Convert arrays into comma-separated strings (keep tag_ids as-is)
        foreach (['highlight_tags', 'auto_tags', 'hashtags'] as $field) {
            if (isset($validated[$field]) && is_array($validated[$field])) {
                $validated[$field] = implode(',', $validated[$field]);
            }
        }

        // Keep video_platforms as JSON to satisfy DB CHECK constraint
        if (isset($validated['video_platforms']) && is_array($validated['video_platforms'])) {
            $validated['video_platforms'] = json_encode(array_values(array_filter(array_map('trim', $validated['video_platforms']))));
        }

        // Update the video with validated data
        $video->update($validated);
        // Sync regions (replace old ones with the new selection)
        if ($request->has('regions')) {
            $video->regions()->sync($request->regions);
        } else {
            // If none selected, clear all
            $video->regions()->sync([]);
        }

        return redirect()->route('admin.videos.index')->with('success', 'Video updated successfully.');
    }



    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted.');
    }

    // GET /api/videos
    public function index_api(Request $request, $region = null)
    {
        try {
            // 1) Resolve region code
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL']; // include GLOBAL if you use it
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // 2) Build query to fetch videos based on region
            $query = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode); // Filter videos by region code
                })
                ->latest(); // Order by the latest videos

            // Optional: Filter by additional parameters like category_id or channel_id
            if ($request->filled('category_id')) {
                $query->where('category_id', (int) $request->input('category_id'));
            }


            $videos = $query->get();

            // Collect all tag ids from videos
            $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])
                ->filter()
                ->unique();

            // Fetch tag names
            $tagMap = $allTagIds->isNotEmpty()
                ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
                : collect();

            // Map tag names to video tag pairs
            $videos->each(function ($v) use ($tagMap) {
                $ids = collect($v->tag_ids_array ?? []);
                $v->tag_pairs = $ids->map(function ($id) use ($tagMap) {
                    $name = $tagMap->get($id);
                    return $name ? ['id' => $id, 'name' => $name] : null;
                })->filter()->values()->all();
            });

            // Return the videos along with the regions
            $data = $videos->map(function ($video) {

                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

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
                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),
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

                    // Regions
                    'regions' => $video->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Videos fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    public function show_api(Video $video)
    {
        $video->load([
            'reviews:id,video_id,rating',
            'highlightTags:id,label,emoji',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Video fetched successfully',
            'data' => new VideoResource($video),
        ], 200);
    }

    // public function freeVideos()
    // {
    //     $videos = Video::with([
    //         'reviews:id,video_id,rating', // OK
    //         // DO NOT eager-load 'highlightTags' since no pivot table exists
    //     ])
    //         ->where('type', 'youtube')
    //         ->where('status', 'published')
    //         ->latest()
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Free videos fetched successfully',
    //         'data' => VideoResource::collection($videos),
    //     ], 200);
    // }

    // GET /api/videos/free?channel_id=&character_id=&category_id=
//     public function freeVideos(Request $request)
// {
//     // Minimal validation on optional filters
//     $request->validate([
//         'channel_id' => 'sometimes|integer',
//         'character_id' => 'sometimes|integer',
//         'category_id' => 'sometimes|integer',
//     ]);

    //     $q = Video::with(['reviews:id,video_id,rating'])
//         ->where('type', 'youtube')
//         ->where('status', 'published');

    //     // Apply filters if present
//     foreach (['channel_id', 'character_id', 'category_id'] as $f) {
//         if ($request->filled($f)) {
//             $q->where($f, $request->get($f));
//         }
//     }

    //     $videos = $q->latest()->get();
//     // dd($videos);

    //     
//     $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])
//         ->filter()
//         ->unique();

    //     $tagMap = $allTagIds->isNotEmpty()
//         ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
//         : collect();

    //     $videos->each(function ($v) use ($tagMap) {
//         $ids = collect($v->tag_ids_array ?? []);
//        
//         $v->tag_pairs = $ids->map(function ($id) use ($tagMap) {
//             $name = $tagMap->get($id);
//             return $name ? ['id' => $id, 'name' => $name] : null;
//         })->filter()->values()->all();
//     });

    //     return response()->json([
//         'status' => true,
//         'message' => 'Free videos fetched successfully',
//         'data' => VideoResource::collection($videos),
//     ], 200);
// }

    public function freeVideos(Request $request, $region)
    {
        try {
            // Validate optional filters
            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);


            $regionCode = strtoupper($region);


            $allowedRegions = ['AU', 'CA', 'UK', 'US'];

            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }


            $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
                ->where('type', 'youtube')
                ->where('status', 'published');


            foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                if ($request->filled($filter)) {
                    $q->where($filter, $request->get($filter));
                }
            }


            $q->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            });


            $videos = $q->latest()->get();


            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }


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


            $data = $videos->map(function ($video) {

                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

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
                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),
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
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Free videos fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function freeVideosTrending(Request $request, $region)
    {
        try {
            // Validate optional filters
            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);


            $regionCode = strtoupper($region);


            $allowedRegions = ['AU', 'CA', 'UK', 'US'];


            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }


            $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
                ->where('type', 'youtube')
                ->where('status', 'published');


            foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                if ($request->filled($filter)) {
                    $q->where($filter, $request->get($filter));
                }
            }

            $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [3]);


            $q->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            });


            $videos = $q->latest()->get();


            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }


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


            $data = $videos->map(function ($video) {

                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

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

                    'highlight_tags' => $video->highlight_tags,

                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),

                    'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,



                    'regions' => $video->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Free videos fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function freeVideosTopDeals(Request $request, $region)
    {
        try {
            // Validate optional filters
            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);


            $regionCode = strtoupper($region);


            $allowedRegions = ['AU', 'CA', 'UK', 'US'];


            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }


            $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
                ->where('type', 'youtube')
                ->where('status', 'published');


            foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                if ($request->filled($filter)) {
                    $q->where($filter, $request->get($filter));
                }
            }

            $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [2]);


            $q->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            });


            $videos = $q->latest()->get();


            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }


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


            $data = $videos->map(function ($video) {

                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

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

                    'highlight_tags' => $video->highlight_tags,

                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),

                    'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,


                    'regions' => $video->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Free videos fetched successfully',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function charactersFromVideos(Request $request, $region)
    {
        try {

            $request->validate([
                'channel_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);

            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];


            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }


            $videoCharacterIds = Video::query()
                ->select('character_id')
                ->whereNotNull('character_id')
                ->where('type', 'youtube')
                ->where('status', 'published')
                ->when($request->filled('channel_id'), fn($q) => $q->where('channel_id', $request->channel_id))
                ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
                ->whereRaw('FIND_IN_SET(?, highlight_tags)', [3]) // CSV style as in your code
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->distinct();

            // Characters: must be referenced by those videos AND have a character_region match for the same region
            $characters = Character::query()
                ->whereIn('id', $videoCharacterIds)
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'image',
                    'character_page_url_slug',
                    'category_id',
                ])
                ->map(function ($character) {
                    return [
                        'id' => $character->id,
                        'name' => $character->name,
                        'image' => $character->image ? asset($character->image) : null, // full asset URL
                        'character_page_url_slug' => $character->character_page_url_slug,
                        'category_id' => $character->category_id,
                    ];
                });

            if ($characters->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No characters found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Characters fetched successfully',
                'data' => $characters,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch characters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function freeVideosDetail(Request $request, $region, $id)
{
    $regionCode = strtoupper($region);
    $allowedRegions = ['AU', 'CA', 'UK', 'US'];
    if (!in_array($regionCode, $allowedRegions)) {
        $regionCode = 'GLOBAL';
    }

    $video = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
        ->where('type', 'youtube')
        ->where('status', 'published')
        ->where('id', $id)
        ->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        })
        ->first();

    if (!$video) {
        return response()->json([
            'status' => false,
            'message' => 'Video not found for the selected region.',
            'data' => [],
        ], 404);
    }
    $character = Character::find($video->character_id);


    $tagMap = collect();
    $idsForMap = collect($video->tag_ids_array ?? [])->filter()->unique();
    if ($idsForMap->isNotEmpty()) {
        $tagMap = Tag::whereIn('id', $idsForMap)->pluck('name', 'id');
    }
    $video->tag_pairs = collect($video->tag_ids_array ?? [])->map(function ($tid) use ($tagMap) {
        $name = $tagMap->get($tid);
        return $name ? ['id' => $tid, 'name' => $name] : null;
    })->filter()->values()->all();

    if ($video->relationLoaded('regions')) {
        $video->regions->each->makeHidden(['pivot']);
    }

    
    $related = Video::query()
        ->select(['id', 'title', 'thumbnail_image', 'description','product_name', 'product_asin_sku', 'product_thumbnail', 'review_details', 'character_id', 'created_at'])
        ->where('status', 'published')
        ->where('type', 'youtube')
        ->where('character_id', $video->character_id)
        ->where('id', '!=', $video->id)
        ->whereHas('regions', function ($q) use ($regionCode) {
            $q->where('region_code', $regionCode);
        })
        ->orderByDesc('created_at')
        ->limit(12)
        ->get()
        ->map(function ($v) {
            return [
                'id' => $v->id,
                'name' => $v->title, 
                // keeping your exact key "thumnail_image" (misspelling preserved intentionally)
                'thumnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
                'description' => $v->description,
                'product_name' => $v->product_name,
                'product_asin_sku' => $v->product_asin_sku,
                'product_thumbnail' => $v->product_thumbnail ? asset($v->product_thumbnail) : null,
                'review_details' => $v->review_details,
            ];
        })
        ->values();

    $data = [
        'video' => [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'type' => $video->type,
            'video_url' => $video->video_url,
            // 'thumbnail_url' => $video->thumbnail_url,
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
            'created_at' => $video->created_at?->toDateTimeString(),
            'updated_at' => $video->updated_at?->toDateTimeString(),
            'regions' => $video->regions->map(fn($r) => [
                'id' => $r->id,
                'region_code' => $r->region_code,
            ]),
        ],
        'related_products' => $related,
        'character_data' => $character ? $character : null,
    ];

    return response()->json([
        'status' => true,
        'message' => 'Free video detail fetched successfully',
        'data' => $data,
    ], 200);
}





    //     public function paidVideos(Request $request)
// {
//     $user = $request->user(); // comes from auth:api

    //     if (!$user || !$this->hasValidSubscription($user->id)) {
//         return response()->json([
//             'status'  => false,
//             'message' => 'An active subscription is required to view paid videos.',
//         ], 403);
//     }

    //     $videos = Video::with(['reviews:id,video_id,rating'])
//         ->whereIn('type', ['youtube', 'vimeo']) // include both free + paid
//         ->where('status', 'published')
//         ->latest()
//         ->get();

    //     return response()->json([
//         'status'  => true,
//         'message' => 'Paid videos (including free) fetched successfully',
//         'data'    => VideoResource::collection($videos),
//     ], 200);
// }

    // GET /api/videos/paid?channel_id=&character_id=&category_id=
    // Includes free (YouTube) + paid (Vimeo) for users with active subscription
//     public function paidVideos(Request $request)
// {
//     $user = $request->user(); 

    //     $request->validate([
//         'channel_id' => 'sometimes|integer',
//         'character_id' => 'sometimes|integer',
//         'category_id' => 'sometimes|integer',
//     ]);

    //     $q = Video::with(['reviews:id,video_id,rating'])
//         ->where('status', 'published');

    //     if ($this->hasValidSubscription($user->id)) {
//         // active subscription → include free + paid
//         $q->whereIn('type', ['youtube', 'vimeo']);
//     } else {
//         // expired subscription → only free
//         $q->where('type', 'youtube');
//     }

    //     foreach (['channel_id', 'character_id', 'category_id'] as $f) {
//         if ($request->filled($f)) {
//             $q->where($f, $request->get($f));
//         }
//     }

    //     $videos = $q->latest()->get();

    //     // Replicate the tag logic from previous functions
//     $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])
//         ->filter()
//         ->unique();

    //     $tagMap = $allTagIds->isNotEmpty()
//         ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
//         : collect();

    //     $videos->each(function ($v) use ($tagMap) {
//         $ids = collect($v->tag_ids_array ?? []);
//        
//         $v->tag_pairs = $ids->map(function ($id) use ($tagMap) {
//             $name = $tagMap->get($id);
//             return $name ? ['id' => $id, 'name' => $name] : null;
//         })->filter()->values()->all();
//     });

    //     return response()->json([
//         'status' => true,
//         'message' => $this->hasValidSubscription($user->id)
//             ? 'Paid videos (including free) fetched successfully'
//             : 'Your subscription has ended. Showing free videos only.',
//         'data' => VideoResource::collection($videos),
//     ], 200);
// }

    public function paidVideos(Request $request, $region)
    {
        $user = $request->user(); 

        // Validate optional filters
        $request->validate([
            'channel_id' => 'sometimes|integer',
            'character_id' => 'sometimes|integer',
            'category_id' => 'sometimes|integer',
        ]);

       
        $regionCode = strtoupper($region);


        $allowedRegions = ['AU', 'CA', 'UK', 'US'];

        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }



        $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published');


        if ($this->hasValidSubscription($user->id)) {
            $q->whereIn('type', ['youtube', 'vimeo']);
        } else {

            $q->where('type', 'youtube');
        }


        foreach (['channel_id', 'character_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }


        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });


        $videos = $q->latest()->get();


        if ($videos->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No data found for the selected region and filters.',
                'data' => [],
            ], 404);
        }


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


        $data = $videos->map(function ($video) {

            if ($video->relationLoaded('regions')) {
                $video->regions->each->makeHidden(['pivot']);
            }

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
                'created_at' => $video->created_at->toDateTimeString(),
                'updated_at' => $video->updated_at->toDateTimeString(),
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
            ];
        });

        return response()->json([
            'status' => true,
            'message' => $this->hasValidSubscription($user->id)
                ? 'Paid videos (including free) fetched successfully'
                : 'Your subscription has ended. Showing free videos only.',
            'data' => $data,
        ], 200);


    }
    public function paidVideosTrending(Request $request, $region)
    {
        $user = $request->user();


        $request->validate([
            'channel_id' => 'sometimes|integer',
            'character_id' => 'sometimes|integer',
            'category_id' => 'sometimes|integer',
        ]);

        $regionCode = strtoupper($region);


        $allowedRegions = ['AU', 'CA', 'UK', 'US'];

        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }



        $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published');


        if ($this->hasValidSubscription($user->id)) {
            $q->whereIn('type', ['youtube', 'vimeo']);
        } else {

            $q->where('type', 'youtube');
        }


        foreach (['channel_id', 'character_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }

        $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [3]);


        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });


        $videos = $q->latest()->get();


        if ($videos->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No data found for the selected region and filters.',
                'data' => [],
            ], 404);
        }


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


        $data = $videos->map(function ($video) {

            if ($video->relationLoaded('regions')) {
                $video->regions->each->makeHidden(['pivot']);
            }

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
                'created_at' => $video->created_at->toDateTimeString(),
                'updated_at' => $video->updated_at->toDateTimeString(),
                'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
                'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,


                'regions' => $video->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ]),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => $this->hasValidSubscription($user->id)
                ? 'Paid videos (including free) fetched successfully'
                : 'Your subscription has ended. Showing free videos only.',
            'data' => $data,
        ], 200);


    }
    public function paidVideosTopDeals(Request $request, $region)
    {
        $user = $request->user();


        $request->validate([
            'channel_id' => 'sometimes|integer',
            'character_id' => 'sometimes|integer',
            'category_id' => 'sometimes|integer',
        ]);

        $regionCode = strtoupper($region);


        $allowedRegions = ['AU', 'CA', 'UK', 'US'];

        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }




        $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published');


        if ($this->hasValidSubscription($user->id)) {
            $q->whereIn('type', ['youtube', 'vimeo']);
        } else {

            $q->where('type', 'youtube');
        }


        foreach (['channel_id', 'character_id', 'category_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->get($f));
            }
        }

        $q->whereRaw('FIND_IN_SET(?, highlight_tags)', [2]);


        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });


        $videos = $q->latest()->get();


        if ($videos->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No data found for the selected region and filters.',
                'data' => [],
            ], 404);
        }


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


        $data = $videos->map(function ($video) {

            if ($video->relationLoaded('regions')) {
                $video->regions->each->makeHidden(['pivot']);
            }

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
                'created_at' => $video->created_at->toDateTimeString(),
                'updated_at' => $video->updated_at->toDateTimeString(),
                'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
                'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,


                'regions' => $video->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ]),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => $this->hasValidSubscription($user->id)
                ? 'Paid videos (including free) fetched successfully'
                : 'Your subscription has ended. Showing free videos only.',
            'data' => $data,
        ], 200);


    }


    public function charactersFromPaidVideos(Request $request, $region)
    {
        try {
            $user = $request->user();

            $request->validate([
                'channel_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);


            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            $types = $this->hasValidSubscription($user->id)
                ? ['youtube', 'vimeo']
                : ['youtube'];

            $videoCharacterIds = Video::query()
                ->select('character_id')
                ->whereNotNull('character_id')
                ->whereIn('type', $types)
                ->where('status', 'published')
                ->when($request->filled('channel_id'), fn($q) => $q->where('channel_id', $request->channel_id))
                ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
                // match your existing "trending" flag based on highlight_tags CSV
                ->whereRaw('FIND_IN_SET(?, highlight_tags)', [3])
                // video must be available in region
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->distinct();

            $characters = Character::query()
                ->whereIn('id', $videoCharacterIds)
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'image',
                    'character_page_url_slug',
                    'category_id',
                ])
                ->map(function ($character) {
                    return [
                        'id' => $character->id,
                        'name' => $character->name,
                        'image' => $character->image ? asset($character->image) : null, // full URL
                        'character_page_url_slug' => $character->character_page_url_slug,
                        'category_id' => $character->category_id,
                    ];
                });

            if ($characters->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No characters found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => $this->hasValidSubscription($user->id)
                    ? 'Paid & free characters fetched successfully'
                    : 'Your subscription has ended. Showing free characters only.',
                'data' => $characters,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch characters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function paidVideosDetail(Request $request, $region, $id)
    {
        $user = $request->user(); 

       
        $regionCode = strtoupper($region);
        $allowedRegions = ['AU', 'CA', 'UK', 'US'];
        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }

        
        $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published')
            ->where('id', $id);

        
        if ($this->hasValidSubscription($user->id)) {
            $q->whereIn('type', ['youtube', 'vimeo']);
        } else {
            $q->where('type', 'youtube');
        }

        
        $q->whereHas('regions', function ($query) use ($regionCode) {
            $query->where('region_code', $regionCode);
        });

        $video = $q->first();

        if (!$video) {
            return response()->json([
                'status' => false,
                'message' => 'Video not found for the selected region.',
                'data' => [],
            ], 404);
        }

        $character = Character::find($video->character_id);

       
        $tagMap = collect();
        $idsForMap = collect($video->tag_ids_array ?? [])->filter()->unique();
        if ($idsForMap->isNotEmpty()) {
            $tagMap = Tag::whereIn('id', $idsForMap)->pluck('name', 'id');
        }
        $video->tag_pairs = collect($video->tag_ids_array ?? [])->map(function ($tid) use ($tagMap) {
            $name = $tagMap->get($tid);
            return $name ? ['id' => $tid, 'name' => $name] : null;
        })->filter()->values()->all();

        
        if ($video->relationLoaded('regions')) {
            $video->regions->each->makeHidden(['pivot']);
        }
    //     $videos = Video::where('status', 'published')
    // ->where('type', 'vimeo')
    // ->where('character_id', $video->character_id)
    // ->where('id', '!=', $video->id)->get();
        // dd($videos);
        $related = Video::query()
    ->select(['id', 'title', 'thumbnail_image', 'description','product_name', 'product_asin_sku', 'product_thumbnail', 'review_details', 'status', 'type', 'character_id', 'created_at'])
    ->where('status', 'published')
    ->where('type', 'vimeo')
    ->where('character_id', $video->character_id)
    ->where('id', '!=', $video->id)
    // ->whereHas('regions', function ($q) use ($regionCode) {
    //     $q->where('region_code', $regionCode);
    // })
    ->orderByDesc('created_at')
    ->limit(5)
    ->get()
    ->map(function ($v) {
        return [
            'id' => $v->id,
            'name' => $v->title, 
            'thumnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
            'description' => $v->description,
            'product_name' => $v->product_name,
            'product_asin_sku' => $v->product_asin_sku,
            'product_thumbnail' => $v->product_thumbnail ? asset($v->product_thumbnail) : null,
            'review_details' => $v->review_details,
        ];
    })
    ->values();
        
        $data = [
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
            'created_at' => $video->created_at?->toDateTimeString(),
            'updated_at' => $video->updated_at?->toDateTimeString(),
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
            'related_products' => $related,
            'character_data'   => $character ? $character : null,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Video detail fetched successfully',
            'data' => $data,
        ], 200);
    }

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


}