<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Region;
use App\Models\ChannelRegion;
use App\Models\Subscription;
use App\Models\ChannelFollow;
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
use Illuminate\Support\Facades\Auth;




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


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:channels,name',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/ogg|max:102400', // <= ~100MB
            'regions' => 'array|nullable',
            'regions.*' => 'integer|exists:regions,id',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'accent_color' => 'nullable|string|max:20',
            'background_color' => 'nullable|string|max:20',
            'channel_category' => 'required|in:pet,people',
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
        $videoPath = null;
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_' . Str::random(6) . '.' . $video->getClientOriginalExtension();

            $destinationPath = public_path('/channel_videos'); // no space
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $video->move($destinationPath, $videoName);
            $videoPath = 'channel_videos/' . $videoName; // relative public path
        }

        DB::transaction(function () use ($request, $imagePath, $videoPath) {
            $channel = Channel::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'image' => $imagePath,
                'video' => $videoPath,
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
                'accent_color' => $request->accent_color,
                'background_color' => $request->background_color,
                'channel_category' => $request->channel_category,
            ]);


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

    public function update(Request $request, Channel $channel)
    {
        $request->validate([
            'name' => 'required|unique:channels,name,' . $channel->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10048',
            'video'  => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/ogg|max:102400', // ~100MB
            'regions' => 'nullable|array',
            'regions.*' => 'exists:regions,id',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'accent_color' => 'nullable|string|max:20',
            'background_color' => 'nullable|string|max:20',
            'channel_category' => 'required|in:pet,people',
        ]);

        $imagePath = $channel->image;
        $videoPath = $channel->video; 

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

        if ($request->hasFile('video')) {
        $video = $request->file('video');
        $videoName = time() . '_' . Str::random(6) . '.' . $video->getClientOriginalExtension();

        $destinationPath = public_path('/channel_videos');
        if (!\Illuminate\Support\Facades\File::isDirectory($destinationPath)) {
            \Illuminate\Support\Facades\File::makeDirectory($destinationPath, 0755, true, true);
        }

        // delete old video if exists
        if (!empty($channel->video) && file_exists(public_path($channel->video))) {
            @unlink(public_path($channel->video));
        }

        $video->move($destinationPath, $videoName);
        $videoPath = 'channel_videos/' . $videoName; // relative public path
    }

        DB::transaction(function () use ($request, $channel, $imagePath, $videoPath) {

            $channel->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'image' => $imagePath,
                'video' => $videoPath,
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
                'accent_color' => $request->accent_color,
                'background_color' => $request->background_color,
                'channel_category' => $request->channel_category,
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
            // $channels = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
            $channels = Channel::select('id', 'name', 'image', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
                ->with([
                    'regions:id,region_code'
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
            // $channels = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
            $channels = Channel::select('id', 'name', 'image', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
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
    // public function index_people_by_region_api(Request $request, $region = null)
    // {
    //     try {
    //         $input = strtoupper($region ?? $request->input('region', ''));
    //         $allowed = ['AU', 'CA', 'UK', 'US'];
    //         $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';


    //         // $type = $request->input('type', 'people');
    //         $type = 'people';

    //         $channels = Channel::select(
    //             'id',
    //             'name',
    //             'image',
    //             'primary_color',
    //             'secondary_color',
    //             'accent_color',
    //             'background_color',
    //             'channel_category',
    //             'created_at',
    //             'updated_at'
    //         )
    //             ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
    //             ->when($type, fn($q) => $q->where('channel_category', $type)) // <-- filter by type
    //             ->latest()
    //             ->get();

    //         // enrich + hide fields
    //         $channels->each(function ($channel) {
    //             $channel->image_url = $channel->image ? asset($channel->image) : null;
    //             $channel->makeHidden(['image']);
    //             if ($channel->relationLoaded('regions')) {
    //                 $channel->regions->each->makeHidden(['pivot']);
    //             }
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Channels fetched successfully',
    //             'data' => $channels,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch channels',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function index_pets_by_region_api(Request $request, $region = null)
    // {
    //     try {
    //         $input = strtoupper($region ?? $request->input('region', ''));
    //         $allowed = ['AU', 'CA', 'UK', 'US'];
    //         $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';


    //         // $type = $request->input('type', 'people');
    //         $type = 'pet';

    //         $channels = Channel::select(
    //             'id',
    //             'name',
    //             'image',
    //             'primary_color',
    //             'secondary_color',
    //             'accent_color',
    //             'background_color',
    //             'channel_category',
    //             'created_at',
    //             'updated_at'
    //         )
    //             ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
    //             ->when($type, fn($q) => $q->where('channel_category', $type)) // <-- filter by type
    //             ->latest()
    //             ->get();

    //         // enrich + hide fields
    //         $channels->each(function ($channel) {
    //             $channel->image_url = $channel->image ? asset($channel->image) : null;
    //             $channel->makeHidden(['image']);
    //             if ($channel->relationLoaded('regions')) {
    //                 $channel->regions->each->makeHidden(['pivot']);
    //             }
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Channels fetched successfully',
    //             'data' => $channels,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch channels',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }






    public function showChannelDetailsByRegion(Request $request, $channelId, $region = null)
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
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';
            $regionCodes = array_unique([$regionCode, 'GLOBAL']); // ← include GLOBAL fallback

            // Optional query filters
            // ?category_id=... (to narrow a single category)
            // ?paginate_videos=1&per_page=20&page=1 (if you ever want to paginate just videos)
            $filterCategoryId = $request->integer('category_id');

            //$channel = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
            $channel = Channel::select('id', 'name', 'image', 'video', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
                ->where('id', $channelId)
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->with([
                    'regions:id,region_code',

                    'categories' => function ($q) use ($filterCategoryId, $regionCode) {
                        if ($filterCategoryId) {
                            $q->where('id', $filterCategoryId);
                        }

                        // region filter on categories to avoid over-filtering
                        // $q->whereHas('regions', function ($r) use ($regionCode) {
                        //     $r->where('region_code', $regionCode);
                        // })
                        $q->select('id', 'name', 'channel_id');
                    },

                    'categories.characters' => function ($q) use ($regionCodes) {
                        $q->select([
                            'id',
                            'name',
                            'image',
                            'category_id',
                            'character_page_url_slug',
                            'persona',
                            'public_private_toggle',
                            'character_tag',
                            'character_role',
                            'created_at',
                            'updated_at',
                        ])
                            ->whereHas('regions', function ($r) use ($regionCodes) {
                                $r->whereIn('region_code', $regionCodes);
                            })
                            ->with([
                                'regions:id,region_code',
                            ])
                            ->latest();
                    },
                ])
                ->first();

                $isFollowing = false;
                if ($user) {
                    $isFollowing = \App\Models\ChannelFollow::where('channel_id', $channelId)
                        ->where('user_id', $user->id)
                        ->exists();
                }


            if (!$channel) {
                return response()->json([
                    'status' => false,
                    'message' => 'Channel not found for the requested region.',
                    'data' => [],
                ], 404);
            }


            $includePaid = $user && $this->hasValidSubscription($user->id);
            $allowedTypes = $includePaid ? ['youtube', 'vimeo'] : ['youtube'];

            $characterIds = collect($channel->categories)
                ->flatMap(fn($cat) => $cat->characters->pluck('id'))
                ->unique()
                ->values();

            $videoQuery = Video::with([
                'regions:id,region_code',
                'reviews' => function ($q) {
                    $q->select('id', 'video_id', 'user_id', 'rating', 'review', 'status', 'created_at')
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
                ->whereIn('type', $allowedTypes)
                ->whereIn('character_id', $characterIds)
                ->whereHas('regions', function ($q) use ($regionCodes) {
                    $q->whereIn('region_code', $regionCodes);
                })
                ->latest();

            if ($filterCategoryId) {
                $videoQuery->where('category_id', $filterCategoryId);
            }

            $videos = $videoQuery->get();

            $allTagIds = $videos->flatMap(fn($v) => $v->tag_ids_array ?? [])->filter()->unique();
            $tagMap = $allTagIds->isNotEmpty()
                ? Tag::whereIn('id', $allTagIds)->pluck('name', 'id')
                : collect();

            $normalizedVideos = $videos->map(function ($video) use ($tagMap) {
                if ($video->relationLoaded('regions')) {
                    $video->regions->each->makeHidden(['pivot']);
                }

                $ids = collect($video->tag_ids_array ?? []);
                $tagPairs = $ids->map(function ($id) use ($tagMap) {
                    $name = $tagMap->get($id);
                    return $name ? ['id' => $id, 'name' => $name] : null;
                })->filter()->values()->all();

                $reviews = $video->reviews->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'rating' => $r->rating,
                        'review' => $r->review,
                        'created_at' => optional($r->created_at)->toDateTimeString(),
                        'user' => $r->relationLoaded('user_api') && $r->user_api ? [
                            'id' => $r->user_api->id,
                            'name' => $r->user_api->name,
                            'profile_image' => $r->user_api->profile_image ? asset($r->user_api->profile_image) : null,
                        ] : null,
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
                    'tags' => $tagPairs,
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
                    ])->values(),
                    'reviews' => $reviews,
                    'rating_avg' => $video->rating_avg ? round((float) $video->rating_avg, 2) : null,
                    'rating_count' => (int) ($video->rating_count ?? 0),
                ];
            });

            // Group videos by category (so we can attach directly under each category)
            $videosByCategory = $normalizedVideos->groupBy('category_id');

            // === NEW: collect ALL approved reviews (one list for the whole response) ===
            $videoIds = $videos->pluck('id');
            // $allReviewRows = \App\Models\Review::with(['user:id,name,profile_image'])
            //     ->whereIn('video_id', $videoIds)
            //     ->where('status', 'approved')
            //     ->latest()
            //     ->get();

            // $allReviews = $allReviewRows->map(function ($rev) {
            //     return [
            //         'id' => $rev->id,
            //         'video_id' => $rev->video_id,
            //         'rating' => (int) $rev->rating,
            //         'review' => $rev->review,
            //         'created_at' => optional($rev->created_at)->toDateTimeString(),
            //         'reviewer_name' => optional($rev->user)->name,
            //         'reviewer_profile_image' => optional($rev->user)->profile_image
            //             ? asset(optional($rev->user)->profile_image)
            //             : null,
            //     ];
            // })->values();

            // Channel top-level
            $channelPayload = [
                'id' => $channel->id,
                'name' => $channel->name,
                'image_url' => $channel->image ? asset($channel->image) : null,
                'video_url' => $channel->video ? asset($channel->video) : null,
                'created_at' => optional($channel->created_at)->toDateTimeString(),
                'updated_at' => optional($channel->updated_at)->toDateTimeString(),
                'is_following' => $isFollowing,
                'regions' => $channel->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ])->values(),
                'categories' => collect($channel->categories)->map(function ($category) use ($videosByCategory) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        // Put videos directly under the category:
                        'videos' => $videosByCategory->get($category->id, collect())->values(),
                        // NOTE: characters intentionally omitted from the payload per requirement
                    ];
                })->values(),
                // NEW: flattened reviews for all videos in this response
                // 'reviews' => $allReviews,
            ];

            return response()->json([
                'status' => true,
                'message' => $includePaid
                    ? 'Channel with categories and paid+free videos fetched successfully'
                    : 'Channel with categories and free videos fetched successfully (subscribe for more).',
                'data' => $channelPayload,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch channel details',
                'error' => $e->getMessage(),
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
        // $applyVideoHighlightFilter = function ($q, array $ids, bool $matchAll) {
        //     if (empty($ids))
        //         return;
        //     $q->where(function ($sub) use ($ids, $matchAll) {
        //         foreach ($ids as $idx => $id) {
        //             // Strip leading/trailing quotes inside SQL before FIND_IN_SET
        //             $expr = "FIND_IN_SET(CAST(? AS CHAR), TRIM(BOTH '\"' FROM videos.highlight_tags))";
        //             if ($matchAll) {
        //                 $sub->whereRaw($expr, [$id]);
        //             } else {
        //                 $idx === 0
        //                     ? $sub->whereRaw($expr, [$id])
        //                     : $sub->orWhereRaw($expr, [$id]);
        //             }
        //         }
        //     });
        // };


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

        //$channelsQuery = Channel::select('id', 'name', 'image', 'created_at', 'updated_at')
        $channelsQuery = Channel::select('id', 'name', 'image', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->with(['regions:id,region_code'])
            ->latest();

        if (!empty($channelIds)) {
            $channelsQuery->whereIn('id', $channelIds);
        } elseif ($hasTagOrHlOrTagIds) {
            $channelsQuery->whereHas('categories.characters.videos', $videoWhere);
        }
        // if (!empty($channelIds)) {
        //     $channelsQuery->whereIn('channels.id', $channelIds);
        // } elseif ($hasTagOrHlOrTagIds) {
        //     $channelsQuery->whereHas('categories.characters.videos', $videoWhere);
        // }


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
        // $categoriesQuery = Category::select('id', 'name', 'slug', 'image', 'created_at', 'updated_at')
        //     ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
        //     ->when(
        //         !empty($channelIdsForChildren),
        //         fn($q) =>
        //         $q->whereHas('channel', fn($q2) => $q2->whereIn('channels.id', $channelIdsForChildren))
        //     )
        //     ->with(['regions:id,region_code'])
        //     ->latest();

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
            ->where('public_private_toggle', 0)
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

    public function filter_region_api_new(Request $request, $region = null)
    {
        /**
         * ---------------------------
         * Helper: Parse tags
         * ---------------------------
         */
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

        /**
         * ---------------------------
         * Helper: Parse IDs
         * ---------------------------
         */
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
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
            return array_values(array_unique(array_keys($ids))); // unique ints
        };

        /**
         * ---------------------------
         * Helper: Apply video tag filter
         * ---------------------------
         */
        $applyVideoTagFilter = function ($q, array $tags, bool $matchAll) {
            if (empty($tags))
                return;
            $q->where(function ($sub) use ($tags, $matchAll) {
                foreach ($tags as $idx => $tag) {
                    $expr = "JSON_CONTAINS(CAST(videos.tags AS JSON), ?)";
                    $param = json_encode($tag, JSON_UNESCAPED_UNICODE);
                    if ($matchAll) {
                        $sub->whereRaw($expr, [$param]); // AND
                    } else {
                        $idx === 0
                            ? $sub->whereRaw($expr, [$param])
                            : $sub->orWhereRaw($expr, [$param]); // OR
                    }
                }
            });
        };

        /**
         * ---------------------------
         * Helper: Apply video highlight filter
         * ---------------------------
         */
        $applyVideoHighlightFilter = function ($q, array $ids, bool $matchAll) {
            if (empty($ids))
                return;
            $q->where(function ($sub) use ($ids, $matchAll) {
                foreach ($ids as $idx => $id) {
                    $expr = "FIND_IN_SET(CAST(? AS CHAR), TRIM(BOTH '\"' FROM videos.highlight_tags))";
                    if ($matchAll) {
                        $sub->whereRaw($expr, [$id]); // AND
                    } else {
                        $idx === 0
                            ? $sub->whereRaw($expr, [$id]) // first
                            : $sub->orWhereRaw($expr, [$id]); // OR others
                    }
                }
            });
        };

        /**
         * ---------------------------
         * Parse request inputs
         * ---------------------------
         */
        $input = strtoupper($region ?? $request->input('region', ''));
        $allowed = ['AU', 'CA', 'UK', 'US']; // extend as needed
        $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

        // IDs
        $channelIds = $parseIds($request->input('channel', []));
        $categoryIds = $parseIds($request->input('category', []));
        $characterIds = $parseIds($request->input('character', []));
        $tagIdsFilter = $parseIds($request->input('tag_ids', []));

        // Tags
        $tagsInputRaw = $request->input('tags', $request->input('tag', []));
        $hlInputRaw = $request->input('highlight', $request->input('highlight_tags', $request->input('highlights', [])));

        $tagsFilter = $parseTags($tagsInputRaw);
        $hlFilter = $parseIds($hlInputRaw);

        $matchAll = $request->boolean('match_all', false);
        $hasTagOrHlOrTagIds = !empty($tagsFilter) || !empty($hlFilter) || !empty($tagIdsFilter);

        /**
         * ---------------------------
         * Sidebar (all data without filter)
         * ---------------------------
         */
        $sidebarChannels = Channel::select('id', 'name', 'image')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->latest()
            ->get()
            ->each(function ($ch) {
                $ch->image_url = $ch->image ? asset($ch->image) : null;
                $ch->makeHidden(['image']);
            });

        $sidebarCategories = Category::select('id', 'name', 'image', 'channel_id')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->latest()
            ->get()
            ->each(function ($cat) {
                $cat->image_url = $cat->image ? asset($cat->image) : null;
                $cat->makeHidden(['image']);
            });

        $sidebarCharacters = Character::select('id', 'name', 'image', 'category_id')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->latest()
            ->get()
            ->each(function ($c) {
                $c->image_url = $c->image ? asset($c->image) : null;
                $c->makeHidden(['image']);
            });

        $sidebarVideos = Video::select('id', 'title', 'thumbnail_image')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->latest()
            ->get()
            ->each(function ($v) {
                $v->thumbnail_image = $v->thumbnail_image ? asset($v->thumbnail_image) : null;
            });

        $sidebarHighlightTags = HighlightTag::select('id', 'label', 'emoji')
            ->orderBy('label')
            ->get()
            ->map(fn($ht) => [
                'id' => $ht->id,
                'label' => $ht->label,
                'emoji' => asset($ht->emoji)
            ]);

        $allTagIds = Tag::select('id', 'name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name]);

        /**
         * ---------------------------
         * Video where condition
         * ---------------------------
         */
        $videoWhere = function ($q) use ($regionCode, $applyVideoTagFilter, $applyVideoHighlightFilter, $tagsFilter, $hlFilter, $matchAll, $tagIdsFilter) {
            $q->whereHas('regions', fn($r) => $r->where('region_code', $regionCode));
            $applyVideoTagFilter($q, $tagsFilter, $matchAll);
            $applyVideoHighlightFilter($q, $hlFilter, $matchAll);

            if (!empty($tagIdsFilter)) {
                $q->where(function ($sub) use ($tagIdsFilter, $matchAll) {
                    foreach ($tagIdsFilter as $idx => $tagId) {
                        $expr = 'FIND_IN_SET(?, videos.tag_ids)';
                        if ($matchAll) {
                            $sub->whereRaw($expr, [$tagId]); // AND
                        } else {
                            $idx === 0
                                ? $sub->whereRaw($expr, [$tagId]) // first
                                : $sub->orWhereRaw($expr, [$tagId]); // OR others
                        }
                    }
                });
            }
        };


        /**
         * ---------------------------
         * Channels
         * ---------------------------
         * 
         */

        $channelsQuery = Channel::select('id', 'name', 'image', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'created_at', 'updated_at')
            ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
            ->with(['regions:id,region_code'])
            ->latest();

        if (!empty($channelIds)) {
            $channelsQuery->whereIn('id', $channelIds);
        } elseif ($hasTagOrHlOrTagIds) {
            $channelsQuery->whereHas('videos', $videoWhere)
                ->orWhereHas('categories', fn($q) => $q->whereHas('videos', $videoWhere));
        }

        $channels = $channelsQuery->get()->each(function ($ch) {
            $ch->image_url = $ch->image ? asset($ch->image) : null;
            $ch->makeHidden(['image']);
            if ($ch->relationLoaded('regions')) {
                $ch->regions->each->makeHidden(['pivot']);
            }
        });

        // --------------------------- STEP 1: Use only found IDs ---------------------------
        $channelIdsForChildren = $channels->pluck('id')->all();


        // If no channels found, downstream categories, characters, videos should be empty
        if (empty($channelIdsForChildren) && (!empty($channelIds) || $hasTagOrHlOrTagIds)) {
            $categories = collect();
            $categoryIdsForChildren = [];
            $characters = collect();
            $characterIdsForChildren = [];
            $videosPayload = collect();
            $availableHighlightTags = collect();
            $tagsIdsList = [];
        } else {
            /**
             * ---------------------------
             * Categories
             * ---------------------------
             */
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
                if ($cat->relationLoaded('regions')) {
                    $cat->regions->each->makeHidden(['pivot']);
                }
            });

            $categoryIdsForChildren = $categories->pluck('id')->all();

            /**
             * ---------------------------
             * Characters
             * ---------------------------
             */
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
                ->where('public_private_toggle', 0)
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
                if ($c->relationLoaded('regions')) {
                    $c->regions->each->makeHidden(['pivot']);
                }
            });

            $characterIdsForChildren = $characters->pluck('id')->all();

            /**
             * ---------------------------
             * Videos
             * ---------------------------
             */
            $videosQuery = Video::select([
                'id',
                'title',
                'thumbnail_image',
                'video_url',
                'type',
                'product_thumbnail',
                'product_name',
                'product_asin_sku',
                'public_rating',
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
                            $sub->whereRaw($expr, [$tagId]); // AND
                        } else {
                            $idx === 0
                                ? $sub->whereRaw($expr, [$tagId]) // first
                                : $sub->orWhereRaw($expr, [$tagId]); // OR others
                        }
                    }
                });
            }

            $videos = $videosQuery->get();

            // Collect tag IDs and names
            $tagIds = [];
            foreach ($videos as $v) {
                $vTagIds = explode(',', (string) $v->tag_ids);
                foreach ($vTagIds as $id) {
                    $id = (int) trim((string) $id);
                    if ($id > 0)
                        $tagIds[$id] = true;
                }
            }

            $tagsWithNames = Tag::whereIn('id', array_keys($tagIds))
                ->get(['id', 'name'])
                ->pluck('name', 'id')
                ->toArray();

            $tagsIdsList = collect(array_keys($tagIds))
                ->filter()
                ->map(fn($id) => ['id' => (int) $id, 'name' => ($tagsWithNames[$id] ?? '')])
                ->values()
                ->all();

            // Available highlight tags
            $highlightTagIds = [];
            foreach ($videos as $v) {
                foreach (explode(',', (string) $v->highlight_tags) as $rawId) {
                    $id = (int) trim($rawId);
                    if ($id > 0)
                        $highlightTagIds[$id] = true;
                }
            }

            $availableHighlightTags = [];
            if (!empty($highlightTagIds)) {
                $ids = array_keys($highlightTagIds);
                $availableHighlightTags = HighlightTag::select('id', 'label', 'emoji')
                    ->whereIn('id', $ids)
                    ->orderBy('label')
                    ->get()
                    ->map(fn($ht) => [
                        'id' => $ht->id,
                        'label' => $ht->label,
                        'emoji' => asset($ht->emoji)
                    ])
                    ->values();
            }

            // Format videos payload
            $videosPayload = $videos->map(function ($v) use ($tagsWithNames) {
                $vTagIds = explode(',', (string) $v->tag_ids);
                $highlightIds = collect(explode(',', (string) $v->highlight_tags))
                    ->filter()
                    ->map(fn($x) => (int) trim($x))
                    ->filter()
                    ->values()
                    ->all();

                $tagDetails = array_map(function ($id) use ($tagsWithNames) {
                    $id = (int) trim((string) $id);
                    $name = $tagsWithNames[$id] ?? '';
                    return ['id' => $id, 'name' => $name];
                }, $vTagIds);

                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'thumbnail_image' => $v->thumbnail_image ? asset($v->thumbnail_image) : null,
                    'video_url' => $v->video_url,
                    'type' => $v->type,
                    'product_thumbnail' => $v->product_thumbnail ? asset($v->product_thumbnail) : null,
                    'product_name' => $v->product_name,
                    'product_asin_sku' => $v->product_asin_sku,
                    'public_rating' => $v->public_rating,
                    'character_id' => $v->character_id,
                    'tags' => $tagDetails,
                    'highlight_tag_ids' => $highlightIds,
                    'created_at' => optional($v->created_at)->toDateTimeString(),
                ];
            });
        }

        /**
         * ---------------------------
         * Final JSON response
         * ---------------------------
         */
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
                'sidebar' => [
                    'channels' => $sidebarChannels,
                    'categories' => $sidebarCategories,
                    'characters' => $sidebarCharacters,
                    'videos' => $sidebarVideos,
                    'highlight_tags' => $sidebarHighlightTags,
                    'tags_ids' => $allTagIds,
                ],
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

    // Follow
    public function follow($channelId)
    {
        $user = Auth::user();


        $alreadyFollowed = ChannelFollow::where('user_id', $user->id)
            ->where('channel_id', $channelId)
            ->exists();
        // dd($alreadyFollowed);
        if ($alreadyFollowed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Already following this channel',
            ], 400);
        }


        ChannelFollow::create([
            'user_id' => $user->id,
            'channel_id' => $channelId,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Channel followed successfully',
        ]);
    }

    // Unfollow


    // public function unfollow($channelId)
    // {
    //     $user = Auth::user();

    //     ChannelFollow::where('user_id', $user->id)
    //         ->where('channel_id', $channelId)
    //         ->delete();

    //     return response()->json([
    //         'status' => 'ok',
    //         'message' => 'Channel unfollowed successfully',
    //     ]);
    // }

    public function listFollows()
    {
        $follows = ChannelFollow::with('channel')
            ->get()
            ->map(function ($follow) {
                return [
                    'channel_id' => $follow->channel->id,
                    'channel_name' => $follow->channel->name,
                    // 'user_id'      => $follow->user_id,
                ];
            });

        return response()->json([
            'status' => 'ok',
            'data' => $follows,
        ]);
    }
}