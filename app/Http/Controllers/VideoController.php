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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Tag;
use App\Models\Region;
use App\Models\AffiliateLink;
use App\Models\Comment;
use App\Models\VideoWatchHistory;
use App\Models\CharacterInsight;
use App\Models\ProductReview;
use Illuminate\Support\Facades\DB;
use App\HasSubscriptionSections;

class VideoController extends Controller
{
    use HasSubscriptionSections;
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
    // private function hasValidSubscription(int $userId): bool
    // {
    //     $sub = Subscription::where('user_id', $userId)
    //         ->latest('subscription_end_date')
    //         ->first();


    //     if (!$sub || $sub->trashed()) {
    //         return false;
    //     }

    //     $now = now();

    //     $statusOkay = in_array($sub->subscription_status, ['active', 'trialing'], true);
    //     $notCanceled = $sub->subscription_status !== 'canceled' && is_null($sub->canceled_at);

    //     $withinPaidPeriod = $sub->subscription_end_date && $now->lte($sub->subscription_end_date);
    //     $withinTrial = $sub->trial_end_date && $now->lte($sub->trial_end_date);

    //     $timeOkay = $withinPaidPeriod || $withinTrial;

    //     $paymentOkay = ($sub->payment_status === 'succeeded') || ($sub->subscription_status === 'trialing');
    //     // dd($sub, $statusOkay, $notCanceled, $withinPaidPeriod, $withinTrial, $paymentOkay);

    //     return $statusOkay && $notCanceled && $timeOkay && $paymentOkay;
    // }
    private function hasValidSubscription(int $userId): bool
    {
        $now = now();

        // Pick the most recent VALID row only
        $sub = Subscription::where('user_id', $userId)
            ->whereNull('deleted_at') // if using SoftDeletes; or ->withoutTrashed()
            ->where(function ($q) use ($now) {
                // Still within access window (paid period or trial)
                $q->where(function ($q2) use ($now) {
                    $q2->whereNotNull('subscription_end_date')
                        ->whereDate('subscription_end_date', '>=', $now->toDateString());
                })
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNotNull('trial_end_date')
                            ->whereDate('trial_end_date', '>=', $now->toDateString());
                    });
            })
            ->where(function ($q) {
                // Status/payment must be okay
                $q->whereIn('subscription_status', ['active', 'trialing'])
                    ->where(function ($q2) {
                        // payment succeeded OR trialing (no payment needed yet)
                        $q2->where('payment_status', 'succeeded')
                            ->orWhere('subscription_status', 'trialing');
                    });
            })
            // Being canceled at period end is still valid until the end date
            // Exclude fully canceled that already has canceled_at in the past
            ->where(function ($q) {
                $q->whereNull('canceled_at')
                    ->orWhere('cancel_at_period_end', 1);
            })
            // Resolve ties: same end date exists for many rows -> take newest row
            ->orderByDesc('subscription_end_date')
            ->orderByDesc('id')
            ->first();

        return (bool) $sub;
    }

    private function getSeoData(Video $video, $regionCode)
    {

        $seo = \DB::table('seo_region')
            ->where('video_id', $video->id)
            ->where('region_id', $regionCode === 'GLOBAL' ? 0 : $regionCode)
            ->first();

        if ($seo) {
            return [
                'seo_title' => $seo->seo_title ?? $video->seo_title,
                'seo_description' => $seo->seo_description ?? $video->seo_description,
                'hashtags' => $seo->hashtags ?? $video->hashtags,
                'cta_text' => $seo->cta_text ?? $video->cta_text,
                'og_image_url' => $seo->og_image_url ?? $video->og_image_url,
                'open_graph_image' => $seo->open_graph_image ?? $video->open_graph_image,
                'twitter_title' => $seo->twitter_title ?? $video->twitter_title,
                'twitter_description' => $seo->twitter_description ?? $video->twitter_description,
            ];
        }

        return [
            'seo_title' => $video->seo_title,
            'seo_description' => $video->seo_description,
            'hashtags' => $video->hashtags,
            'cta_text' => $video->cta_text,
            'og_image_url' => $video->og_image_url,
            'open_graph_image' => $video->open_graph_image,
            'twitter_title' => $video->twitter_title,
            'twitter_description' => $video->twitter_description,
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
        $characters = Character::where('public_private_toggle', 0)->get();
        $categories = Category::all();
        $highlight_tags = HighlightTag::all();
        $regions = Region::where('is_active', 1)->get();
        return view('admin.videos.create', compact('channels', 'characters', 'categories', 'highlight_tags', 'regions'));
    }

    public function store(Request $request)
    {
        // dd($request->all());
        //  Use Validator instead of request->validate
        $validator = \Validator::make(
            $request->all(),
            [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'type' => 'required|in:youtube,vimeo',
                'video_url' => 'nullable|url',
                'is_featured' => 'nullable|boolean',

                // Step 2 fields
                'character_id' => 'required|exists:characters,id',
                //'channel_id' => 'nullable|exists:channels,id',
                //'category_id' => 'nullable|exists:categories,id',
                'access_level' => 'required|in:public,premium,early_access',
                'regions' => 'nullable|array',
                'regions.*' => 'exists:regions,id',

                // Step 3 optional fields
                'affiliate_link' => 'nullable|url',
                'thumbnail_url' => 'nullable',
                // 'thumbnail_url'    => 'nullable|required_without:thumbnail_image|url',
                'thumbnail_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
                // 'thumbnail_image'  => 'nullable|required_without:thumbnail_url|image|mimes:jpg,jpeg,png|max:5120',

                // Step 4 meta fields

                // 'tags' => 'nullable|string',
                // 'tags.*' => 'string',
                // 'tag_ids' => ['required', 'string', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
                'tag_ids' => ['required', 'string', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
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
                'character_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
                'editorial_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
                'final_beastie_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
                'product_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120', // For thumbnail image
            ],
            [
                'tag_ids.required' => 'Tags are required.',
            ]
        );


        // if ($validator->fails()) {
        //     dd($validator->errors()->all());
        // }
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        // Handle the 'is_featured' field
        if ($request->has('is_featured')) {
            $validated['is_featured'] = $request->input('is_featured');
        } else {
            $validated['is_featured'] = false; // default to false if not provided
        }

        $character = Character::find($validated['character_id']);

        if ($character) {
            // Get category_id from the character
            $validated['category_id'] = $character->category_id;

            // Get channel_id from the category
            $category = Category::find($validated['category_id']);
            if ($category) {
                $validated['channel_id'] = $category->channel_id;
            }
        }


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


        if (isset($validated['video_platforms']) && is_array($validated['video_platforms'])) {
            $validated['video_platforms'] = json_encode(array_values(array_filter(array_map('trim', $validated['video_platforms']))));
        }

        $video = Video::create($validated);
        // if (!empty($validated['channel_ids'])) {
        //     $video->channel()->sync($validated['channel_ids']);
        // }
        // dd($video);

        // Attach regions if any
        if ($request->has('regions')) {
            $video->regions()->attach($request->regions);
        }

        // return redirect()->route('admin.videos.index')->with('success', 'Video created successfully.');
        $typeFrom = strtolower(trim($request->input('type', $request->query('type', $validated['type'] ?? ''))));
        $urlFrom = trim((string) $request->input('video_url', $request->query('video_url', $validated['video_url'] ?? '')));
        $toVimeo = ($typeFrom === 'vimeo') && ($urlFrom !== '');
        // dd($typeFrom,$urlFrom,$toVimeo);
        return redirect()
            ->route($toVimeo ? 'admin.vimeo.index' : 'admin.videos.index')
            ->with('success', 'Video created successfully.');
    }

    public function getRegions($characterId)
    {
        $character = Character::findOrFail($characterId);
        $regions = $character->regions; // Assuming you have the relationship set up in the Character model
        return response()->json($regions);
    }


    public function edit(Video $video)
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        $highlight_tags = HighlightTag::all();
        $regions = Region::all();
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

        // Selected region IDs for this video
        $selectedRegions = $video->regions->pluck('id')->toArray();

        // Get only active regions that exist in the selectedRegions
        $regions = Region::where('region_name', '!=', 'Global')
            ->where('is_active', 1)
            ->whereIn('id', $selectedRegions) // only existing ones
            ->get();

        // Always prepend Global (id=0)
        $regions->prepend((object) ['id' => 0, 'name' => 'Global']);

        return view('admin.videos.edit-seo', compact(
            'video',
            'channels',
            'characters',
            'categories',
            'highlight_tags',
            'selectedPlatforms',
            'videoHighlightTags',
            'regions',
            'selectedRegions'
        ));
    }


    public function updateSeo(Request $request, Video $video)
    {
        $regionId = $request->input('regions', 0);

        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'hashtags' => ['nullable', 'string', 'max:255'],  // Ensure max length is appropriate
            'cta_text' => ['nullable', 'string', 'max:255'],
            'og_image_url' => ['nullable', 'string', 'max:255'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string', 'max:280'],
        ]);

        // If hashtags is null, set it to an empty string to avoid issues with DB constraints
        if ($validated['hashtags'] === null) {
            $validated['hashtags'] = '';
        }

        if ($regionId == 0) {
            // Update global
            $video->update($validated);
        } else {
            // Update or Insert region SEO
            \DB::table('seo_region')->updateOrInsert(
                ['video_id' => $video->id, 'region_id' => $regionId],
                array_merge($validated, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        return redirect()
            ->route('admin.videos.edit.seo', $video)
            ->with('success', 'SEO fields updated.');
    }

    public function editSimilarProducts($videoId)
    {
        $video = Video::findOrFail($videoId);

        $regions = Region::where('is_active', 1)->get();

        // Default to Global (id=0) if no region selected yet
        $selectedRegionId = 0;

        // Load similar products for selected region (default Global)
        $similarProducts = \DB::table('similar_products')
            ->where('video_id', $video->id)
            ->where('region_id', $selectedRegionId)
            ->get();

        return view('admin.videos.edit-similar-products', compact(
            'video',
            'regions',
            'similarProducts',
            'selectedRegionId'
        ));
    }



    public function getSimilarProductsByRegion($videoId, $regionId)
    {
        $products = \DB::table('similar_products')
            ->where('video_id', $videoId)
            ->where('region_id', $regionId)
            ->get();

        return response()->json($products);
    }



    public function getSeoByRegion(Video $video, $regionId)
    {
        if ($regionId == 0) {
            // Global = from videos table
            return response()->json([
                'seo_title' => $video->seo_title,
                'seo_description' => $video->seo_description,
                'hashtags' => $video->hashtags,
                'cta_text' => $video->cta_text,
                'og_image_url' => $video->og_image_url,
                'twitter_title' => $video->twitter_title,
                'twitter_description' => $video->twitter_description,
            ]);
        }

        $seo = \DB::table('seo_region')
            ->where('video_id', $video->id)
            ->where('region_id', $regionId)
            ->first();

        return response()->json($seo ?: []);
    }

    public function regionData(Video $video, Region $region)
    {
        // Assuming you have a pivot/translation table for SEO fields per region
        $seo = $video->seos()->where('region_id', $region->id)->first();

        return response()->json([
            'seo_title' => $seo->seo_title ?? '',
            'seo_description' => $seo->seo_description ?? '',
            'hashtags' => $seo->hashtags ?? '',
            'cta_text' => $seo->cta_text ?? '',
            'og_image_url' => $seo->og_image_url ?? '',
            'twitter_title' => $seo->twitter_title ?? '',
            'twitter_description' => $seo->twitter_description ?? '',
        ]);
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
        // dd($request->all());
        // Use Validator 
        $validator = \Validator::make(
            $request->all(),
            [
                // Step 1
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'type' => 'required|in:youtube,vimeo',
                'video_url' => 'nullable|url',
                'is_featured' => 'nullable|boolean',

                // Step 2
                'character_id' => 'nullable|exists:characters,id',
                // 'channel_id' => 'nullable|exists:channels,id',
                // 'category_id' => 'nullable|exists:categories,id',
                'access_level' => 'required|in:public,premium,early_access',
                'regions' => 'nullable|array',
                'regions.*' => 'exists:regions,id',

                // Step 3
                'affiliate_link' => 'nullable|url',
                // 'thumbnail_url'    => 'nullable|url|required_without:thumbnail_image', // Required without image
                'thumbnail_url' => 'nullable|url', // Required without image
                // 'thumbnail_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:5120|required_without:thumbnail_url', // Required without URL
                'thumbnail_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',

                // Other Fields
                // 'tags' => 'nullable|string',
                // 'tags.*' => 'string',
                // 'tag_ids' => ['required', 'string', 'regex:/^\s*$|^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
                'tag_ids' => ['required', 'string', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
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
                'final_beastie_score' => 'nullable|string|max:255',
                'product_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120',
            ],
            [
                'tag_ids.required' => 'Tags are required.',
                'tag_ids.regex' => 'Tags must be a comma-separated list of IDs.',
            ]
        );

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        // Handle 'is_featured'
        if ($request->has('is_featured')) {
            $validated['is_featured'] = $request->input('is_featured');
        } else {
            // Keep the existing 'is_featured' value if it's not provided
            $validated['is_featured'] = $video->is_featured ?? false; // Default to 'false' if not present
        }

        if ($validated['character_id']) {
            $character = Character::find($validated['character_id']);
            if ($character) {
                $validated['category_id'] = $character->category_id;

                $category = Category::find($validated['category_id']);
                if ($category) {
                    $validated['channel_id'] = $category->channel_id;
                }
            }
        } else {
            // If character_id is not provided, keep existing category_id and channel_id
            $validated['category_id'] = $video->category_id;
            $validated['channel_id'] = $video->channel_id;
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

        // if (!empty($validated['channel_ids'])) {
        //     $video->channel()->sync($validated['channel_ids']);
        // }

        // return redirect()->route('admin.videos.index')->with('success', 'Video updated successfully.');
        $video->refresh();
        $typeFrom = strtolower(trim($request->input('type', $request->query('type', $video->type ?? ''))));
        $urlFrom = trim((string) $request->input('video_url', $request->query('video_url', $video->video_url ?? '')));
        $toVimeo = ($typeFrom === 'vimeo') && ($urlFrom !== '');
        // dd($toVimeo);
        return redirect()
            ->route($toVimeo ? 'admin.vimeo.index' : 'admin.videos.index')
            ->with('success', 'Video updated successfully.');
    }

    public function toggleFeatured(Request $request)
    {
        $video = Video::find($request->id); // Find the video by ID

        if ($video) {
            // Toggle the is_featured status
            $video->is_featured = $request->is_featured;
            $video->save(); // Save the updated status

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }




    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted.');
    }

    public function showCommentsPage($id)
    {
        $video = Video::with(['comments.user', 'comments.replies.user'])
            ->where('id', $id)
            ->first();

        if (!$video) {
            return redirect()->route('admin.videos.index')->with('error', 'Video not found.');
        }

        // Paginate comments if the data is large
        $comments = $video->comments()->with(['user', 'replies.user'])->paginate(10); // Pagination added



        return view('admin.videos.comments', [
            'video' => $video,
            'comments' => $comments, // Pass paginated comments
        ]);
    }


    public function destroy_comment($commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $comment->replies()->delete();

        $comment->delete();

        return redirect()->back()->with('success', 'Comment and its replies deleted successfully.');
    }
    public function update_comment(Request $request, $commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update(['body' => $data['body']]);

        return redirect()->back()->with('success', 'Comment updated successfully.');
    }

    public function deleteReply($id)
    {
        // dd($id);
        // Find the reply by ID
        $reply = Comment::find($id);

        if (!$reply) {
            return response()->json([
                'status' => false,
                'message' => 'Reply not found.',
                'data' => []
            ], 404);
        }

        $reply->delete();

        return redirect()->back()->with('success', 'Reply deleted successfully.');
    }



    // Show form for managing affiliate links for a video
    public function manageLinks($videoId)
    {
        $video = Video::findOrFail($videoId);
        $regions = Region::all();  // Get all regions
        $affiliateLinks = AffiliateLink::where('video_id', $videoId)->get();  // Get all affiliate links for this video

        return view('admin.videos.manage_affiliate_links', compact('video', 'regions', 'affiliateLinks'));
    }

    // Store a new affiliate link
    public function storeLink(Request $request, $videoId)
    {
        $request->validate([
            'region_id' => 'required|exists:regions,id',
            'retailer' => 'required|string',
            'url' => 'required|url',
        ]);

        AffiliateLink::create([
            'video_id' => $videoId,
            'region_id' => $request->region_id,
            'retailer' => $request->retailer,
            'url' => $request->url,
        ]);

        return redirect()->route('admin.videos.affiliate-links', $videoId)->with('success', 'Affiliate link added successfully.');
    }

    public function editAffiliateLink($videoId, $affiliateLinkId)
    {
        $video = Video::findOrFail($videoId);
        $affiliateLink = AffiliateLink::findOrFail($affiliateLinkId);
        $regions = Region::all();  // Assuming you have a Region model

        return view('admin.videos.edit-affiliate-link', compact('video', 'affiliateLink', 'regions'));
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
                    'video_url' => $video->video_url ?? '',
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
                    'video_url' => $video->video_url ?? '',
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
                    'video_url' => $video->video_url ?? '',
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

    public function trendingVideos(Request $request, $region)
    {
        try {
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;

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

            // Build the base query using the helper method from the trait
            // $q = $this->buildVideosQueryWithoutSubscription($request, $regionCode, 3);
            $q = $this->buildVideosQueryWithoutSubscription($request, $regionCode, 3)
                ->with([
                    'regions:id,region_code',
                    'channel:id,name,image,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta',
                ]);

            $limit = (int) $request->query('limit', 0);
            if ($limit > 0) {
                $q->limit($limit);
            }

            $videos = $q->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            $this->attachTagPairs($videos);

            $isPaidUser = $user && $this->hasValidSubscription($userId);

            $data = $videos->map(function ($video) use ($isPaidUser) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }
                $ch = $video->relationLoaded('channel') ? $video->channel : null;
                $channelPayload = $ch ? [
                    'id'               => $ch->id,
                    'name'             => $ch->name,
                    'image_url'        => $ch->image ? asset($ch->image) : null,
                    'primary_color'    => $ch->primary_color,
                    'secondary_color'  => $ch->secondary_color,
                    'accent_color'     => $ch->accent_color,
                    'background_color' => $ch->background_color,
                    'text_color'       => $ch->text_color,
                    'hover_color'      => $ch->hover_color,
                    'highlight_color'  => $ch->highlight_color,
                    'cta'              => $ch->cta,
                ] : null;


                $isPaidVideo = in_array($video->type, ['vimeo']);

                $paidFlag = $isPaidVideo;


                $isSubscribed = $isPaidUser;

                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }

                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
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
                    'paid' => $paidFlag,  // Set the 'paid' flag 
                    'is_subscribed' => $isSubscribed,  // 'is_subscribed' indicating whether the user has a valid subscription
                    'final_beastie_score' => $finalBeastieScore,
                    'channel' => $channelPayload,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Trending videos fetched successfully',
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
    public function latestVideos(Request $request, $region)
    {
        // dd($request, $region);
        try {
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;

            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
            ]);

            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Build the base query using the helper method from the trait
            $q = $this->buildVideosQueryWithoutSubscription2($request, $regionCode);

            $videos = $q->get();
            $videos = $q->limit(6)->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            $this->attachTagPairs($videos);

            $isPaidUser = $user && $this->hasValidSubscription($userId);

            $data = $videos->map(function ($video) use ($isPaidUser) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

                $isPaidVideo = in_array($video->type, ['vimeo']);
                $paidFlag = $isPaidVideo;
                $isSubscribed = $isPaidUser;

                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }

                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
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
                    'paid' => $paidFlag,
                    'is_subscribed' => $isSubscribed,
                    'final_beastie_score' => $finalBeastieScore,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Latest videos fetched successfully',
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

    public function hotThisWeek(Request $request, $region)
    {
        try {
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;
            
            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);
            
            // Normalize region
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }
            
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();
            
            $q = Video::with([
                 'channel:id,name,image,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta,channel_category',
                 'reviews:id,video_id,rating', 'regions:id,region_code'])
            ->where('status', 'published')
            ->whereHas('regions', function ($query) use ($regionCode) {
                    $query->where('region_code', $regionCode);
                })
                ->withCount([
                    'watchHistories as weekly_views' => function ($query) use ($startOfWeek, $endOfWeek) {
                        $query->whereBetween('watched_at', [$startOfWeek, $endOfWeek]);
                    }
                ]);
                
                foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                    if ($request->filled($filter)) {
                        $q->where($filter, $request->get($filter));
                    }
                }
                
                // Always order by weekly_views first, then total watch count
                $videos = $q->orderByDesc('weekly_views')
                ->orderByDesc('watch')
                ->limit(10)
                ->get();
                
            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No videos found for this region and filters.',
                    'data' => [],
                ], 404);
            }

            // Collect tag IDs
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

            $data = $videos->map(function ($video) use ($userId) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }
                $ch = $video->relationLoaded('channel') ? $video->channel : null;
                $channelPayload = $ch ? [
                    'id'               => $ch->id,
                    'name'             => $ch->name,
                    'image_url'        => $ch->image ? asset($ch->image) : null,
                    'primary_color'    => $ch->primary_color,
                    'secondary_color'  => $ch->secondary_color,
                    'accent_color'     => $ch->accent_color,
                    'background_color' => $ch->background_color,
                    'text_color'       => $ch->text_color,
                    'hover_color'      => $ch->hover_color,
                    'highlight_color'  => $ch->highlight_color,
                    'cta'              => $ch->cta,
                    'channel_category' => $ch->channel_category,
                ] : null;

                $isPaidVideo = in_array($video->type, ['vimeo']);
                $isPaidUser = $userId && $this->hasValidSubscription($userId);
                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
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
                    'paid' => $isPaidVideo,
                    'is_subscribed' => $isPaidUser,
                    'views' => $video->watch ?? 0,
                    'weekly_views' => $video->weekly_views ?? 0,
                    'final_beastie_score' => $finalBeastieScore,
                    'channel' => $channelPayload,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Hot This Week videos fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage(),
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
                    'video_url' => $video->video_url ?? '',
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


    public function topDeals(Request $request, $region)
    {
        try {
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;

            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);

            // Resolve the region
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            $q = Video::with([
                'reviews:id,video_id,rating',
                'regions:id,region_code',
                'channel:id,name,image,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta'
            ])
                ->where('status', 'published')
                ->whereRaw('FIND_IN_SET(?, highlight_tags)', [2]); // Highlight tag for "Top Deals"

            foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                if ($request->filled($filter)) {
                    $q->where($filter, $request->get($filter));
                }
            }

            // Region filter
            $q->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            });

            // Fetch videos
            // $videos = $q->latest()->get();
            $videos = $q->latest('updated_at')->orderByDesc('id')->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            // Add tag pairs for the videos
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


            $data = $videos->map(function ($video) use ($userId) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

                $ch = $video->relationLoaded('channel') ? $video->channel : null;
                $channelPayload = $ch ? [
                    'id'               => $ch->id,
                    'name'             => $ch->name,
                    'image_url'        => $ch->image ? asset($ch->image) : null,
                    'primary_color'    => $ch->primary_color,
                    'secondary_color'  => $ch->secondary_color,
                    'accent_color'     => $ch->accent_color,
                    'background_color' => $ch->background_color,
                    'text_color'       => $ch->text_color,
                    'hover_color'      => $ch->hover_color,
                    'highlight_color'  => $ch->highlight_color,
                    'cta'              => $ch->cta,
                ] : null;



                $isPaidVideo = in_array($video->type, ['vimeo']);
                $isPaidUser = $userId && $this->hasValidSubscription($userId);
                // dd($isPaidVideo);
                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }



                $paidFlag = $isPaidVideo;

                $isSubscribed = $isPaidUser;
                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }

                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
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
                    'paid' => $paidFlag,
                    'is_subscribed' => $isSubscribed,
                    'final_beastie_score' => $finalBeastieScore,
                    'channel' => $channelPayload,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Top deals fetched successfully',
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
    public function topRatedProducts(Request $request, $region)
    {
        try {
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;

            $request->validate([
                'channel_id' => 'sometimes|integer',
                'character_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);

            // Resolve the region
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            $q = Video::with(['reviews:id,video_id,rating', 'regions:id,region_code'])
                ->where('status', 'published')
                ->whereRaw('FIND_IN_SET(?, highlight_tags)', [2]); // Highlight tag for "Top Deals"

            foreach (['channel_id', 'character_id', 'category_id'] as $filter) {
                if ($request->filled($filter)) {
                    $q->where($filter, $request->get($filter));
                }
            }

            // Region filter
            $q->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            });

            // Fetch videos
            // $videos = $q->latest()->get();
            $videos = $q->latest('updated_at')->orderByDesc('id')->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            // Add tag pairs for the videos
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


            $data = $videos->map(function ($video) use ($userId) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }


                $isPaidVideo = in_array($video->type, ['vimeo']);
                $isPaidUser = $userId && $this->hasValidSubscription($userId);
                // dd($isPaidVideo);
                $finalBeastieScore = null;
                if (!is_null($video->final_beastie_score)) {
                    $finalBeastieScore = round($video->final_beastie_score / 2, 1);
                }



                $paidFlag = $isPaidVideo;

                $isSubscribed = $isPaidUser;

                return [
                    'id' => $video->id,
                    'product_name' => $video->product_name,
                    'review_details' => $video->review_details,
                    'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
                    'product_asin_sku' => $video->product_asin_sku,
                    'public_rating' => $video->public_rating,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
                    'character_id' => $video->character_id,
                    'channel_id' => $video->channel_id,
                    'category_id' => $video->category_id,
                    'access_level' => $video->access_level,
                    'affiliate_link' => $video->affiliate_link,
                    'tags' => $video->tag_pairs,
                    'highlight_tags' => $video->highlight_tags,
                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),
                    'regions' => $video->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'paid' => $paidFlag,
                    'is_subscribed' => $isSubscribed,
                    'final_beastie_score' => $finalBeastieScore,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Top deals fetched successfully',
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

    public function charactersFromVideosAndPaid(Request $request, $region)
    {
        try {
            // Get the logged-in user (if available)
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $userId = $user?->id;

            // Validate the request parameters
            $request->validate([
                'channel_id' => 'sometimes|integer',
                'category_id' => 'sometimes|integer',
            ]);

            // Normalize region code
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Get the IDs of characters associated with videos
            $videoCharacterIds = Video::query()
                ->select('character_id')
                ->whereNotNull('character_id')
                ->where('status', 'published')
                ->when($request->filled('channel_id'), fn($q) => $q->where('channel_id', $request->channel_id))
                ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
                ->whereRaw('FIND_IN_SET(?, highlight_tags)', [3])  // Trending flag
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->distinct();

            // Fetch the characters associated with the video character IDs
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
                        'image' => $character->image ? asset($character->image) : null,  // Full URL
                        'character_page_url_slug' => $character->character_page_url_slug,
                        'category_id' => $character->category_id,
                    ];
                });

            // If no characters are found, return a response
            if ($characters->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No characters found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            $isSubscribed = $user && $this->hasValidSubscription($userId);


            $charactersWithVideos = $characters->map(function ($character) use ($regionCode, $isSubscribed) {
                // Get videos associated with the character
                $videos = Video::query()
                    ->where('character_id', $character['id'])
                    ->where('status', 'published')
                    ->whereHas('regions', function ($q) use ($regionCode) {
                        $q->where('region_code', $regionCode);
                    })
                    ->get([
                        'id',
                        'title',
                        'type',
                        'video_url',
                        'category_id',
                    ]);

                $paidFlag = $videos->contains(function ($video) {
                    return $video->type === 'vimeo';
                });

                return array_merge($character, [
                    // 'videos' => $videos, 
                    'paid' => $paidFlag,
                    'is_subscribed' => $isSubscribed,
                ]);
            });

            return response()->json([
                'status' => true,
                'message' => $isSubscribed
                    ? 'Paid & free characters fetched successfully'
                    : 'Your subscription has ended. Showing free characters only.',
                'data' => $charactersWithVideos,
            ]);
        } catch (\Exception $e) {
            // Return an error response if an exception occurs
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

        $liked = false;
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }
        if ($user) {
            $liked = $user->likedVideos()->where('video_id', $video->id)->exists();
        }

        // Find the region ID based on the regionCode
        $regionModel = Region::where('region_code', $regionCode)->first();
        $regionId = $regionModel ? $regionModel->id : 0;

        // Fetch SEO data using the regionId
        $seoData = $this->getSeoData($video, $regionId);

        $affiliateLinks = AffiliateLink::where('video_id', $video->id)
            ->where('region_id', $regionId)
            ->get();

        $affiliateLinksData = $affiliateLinks->map(function ($link) {
            // Extract the retailer name by splitting the string at the first underscore
            $retailerName = explode('_', $link->retailer)[0];

            return [
                'region_id' => $link->region_id,
                'retailer' => $retailerName,
                'url' => $link->url,
            ];
        });


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
            ->select(['id', 'title', 'thumbnail_image', 'description', 'product_name', 'product_asin_sku', 'product_thumbnail', 'review_details', 'character_id', 'created_at'])
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
                    'thumnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
                    'description' => $v->description,
                    'product_name' => $v->product_name,
                    'product_asin_sku' => $v->product_asin_sku,
                    'product_thumbnail' => $v->product_thumbnail ? asset($v->product_thumbnail) : null,
                    'review_details' => $v->review_details,
                ];
            })
            ->values();
        $comments = $video->comments()
            ->with(['user', 'replies.user'])
            ->withCount('replies')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'video_id' => $comment->video_id,
                    'user_id' => $comment->user_id,
                    'name' => $comment->user->name ?? null,
                    'email' => $comment->user->email ?? null,
                    'profile_image' => $comment->user->profile_image ?? null,
                    'parent_id' => $comment->parent_id,
                    'body' => $comment->body,
                    'replies_count' => $comment->replies_count,
                    'created_at' => $comment->created_at->toISOString(),
                    'updated_at' => $comment->updated_at->toISOString(),
                    'replies' => $comment->replies->isEmpty() ? [] : $comment->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'user_id' => $reply->user_id,
                            'name' => $reply->user->name ?? null,
                            'email' => $reply->user->email ?? null,
                            'profile_image' => $reply->user->profile_image ?? null,
                            'parent_id' => $reply->parent_id,
                            'body' => $reply->body,
                            'created_at' => $reply->created_at->toISOString(),
                            'updated_at' => $reply->updated_at->toISOString(),
                        ];
                    }),
                ];
            });


        $data = [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'type' => $video->type,
                'video_url' => $video->video_url ?? '',
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
                'liked' => $liked,
                // 'seo_title' => $video->seo_title,
                // 'seo_description' => $video->seo_description,
                // 'hashtags' => $video->hashtags,
                // 'cta_text' => $video->cta_text,
                // 'og_image_url' => $video->og_image_url,
                // 'open_graph_image' => $video->open_graph_image,
                // 'twitter_title' => $video->twitter_title,
                // 'twitter_description' => $video->twitter_description,
                'seo_title' => $seoData['seo_title'],
                'seo_description' => $seoData['seo_description'],
                'hashtags' => $seoData['hashtags'],
                'cta_text' => $seoData['cta_text'],
                'og_image_url' => $seoData['og_image_url'],
                'open_graph_image' => $seoData['open_graph_image'],
                'twitter_title' => $seoData['twitter_title'],
                'twitter_description' => $seoData['twitter_description'],
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
                'comments' => $comments,
                'affiliate_links' => $affiliateLinksData,
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


    public function allVideosDetail(Request $request, $region, $id)
    {
        // try {

        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }
        $userId = $user?->id;
        $isPaidUser = $user && $this->hasValidSubscription($userId);
        // dd($user,$userId,$isPaidUser);

        $regionCode = strtoupper($region);
        $allowedRegions = ['AU', 'CA', 'UK', 'US'];
        if (!in_array($regionCode, $allowedRegions)) {
            $regionCode = 'GLOBAL';
        }

        $video = Video::with([
            'reviews:id,video_id,rating',
            'regions:id,region_code',
            'channel:id,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta',
        ])
            ->where('status', 'published')

            ->where('id', $id)
            ->whereHas('regions', function ($query) use ($regionCode) {
                $query->where('region_code', $regionCode);
            })
            ->first();

        // dd($region, $id,$isPaidUser, $userId, $allowedRegions, $regionCode, $video);
        if (!$video) {
            return response()->json([
                'status' => false,
                'message' => 'Video not found for the selected region.',
                'data' => [],
            ], 404);
        }

        $characterInsights = CharacterInsight::where('video_id', $video->id)
            ->get()
            ->map(function ($insight) {
                return [
                    'id' => $insight->id,
                    'title' => $insight->title,
                    'short_description' => $insight->short_description,
                    'image' => $insight->character_insight_image ? asset($insight->character_insight_image) : null,
                    'created_at' => $insight->created_at?->toDateTimeString(),
                    'updated_at' => $insight->updated_at?->toDateTimeString(),
                ];
            });




        $isPaidVideo = in_array($video->type, ['vimeo']);

        $paidFlag = $isPaidVideo;

        $isSubscribed = $isPaidUser;

        $character = Character::find($video->character_id);
        $characterData = $character ? $character->toArray() : null;
        if ($characterData) {
            // Apply asset() to image and video fields
            $characterData['image'] = $character->image ? asset($character->image) : null;
            $characterData['video'] = $character->video ? asset($character->video) : null;
        }

        $liked = false;
        if ($user) {
            $liked = $user->likedVideos()->where('video_id', $video->id)->exists();
        }

        $isAuthenticated = $user !== null;
        $userId = $isAuthenticated ? $user->id : null;

        // Fetch other videos with the same character_id, excluding the current video
        $moreVideos = Video::where('status', 'published')
            ->where('character_id', $video->character_id)
            ->where('id', '!=', $video->id)
            ->orderByDesc('created_at')
            ->limit(2)
            ->get()
            ->map(function ($v) {
                $finalBeastieScore = $v->final_beastie_score
                    ? round($v->final_beastie_score / 2, 2)
                    : null;
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'description' => $v->description,
                    'thumbnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
                    'final_beastie_score' => $finalBeastieScore,
                ];
            });


        // Fetch review by this character for this video first
        $videoReview = ProductReview::where('character_id', $video->character_id)
            ->where('video_id', $video->id)
            ->where('is_active', 1)
            ->with('video')
            ->first();

        // Fetch other reviews by this character excluding this video
        $otherReviews = ProductReview::where('character_id', $video->character_id)
            ->where('video_id', '!=', $video->id)
            ->where('is_active', 1)
            ->with('video')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Merge reviews: video-specific review first, then others
        $mergedReviews = collect([]);
        if ($videoReview) {
            $mergedReviews->push($videoReview);
        }

        // Add other reviews up to remaining slots (total 5)
        $remainingSlots = 5 - $mergedReviews->count();
        if ($remainingSlots > 0) {
            $mergedReviews = $mergedReviews->merge($otherReviews->take($remainingSlots));
        }

        // Map to response format
        // $moreReviewsData = $mergedReviews->map(function ($review) {
        //     $video = $review->video;
        //     $paidFlag = $video && $video->type === 'vimeo';

        //     return [
        //         'id' => $review->id,
        //         'character_id' => $review->character_id,
        //         'video_id' => $review->video_id,
        //         'review_url' => $review->review_url ?? null,
        //         'is_featured' => $review->is_featured ?? null,
        //         'is_active' => $review->is_active ?? null,
        //         'views' => $review->views ?? 0,
        //         'thumbnail_image' => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
        //         'title' => $video?->title ?? null,
        //         'description' => $video?->description ?? null,
        //         'type' => $video?->type ?? null,
        //         'video_url' => $video?->video_url ?? null,
        //         'paid' => $paidFlag,
        //         'created_at' => $review->created_at?->toDateTimeString(),
        //         'updated_at' => $review->updated_at?->toDateTimeString(),
        //     ];
        // });


        $watchHistory = $isAuthenticated ? VideoWatchHistory::where('user_id', $userId)
            ->where('video_id', $video->id)
            ->first() : null;
        $lastPositionSeconds = $watchHistory ? $watchHistory->last_position_seconds : 0;
        $isCompleted = $watchHistory ? $watchHistory->is_completed : false;
        $watchedAt = $watchHistory ? $watchHistory->watched_at : null;

        $regionModel = Region::where('region_code', $regionCode)->first();
        $regionId = $regionModel ? $regionModel->id : 0;

        $seoData = $this->getSeoData($video, $regionId);

        $affiliateLinks = AffiliateLink::where('video_id', $video->id)
            ->where('region_id', $regionId)
            ->get();

        $affiliateLinksData = $affiliateLinks->map(function ($link) {
            $retailerName = explode('_', $link->retailer)[0];
            return [
                'region_id' => $link->region_id,
                'retailer' => $retailerName,
                'url' => $link->url,
            ];
        });

        // Fetch Local Available Products based on the region
        $localProducts = \DB::table('similar_products')
            ->where('video_id', $video->id)
            ->where('region_id', $regionId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'short_description' => $p->short_description,
                    'url' => $p->url,
                    'image' => $p->image ? asset($p->image) : null,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ];
            });

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
            ->select(['id', 'title', 'thumbnail_image', 'description', 'product_name', 'product_asin_sku', 'product_thumbnail', 'review_details', 'character_id', 'final_beastie_score', 'created_at'])
            ->where('status', 'published')
            ->where('type', 'youtube') // keep as-is; change/remove if you want paid types to show here too
            ->where('character_id', $video->character_id)
            ->where('id', '!=', $video->id)
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(function ($v) {
                $finalBeastieScore = $v->final_beastie_score
                    ? round($v->final_beastie_score / 2, 2)
                    : null;
                return [
                    'id' => $v->id,
                    'name' => $v->title,
                    'thumnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
                    'description' => $v->description,
                    'product_name' => $v->product_name,
                    'product_asin_sku' => $v->product_asin_sku,
                    'product_thumbnail' => $v->product_thumbnail ? asset($v->product_thumbnail) : null,
                    'review_details' => $v->review_details,
                    'final_beastie_score' => $finalBeastieScore,
                ];
            })
            ->values();

        $comments = $video->comments()
            ->with(['user', 'replies.user'])
            ->withCount('replies')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'video_id' => $comment->video_id,
                    'user_id' => $comment->user_id,
                    'name' => $comment->user->name ?? null,
                    'email' => $comment->user->email ?? null,
                    'profile_image' => $comment->user->profile_image ?? null,
                    'parent_id' => $comment->parent_id,
                    'body' => $comment->body,
                    'replies_count' => $comment->replies_count,
                    'created_at' => $comment->created_at->toISOString(),
                    'updated_at' => $comment->updated_at->toISOString(),
                    'replies' => $comment->replies->isEmpty() ? [] : $comment->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'user_id' => $reply->user_id,
                            'name' => $reply->user->name ?? null,
                            'email' => $reply->user->email ?? null,
                            'profile_image' => $reply->user->profile_image ?? null,
                            'parent_id' => $reply->parent_id,
                            'body' => $reply->body,
                            'created_at' => $reply->created_at->toISOString(),
                            'updated_at' => $reply->updated_at->toISOString(),
                        ];
                    }),
                ];
            });
        $resolvedTagIds = $idsForMap->values()->all();
        // --- Highlight Tags enrichment ---
        $highlightIds = collect(
            is_array($video->highlight_tags)
                ? $video->highlight_tags
                : ($video->highlight_tags ? explode(',', (string) $video->highlight_tags) : [])
        )
            ->map(fn($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->values();

        $highlightTagDetails = $highlightIds->isNotEmpty()
            ? HighlightTag::whereIn('id', $highlightIds)->get(['id', 'label', 'emoji'])->map(function ($ht) {
                return [
                    'id'    => $ht->id,
                    'label' => $ht->label,
                    'emoji' => $ht->emoji ? asset($ht->emoji) : null, // image URL
                ];
            })->values()
            : collect();

        $data = [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'type' => $video->type,
                'video_url' => $video->video_url ?? '',
                // 'thumbnail_url'      => $video->thumbnail_url,
                'character_id' => $video->character_id,
                'channel_id' => $video->channel_id,
                'category_id' => $video->category_id,
                'access_level' => $video->access_level,
                'affiliate_link' => $video->affiliate_link,
                // 'tags' => $video->tag_pairs,
                'rating_type' => $video->rating_type,
                'sponsorship_type' => $video->sponsorship_type,
                // 'highlight_tags' => $video->highlight_tags,
                // 'tags' => $video->tag_pairs,                 
                'tags'        => $video->tag_pairs,
                'tag_ids'     => $resolvedTagIds,
                // Highlight tags with label + emoji(image url)
                'highlight_tag_ids' => $highlightIds,
                'highlight_tags'    => $highlightTagDetails,
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
                // 'tag_ids' => $video->tag_ids,
                'is_ai_generated' => $video->is_ai_generated,
                'is_finalized' => $video->is_finalized,
                'qa_passed' => $video->qa_passed,
                'post_schedule_at' => $video->post_schedule_at,
                'review_type' => $video->review_type,
                'sponsored' => $video->sponsored,
                'liked' => $liked,
                // SEO block
                'seo_title' => $seoData['seo_title'],
                'seo_description' => $seoData['seo_description'],
                'hashtags' => $seoData['hashtags'],
                'cta_text' => $seoData['cta_text'],
                'og_image_url' => $seoData['og_image_url'],
                'open_graph_image' => $seoData['open_graph_image'],
                'twitter_title' => $seoData['twitter_title'],
                'twitter_description' => $seoData['twitter_description'],
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
                'comments' => $comments,
                'affiliate_links' => $affiliateLinksData,

                // NEW FLAGS
                'paid' => $paidFlag,       // true only if (video is paid) AND (user subscribed)
                'is_subscribed' => $isSubscribed,   // user subscription flag

                // Watch History
                'last_position_seconds' => $lastPositionSeconds,
                'is_completed' => $isCompleted,
                'watched_at' => $watchedAt,

                'primary_color'    => optional($video->channel)->primary_color,
                'secondary_color'  => optional($video->channel)->secondary_color,
                'accent_color'     => optional($video->channel)->accent_color,
                'background_color' => optional($video->channel)->background_color,
                'text_color'       => optional($video->channel)->text_color,
                'hover_color'      => optional($video->channel)->hover_color,
                'highlight_color'  => optional($video->channel)->highlight_color,
                'cta'              => optional($video->channel)->cta,

            ],
            'related_products' => $related,
            'local_available_products' => $localProducts,
            'character_data' => $characterData,
            'character_insights' => $characterInsights,
            // 'more_reviews' => $moreReviewsData,
            'character_more_videos' => $moreVideos,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Video detail fetched successfully',
            'data' => $data,
        ], 200);

        // } catch (\Exception $e) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Failed to fetch video detail',
        //         'error' => $e->getMessage(),
        //     ], 500);
        // }
    }


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
                'video_url' => $video->video_url ?? '',
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
                'video_url' => $video->video_url ?? '',
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
                'video_url' => $video->video_url ?? '',
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
        $liked = false;
        $user = $request->user('api') ?? $request->user('sanctum') ?? null;
        // Check if the user is blocked
        if ($user && $user->is_blocked) {
            return response()->json([
                'status' => false,
                'message' => 'Your account has been blocked. Please contact support.',
            ], 403); // Forbidden
        }
        if ($user) {
            $liked = $user->likedVideos()->where('video_id', $video->id)->exists();
        }

        // Find the region ID based on the regionCode
        $regionModel = Region::where('region_code', $regionCode)->first();
        $regionId = $regionModel ? $regionModel->id : 0;

        // Fetch SEO data using the regionId
        $seoData = $this->getSeoData($video, $regionId);
        $affiliateLinks = AffiliateLink::where('video_id', $video->id)
            ->where('region_id', $regionId)
            ->get();


        $affiliateLinksData = $affiliateLinks->map(function ($link) {
            // Extract the retailer name by splitting the string at the first underscore
            $retailerName = explode('_', $link->retailer)[0];

            return [
                'region_id' => $link->region_id,
                'retailer' => $retailerName,
                'url' => $link->url,
            ];
        });

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
            ->select(['id', 'title', 'thumbnail_image', 'description', 'product_name', 'product_asin_sku', 'product_thumbnail', 'review_details', 'status', 'type', 'character_id', 'created_at'])
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

        $comments = $video->comments()  // Assuming you have a 'comments' relationship defined in the Video model
            ->with(['user', 'replies.user']) // Load the user for each comment and replies
            ->withCount('replies') // Count replies for each comment
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'video_id' => $comment->video_id,
                    'user_id' => $comment->user_id,
                    'name' => $comment->user->name ?? null,
                    'email' => $comment->user->email ?? null,
                    'profile_image' => $comment->user->profile_image ?? null,
                    'parent_id' => $comment->parent_id,
                    'body' => $comment->body,
                    'replies_count' => $comment->replies_count,
                    'created_at' => $comment->created_at->toISOString(),
                    'updated_at' => $comment->updated_at->toISOString(),
                    'replies' => $comment->replies->isEmpty() ? [] : $comment->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'user_id' => $reply->user_id,
                            'name' => $reply->user->name ?? null,
                            'email' => $reply->user->email ?? null,
                            'profile_image' => $reply->user->profile_image ?? null,
                            'parent_id' => $reply->parent_id,
                            'body' => $reply->body,
                            'created_at' => $reply->created_at->toISOString(),
                            'updated_at' => $reply->updated_at->toISOString(),
                        ];
                    }),
                ];
            });

        $data = [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'type' => $video->type,
                'video_url' => $video->video_url ?? '',
                //'thumbnail_url' => $video->thumbnail_url,
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
                'liked' => $liked,
                'seo_title' => $seoData['seo_title'],
                'seo_description' => $seoData['seo_description'],
                'hashtags' => $seoData['hashtags'],
                'cta_text' => $seoData['cta_text'],
                'og_image_url' => $seoData['og_image_url'],
                'open_graph_image' => $seoData['open_graph_image'],
                'twitter_title' => $seoData['twitter_title'],
                'twitter_description' => $seoData['twitter_description'],
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
                'comments' => $comments,
                'affiliate_links' => $affiliateLinksData,
            ],
            'related_products' => $related,
            'character_data' => $character ? $character : null,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Video detail fetched successfully',
            'data' => $data,
        ], 200);
    }

    public function recommendedVideos(Request $request, $region = null)
    {
        try {
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // Get the authenticated user
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }

            $recommendedVideos = collect();

            // If the user is authenticated, fetch recommended videos based on watch history and region
            if ($user) {
                // Base query to fetch recommended videos based on user's watch history and region
                $q = Video::with([
                    'reviews:id,video_id,rating',
                    'regions:id,region_code',
                ])
                    ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                        $join->on('vwh.video_id', '=', 'videos.id')
                            ->where('vwh.user_id', '=', $user->id);
                    })
                    ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'videos.channel_id')
                    ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                    ->where(function ($query) use ($regionCode) {
                        if ($regionCode !== 'GLOBAL') {
                            $query->where('rr.region_code', '=', $regionCode);
                        }
                    })
                    ->where('videos.status', 'published') // Filter published videos
                    ->select(
                        'videos.id',
                        'videos.title',
                        'videos.description',
                        'videos.video_url',
                        'videos.type',
                        'videos.thumbnail_image',
                        'videos.channel_id',
                        'videos.character_id',
                        'videos.category_id',
                        'videos.product_name',
                        'videos.product_asin_sku',
                        'videos.public_rating',
                        'videos.product_thumbnail',
                        'videos.video_type',
                        'videos.views',
                        'videos.likes',
                        'videos.rating_type',
                        'videos.sponsorship_type',
                        'videos.highlight_tags',
                        'videos.created_at',
                        'videos.updated_at',
                        'videos.tag_ids' // Get tag ids directly
                    )
                    ->groupBy(
                        'videos.id',
                        'videos.title',
                        'videos.description',
                        'videos.video_url',
                        'videos.type',
                        'videos.thumbnail_image',
                        'videos.channel_id',
                        'videos.character_id',
                        'videos.category_id',
                        'videos.product_name',
                        'videos.product_asin_sku',
                        'videos.public_rating',
                        'videos.product_thumbnail',
                        'videos.video_type',
                        'videos.views',
                        'videos.likes',
                        'videos.rating_type',
                        'videos.sponsorship_type',
                        'videos.highlight_tags',
                        'videos.created_at',
                        'videos.updated_at',
                        'videos.tag_ids'
                    )
                    ->orderByDesc('vwh.created_at')
                    ->limit(20);

                // Check if the user has a valid subscription
                if ($this->hasValidSubscription($user->id)) {
                    // Include both free and paid videos
                    $q->whereIn('videos.type', ['youtube', 'vimeo']);
                } else {
                    // Only include free videos (assuming 'youtube' is for free videos)
                    $q->where('videos.type', 'youtube');
                }

                // Execute the query to get recommended videos
                $recommendedVideos = $q->get();
            }

            // If there are videos, process the additional fields like tags and images
            $data = $recommendedVideos->map(function ($video) {

                // Handle video regions (remove pivot relation for simplicity)
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

                // Handle tags - manually map the tag_ids if necessary
                $tags = collect(explode(',', (string) $video->tag_ids)) // Assuming tag_ids is a comma-separated string
                    ->map(fn($s) => trim($s))
                    ->filter()
                    ->values();

                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'type' => $video->type,
                    'video_url' => $video->video_url ?? '',
                    'thumbnail_image' => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
                    'character_id' => $video->character_id,
                    'channel_id' => $video->channel_id,
                    'category_id' => $video->category_id,
                    'product_name' => $video->product_name,
                    'product_asin_sku' => $video->product_asin_sku,
                    'public_rating' => $video->public_rating,
                    'product_thumbnail' => $video->product_thumbnail ? asset($video->product_thumbnail) : null,
                    'rating_type' => $video->rating_type,
                    'sponsorship_type' => $video->sponsorship_type,
                    'highlight_tags' => $video->highlight_tags,
                    'created_at' => $video->created_at->toDateTimeString(),
                    'updated_at' => $video->updated_at->toDateTimeString(),
                    'tags' => $tags, // Add tags directly here
                    'views' => $video->views,
                    'likes' => $video->likes,
                    'regions' => $video->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'status' => $video->status,
                    'is_draft' => $video->is_draft,
                    'is_ai_generated' => $video->is_ai_generated,
                    'qa_passed' => $video->qa_passed,
                    'post_schedule_at' => $video->post_schedule_at,
                    'seo_title' => $video->seo_title,
                    'seo_description' => $video->seo_description,
                    'cta_text' => $video->cta_text,
                    'og_image_url' => $video->og_image_url,
                    'open_graph_image' => $video->open_graph_image,
                    'twitter_title' => $video->twitter_title,
                    'twitter_description' => $video->twitter_description,
                    'original_price' => $video->original_price,
                    'sale_end_date' => $video->sale_end_date,
                    'is_amazon_choice' => $video->is_amazon_choice,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => $this->hasValidSubscription($user->id)
                    ? 'Paid and free recommended videos fetched successfully'
                    : 'Your subscription has ended. Showing free recommended videos only.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch recommended videos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
