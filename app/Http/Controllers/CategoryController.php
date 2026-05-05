<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use App\Models\CategoryRegion;
use App\Models\Region;
use App\Models\Subscription;
use App\Models\Channel;
use App\Models\CategoryFollow;
use App\Models\ProductReview;
use App\Models\Character;
use App\Models\Video;
use Illuminate\Support\Facades\Auth;
use App\HasSubscriptionSections;
// use App\Traits\HasSubscriptionSections;

class CategoryController extends Controller
{
    use HasSubscriptionSections;
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:category.view', only: ['index']),
            new Middleware('permission:category.create', only: ['create', 'store']),
            new Middleware('permission:category.edit', only: ['edit', 'update']),
            new Middleware('permission:category.delete', only: ['destroy']),

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

    // public function index()
    // {
    //     $categories = Category::latest()->paginate(10);
    //     return view('admin.categories.index', compact('categories'));
    // }
    public function index()
    {
        $categories = Category::latest()->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $channels = Channel::all();
        $regions = Region::where('is_active', 1)->get();

        return view('admin.categories.create', compact('channels', 'regions'));
    }



    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name',
            'channel_id' => 'required|exists:channels,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'regions' => 'array|nullable',
            'regions.*' => 'integer|exists:regions,id',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
            $request->image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;
        }

        DB::transaction(function () use ($request, $imagePath) {
            $category = Category::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'channel_id' => $request->channel_id,
                'image' => $imagePath,
            ]);

            $regionIds = collect($request->input('regions', []))
                ->filter()
                ->unique()
                ->values();

            if ($regionIds->isNotEmpty()) {
                $rows = $regionIds->map(fn($rid) => [
                    'category_id' => $category->id,
                    'region_id' => $rid,
                ])->all();

                CategoryRegion::insert($rows);
                // Alternatively:
                // $category->regions()->attach($regionIds);
            }
        });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully!');
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|unique:categories,name',
    //         'channel_ids' => 'required|array',
    //         'channel_ids.*' => 'exists:channels,id',
    //         'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
    //         'regions' => 'array|nullable',
    //         'regions.*' => 'integer|exists:regions,id',
    //     ]);

    //     $imagePath = null;
    //     if ($request->hasFile('image')) {
    //         $folderPath = public_path('category');
    //         if (!file_exists($folderPath)) {
    //             mkdir($folderPath, 0777, true);
    //         }
    //         $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
    //         $request->image->move($folderPath, $imageName);
    //         $imagePath = 'category/' . $imageName;
    //     }

    //     DB::transaction(function () use ($request, $imagePath) {
    //         // create category without channel_id (since pivot is used now)
    //         $category = Category::create([
    //             'name' => $request->name,
    //             'slug' => Str::slug($request->name),
    //             'image' => $imagePath,
    //         ]);

    //         // attach selected channels
    //         $category->channel()->attach($request->channel_ids);

    //         // attach regions (your existing logic)
    //         $regionIds = collect($request->input('regions', []))
    //             ->filter()
    //             ->unique()
    //             ->values();

    //         if ($regionIds->isNotEmpty()) {
    //             $rows = $regionIds->map(fn($rid) => [
    //                 'category_id' => $category->id,
    //                 'region_id' => $rid,
    //             ])->all();

    //             CategoryRegion::insert($rows);
    //         }
    //     });

    //     return redirect()->route('admin.categories.index')
    //         ->with('success', 'Category created successfully!');
    // }




    public function getRegions($channelId)
    {
        $channel = Channel::findOrFail($channelId);
        $regions = $channel->regions;  // Assuming there's a `regions()` relationship defined in the `Channel` model
        return response()->json($regions);
    }

    public function edit(Category $category)
    {

        $category->loadMissing('regions');

        $channels = Channel::all();


        $selectedRegions = $category->regions()
            ->pluck('regions.id')
            ->toArray();

        // show active regions OR ones already selected
        $regions = Region::where('is_active', 1)
            ->orWhereIn('id', $selectedRegions)
            ->get();

        return view('admin.categories.edit', compact('category', 'channels', 'regions', 'selectedRegions'));
    }
    // public function edit(Category $category)
    // {
    //     $category->loadMissing(['regions', 'channel']); // eager load channels too

    //     $channels = Channel::all();

    //     $selectedRegions = $category->regions()
    //         ->pluck('regions.id')
    //         ->toArray();

    //     $selectedChannels = $category->channel()
    //         ->pluck('channels.id')
    //         ->toArray();

    //     // show active regions OR ones already selected
    //     $regions = Region::where('is_active', 1)
    //         ->orWhereIn('id', $selectedRegions)
    //         ->get();

    //     return view('admin.categories.edit', compact('category', 'channels', 'regions', 'selectedRegions', 'selectedChannels'));
    // }



    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|unique:categories,name,' . $category->id,
            'channel_id' => 'required|exists:channels,id',
            // 'channel_ids' => 'required|array',
            // 'channel_ids.*' => 'exists:channels,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'regions' => 'nullable|array',
            'regions.*' => 'integer|exists:regions,id',
        ]);

        $imagePath = $category->image;

        if ($request->hasFile('image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }

            if ($category->image && file_exists(public_path($category->image))) {
                unlink(public_path($category->image));
            }

            $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
            $request->image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;
        }

        DB::transaction(function () use ($request, $category, $imagePath) {
            $category->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'channel_id' => $request->channel_id,
                'image' => $imagePath,
            ]);

            // Wipe old and insert new (mirrors your channel logic)
            CategoryRegion::where('category_id', $category->id)->delete();

            $regionIds = collect($request->input('regions', []))
                ->filter()
                ->unique()
                ->values();

            if ($regionIds->isNotEmpty()) {
                $rows = $regionIds->map(fn($rid) => [
                    'category_id' => $category->id,
                    'region_id' => $rid,
                ])->all();

                CategoryRegion::insert($rows);
                // Alternatively:
                // $category->regions()->sync($regionIds);
            }
        });
        //         DB::transaction(function () use ($request, $category, $imagePath) {
        //     $category->update([
        //         'name' => $request->name,
        //         'slug' => Str::slug($request->name),
        //         'image' => $imagePath,
        //     ]);

        //     // sync channels instead of single channel_id
        //     $category->channel()->sync($request->channel_ids);

        //     // wipe old regions and insert new
        //     CategoryRegion::where('category_id', $category->id)->delete();

        //     $regionIds = collect($request->input('regions', []))
        //         ->filter()
        //         ->unique()
        //         ->values();

        //     if ($regionIds->isNotEmpty()) {
        //         $rows = $regionIds->map(fn($rid) => [
        //             'category_id' => $category->id,
        //             'region_id' => $rid,
        //         ])->all();

        //         CategoryRegion::insert($rows);
        //     }
        // });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully!');
    }

    public function destroy(Category $category)
    {
        if ($category->image && file_exists(public_path($category->image))) {
            unlink(public_path($category->image));
        }

        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }



    /**
     * Get all categories.
     */
    public function index_api()
    {
        $categories = Category::with(['channel', 'regions'])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully',
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel,
                    'regions' => $category->regions->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'region_code' => $r->region_code,
                        ];
                    }),
                    'category_image' => $category->category_image
                        ? asset($category->category_image)
                        : null,
                    'created_at' => $category->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }

    /**
     * GET /api/categories/region/{region?}
     */
    public function index_by_region_api(Request $request, $region = null)
    {
        try {
            // 1) Resolve region code
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL']; // include GLOBAL if you use it
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // 2) Build query
            $query = Category::select('id', 'name', 'slug', 'image', 'channel_id', 'created_at', 'updated_at')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                // ->with([
                //     'channel:id,name',
                //     'primary_color',
                //     'secondary_color',
                //     'accent_color',
                //     'background_color',
                //     'text_color',
                //     'hover_color',
                //     'highlight_color',
                //     'cta',
                //     'channel_category',
                //     'regions:id,region_code'
                // ])
                ->with([
                    'channel:id,name,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta,channel_category',
                    'regions:id,region_code'
                ])
                ->latest();

            if ($request->filled('channel_id')) {
                $query->where('channel_id', (int) $request->input('channel_id'));
            }

            $categories = $query->get();

            $data = $categories->map(function ($category) {
                if ($category->relationLoaded('regions')) {
                    $category->regions->each->makeHidden(['pivot']);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel ? [
                        'id' => $category->channel->id,
                        'name' => $category->channel->name,
                        'primary_color' => $category->channel->primary_color,
                        'secondary_color' => $category->channel->secondary_color,
                        'accent_color' => $category->channel->accent_color,
                        'background_color' => $category->channel->background_color,
                        'text_color' => $category->channel->text_color,
                        'hover_color' => $category->channel->hover_color,
                        'highlight_color' => $category->channel->highlight_color,
                        'cta' => $category->channel->cta,
                        'channel_category' => $category->channel->channel_category,
                    ] : null,
                    'regions' => $category->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'category_image' => $category->image ? asset($category->image) : null,
                    'created_at' => $category->created_at->toDateTimeString(),
                ];
            });




            return response()->json([
                'status' => true,
                'message' => 'Categories fetched successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index_by_region_api_pets(Request $request, $region = null)
    {
        try {
            $type = 'pet';
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // Retrieve user
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;

            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }

            $query = Category::select('id', 'name', 'slug', 'image', 'channel_id', 'created_at', 'updated_at')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->whereHas('channel', function ($q) use ($type) {
                    $q->where('channel_category', $type);
                })
                // ->with([
                //     'channel:id,name,channel_category,image,created_at,updated_at',
                //     'regions:id,region_code',
                // ])
                ->with([
                    'channel:id,name,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta,channel_category',
                    'regions:id,region_code'
                ])

                ->latest();

            if ($request->filled('channel_id')) {
                $query->where('channel_id', (int) $request->input('channel_id'));
            }

            $categories = $query->get();

            $data = $categories->map(function ($category) {
                if ($category->relationLoaded('regions')) {
                    $category->regions->each->makeHidden(['pivot']);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel ? [
                        'id' => $category->channel->id,
                        'name' => $category->channel->name,
                        'primary_color' => $category->channel->primary_color,
                        'secondary_color' => $category->channel->secondary_color,
                        'accent_color' => $category->channel->accent_color,
                        'background_color' => $category->channel->background_color,
                        'text_color' => $category->channel->text_color,
                        'hover_color' => $category->channel->hover_color,
                        'highlight_color' => $category->channel->highlight_color,
                        'cta' => $category->channel->cta,
                        'channel_category' => $category->channel->channel_category,
                    ] : null,
                    'regions' => $category->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'category_image' => $category->image ? asset($category->image) : null,
                    'created_at' => $category->created_at->toDateTimeString(),
                ];
            });



            // $user = Auth::user();
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;


            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }

            $recommended = collect();

            if ($user) {
                //     $recommended = Channel::query()
                //         ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                //         ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                //             $join->on('vwh.video_id', '=', 'videos.id')
                //                 ->where('vwh.user_id', '=', $user->id);
                //         })

                //         ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                //         ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                //         ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                //         ->when($type, fn($q) => $q->where('channels.channel_category', $type))
                //         ->whereNotNull('channels.id')
                //         // ->groupBy('channels.id', 'channels.name', 'channels.image', 'channels.created_at', 'channels.updated_at')
                //         ->groupBy(
                //             'channels.id',
                //             'channels.name',
                //             'channels.image',
                //             'channels.primary_color',
                //             'channels.secondary_color',
                //             'channels.accent_color',
                //             'channels.background_color',
                //             'channels.created_at',
                //             'channels.updated_at',
                //             'channels.channel_category'
                //         )
                //         ->select(
                //             'channels.*',
                //             DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                //             DB::raw('MAX(vwh.created_at) as last_watched_at')
                //         )
                //         ->orderByDesc('watch_count')
                //         ->orderByDesc('last_watched_at')
                //         ->limit(20)
                //         ->get()

                //         ->map(function ($ch) {
                //             return [
                //                 'id' => $ch->id,
                //                 'name' => $ch->name,
                //                 'image_url' => $ch->image_url,
                //                 'created_at' => optional($ch->created_at)?->toDateTimeString(),
                //                 'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                //                 'watch_count' => (int) $ch->watch_count,
                //             ];
                //         });
                // }
                $recommended = Channel::query()
                    ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                    ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                        $join->on('vwh.video_id', '=', 'videos.id')
                            ->where('vwh.user_id', '=', $user->id);
                    })
                    ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                    ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                    ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                    ->when($type, fn($q) => $q->where('channels.channel_category', $type))
                    ->whereNotNull('channels.id')
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at'
                    )
                    ->select(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at',
                        DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                        DB::raw('MAX(vwh.created_at) as last_watched_at')
                    )
                    ->orderByDesc('watch_count')
                    ->orderByDesc('last_watched_at')
                    ->limit(20)
                    ->get()
                    ->map(function ($ch) {
                        return [
                            'id' => $ch->id,
                            'name' => $ch->name,
                            'image_url' => $ch->image_url,
                            'primary_color' => $ch->primary_color,
                            'secondary_color' => $ch->secondary_color,
                            'accent_color' => $ch->accent_color,
                            'background_color' => $ch->background_color,
                            'text_color' => $ch->text_color,
                            'hover_color' => $ch->hover_color,
                            'highlight_color' => $ch->highlight_color,
                            'cta' => $ch->cta,
                            'channel_category' => $ch->channel_category,
                            'created_at' => optional($ch->created_at)?->toDateTimeString(),
                            'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                            'watch_count' => (int) $ch->watch_count,
                        ];
                    });
            }
            $sections = $this->getSubscriptionSectionsNew($request, $region ?? $request->input('region', ''), $type);
            $channelsByRegion = $this->getChannelsForRegionNew($request, $region ?? $request->input('region', ''), $type);
            $featuredResponse = $this->fetchReviewsByRegion($request, $regionCode, 1, $type);
            $featuredReviews = $featuredResponse->getData()->data ?? []; //previously i was getting all the data of audio and video reviews by now updated to videos
            // dd($featuredReviews);

            // $mostViewedResponse = $this->fetchMostViewedReviewsByRegion($request, $regionCode, 5, $type);
            $mostViewedResponse = $this->getProductReviewCharactersMostFollowed($request, $regionCode, $type);
            $mostViewedReviews = $mostViewedResponse->getData()->data ?? [];
            // $productReviewCharactersResponse = $this->getProductReviewCharacters2($request, $regionCode, $type); // it is showing review videos but all i want is the most followed character
            $productReviewCharactersResponse = $this->getProductReviewCharacters2($request, $regionCode, $type); // it is showing review videos but all i want is the most followed character
            $productReviewCharacters = $productReviewCharactersResponse->getData()->data ?? [];


            $scaleFinalScore = function ($row) {
                if (is_array($row)) {
                    if (array_key_exists('final_beastie_score', $row) && $row['final_beastie_score'] !== null) {
                        $row['final_beastie_score'] = round($row['final_beastie_score'] / 2, 1);
                    }
                    // if payload nests video like ['video' => [...]]
                    if (isset($row['video']['final_beastie_score'])) {
                        $row['video']['final_beastie_score'] = round($row['video']['final_beastie_score'] / 2, 1);
                    }
                } elseif (is_object($row)) {
                    if (isset($row->final_beastie_score) && $row->final_beastie_score !== null) {
                        $row->final_beastie_score = round($row->final_beastie_score / 2, 1);
                    }
                    if (isset($row->video) && isset($row->video->final_beastie_score)) {
                        $row->video->final_beastie_score = round($row->video->final_beastie_score / 2, 1);
                    }
                }
                return $row;
            };

            // Apply to blocks that contain review/video cards
            $featuredReviews   = collect($featuredReviews ?? [])->map($scaleFinalScore)->values();
            $mostViewedReviews = collect($mostViewedReviews ?? [])->map($scaleFinalScore)->values();

            if (isset($sections['top_deals'])) {
                $sections['top_deals'] = collect($sections['top_deals'])->map($scaleFinalScore)->values();
            }
            if (isset($sections['trending_products'])) {
                $sections['trending_products'] = collect($sections['trending_products'])->map($scaleFinalScore)->values();
            }
            return response()->json([
                'status' => true,
                'message' => 'Categories fetched successfully',
                'data' => [
                    'categories' => $data,
                    'recommended_channels' => $recommended,
                    'featured_reviews' => $featuredReviews,
                    'most_followed_reviews' => $mostViewedReviews,
                    'top_deals' => $sections['top_deals'],
                    'trending_products' => $sections['trending_products'],
                    'channels' => $channelsByRegion,
                    'meet_the_reviewers' => $productReviewCharacters,
                    // 'is_paid_user' => $sections['is_paid_user'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function index_by_region_api_people(Request $request, $region = null)
    {
        try {
            $type = 'people';
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            $query = Category::select('id', 'name', 'slug', 'image', 'channel_id', 'created_at', 'updated_at')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->whereHas('channel', function ($q) use ($type) {
                    $q->where('channel_category', $type);
                })
                // ->with([
                //     'channel:id,name,channel_category,image,created_at,updated_at',
                //     'regions:id,region_code',
                // ])
                ->with([
                    'channel:id,name,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta,channel_category',
                    'regions:id,region_code'
                ])

                ->latest('updated_at');

            if ($request->filled('channel_id')) {
                $query->where('channel_id', (int) $request->input('channel_id'));
            }

            $categories = $query->get();

            $data = $categories->map(function ($category) {
                if ($category->relationLoaded('regions')) {
                    $category->regions->each->makeHidden(['pivot']);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel ? [
                        'id' => $category->channel->id,
                        'name' => $category->channel->name,
                        'primary_color' => $category->channel->primary_color,
                        'secondary_color' => $category->channel->secondary_color,
                        'accent_color' => $category->channel->accent_color,
                        'background_color' => $category->channel->background_color,
                        'text_color' => $category->channel->text_color,
                        'hover_color' => $category->channel->hover_color,
                        'highlight_color' => $category->channel->highlight_color,
                        'cta' => $category->channel->cta,
                        'channel_category' => $category->channel->channel_category,
                    ] : null,
                    'regions' => $category->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'category_image' => $category->image ? asset($category->image) : null,
                    'created_at' => $category->created_at->toDateTimeString(),
                ];
            });



            // $user = Auth::user();
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }

            $recommended = collect();

            if ($user) {
                // $recommended = Channel::query()
                //     ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                //     ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                //         $join->on('vwh.video_id', '=', 'videos.id')
                //             ->where('vwh.user_id', '=', $user->id);
                //     })

                //     ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                //     ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                //     ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                //     ->when($type, fn($q) => $q->where('channels.channel_category', $type))
                //     ->whereNotNull('channels.id')
                //     // ->groupBy('channels.id', 'channels.name', 'channels.image', 'channels.created_at', 'channels.updated_at')
                //     ->groupBy(
                //         'channels.id',
                //         'channels.name',
                //         'channels.image',
                //         'channels.primary_color',
                //         'channels.secondary_color',
                //         'channels.accent_color',
                //         'channels.background_color',
                //         'channels.created_at',
                //         'channels.updated_at',
                //         'channels.channel_category'
                //     )
                //     ->select(
                //         'channels.*',
                //         DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                //         DB::raw('MAX(vwh.created_at) as last_watched_at')
                //     )
                //     ->orderByDesc('watch_count')
                //     ->orderByDesc('last_watched_at')
                //     ->limit(20)
                //     ->get()

                //     ->map(function ($ch) {
                //         return [
                //             'id' => $ch->id,
                //             'name' => $ch->name,
                //             'image_url' => $ch->image_url,
                //             'created_at' => optional($ch->created_at)?->toDateTimeString(),
                //             'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                //             'watch_count' => (int) $ch->watch_count,
                //         ];
                //     });
                $recommended = Channel::query()
                    ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                    ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                        $join->on('vwh.video_id', '=', 'videos.id')
                            ->where('vwh.user_id', '=', $user->id);
                    })
                    ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                    ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                    ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                    ->when($type, fn($q) => $q->where('channels.channel_category', $type))
                    ->whereNotNull('channels.id')
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at'
                    )
                    //->latest('videos.created_at')
                    ->select(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at',
                        DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                        DB::raw('MAX(vwh.created_at) as last_watched_at')
                    )
                    ->orderByDesc('watch_count')
                    ->orderByDesc('last_watched_at')
                    ->orderByDesc('channels.created_at') 
                    ->limit(20)
                    ->get()
                    ->map(function ($ch) {
                        return [
                            'id' => $ch->id,
                            'name' => $ch->name,
                            'image_url' => $ch->image_url,
                            'primary_color' => $ch->primary_color,
                            'secondary_color' => $ch->secondary_color,
                            'accent_color' => $ch->accent_color,
                            'background_color' => $ch->background_color,
                            'text_color' => $ch->text_color,
                            'hover_color' => $ch->hover_color,
                            'highlight_color' => $ch->highlight_color,
                            'cta' => $ch->cta,
                            'channel_category' => $ch->channel_category,
                            'created_at' => optional($ch->created_at)?->toDateTimeString(),
                            'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                            'watch_count' => (int) $ch->watch_count,
                        ];
                    });
            }
            $sections = $this->getSubscriptionSectionsNew($request, $region ?? $request->input('region', ''), $type);
            
            $channelsByRegion = $this->getChannelsForRegionNew($request, $region ?? $request->input('region', ''), $type);
            $featuredResponse = $this->fetchReviewsByRegion($request, $regionCode, 1, $type);
            $featuredReviews = $featuredResponse->getData()->data ?? [];
            // $mostViewedResponse = $this->fetchMostViewedReviewsByRegion($request, $regionCode, 5, $type);
            $mostViewedResponse = $this->getProductReviewCharactersMostFollowed($request, $regionCode, $type);
            
            $mostViewedReviews = $mostViewedResponse->getData()->data ?? [];
            $productReviewCharactersResponse = $this->getProductReviewCharacters($request, $regionCode, $type);
            $productReviewCharacters = $productReviewCharactersResponse->getData()->data ?? [];
            

            // Query for videos by region and type
            // $allvideos = Video::query()
            //     ->where('status', 'published')
            //     ->whereHas('regions', function ($q) use ($regionCode) {
            //         $q->where('region_code', $regionCode);
            //     })
            //     ->when($type, function ($q) use ($type) {
            //         $q->whereHas('channel', function ($c) use ($type) {
            //             $c->where('channel_category', $type);
            //         });
            //     })
            //     ->select('id', 'character_id','channel_id','category_id') // Fetch only video ID and character ID
            //     ->get();
            //     // dd($videos);

            // // Map the result to return the list of video IDs and character IDs
            // $productReviewCharacters = $allvideos->map(function ($video) {
            //     return [
            //         'video_id' => $video->id,
            //         'character_id' => $video->character_id,
            //         'channel_id' => $video->channel_id,
            //         'category_id' => $video->category_id,
            //     ];
            // });
            // dd($productReviewCharacters);



            // inside $scaleFinalScore in index_by_region_api_people

            $scaleFinalScore = function ($row) {
                $to5 = function ($v) {
                    if ($v === null || $v === '') return null;
                    if (!is_numeric($v)) return null;
                    return (int) round(((float) $v) / 2, 0); // 1–10 → 1–5
                };

                if (is_array($row)) {
                    
                    if (array_key_exists('final_beastie_score', $row)) {
                        $row['final_beastie_score'] = $to5($row['final_beastie_score']);
                    }
                    if (array_key_exists('final_beastiescore', $row)) {
                        $row['final_beastie_score'] = $to5($row['final_beastiescore']);
                    }

                    
                    if (array_key_exists('final_beastiee_score', $row)) {
                        $row['final_beastie_score'] = $to5($row['final_beastiee_score']);
                        unset($row['final_beastiee_score']);
                    }

                    // nested video
                    if (isset($row['video']) && is_array($row['video'])) {
                        if (array_key_exists('final_beastie_score', $row['video'])) {
                            $row['video']['final_beastie_score'] = $to5($row['video']['final_beastie_score']);
                        }
                        if (array_key_exists('final_beastiescore', $row['video'])) {
                            $row['video']['final_beastie_score'] = $to5($row['video']['final_beastiescore']);
                        }
                        
                        if (array_key_exists('final_beastiee_score', $row['video'])) {
                            $row['video']['final_beastie_score'] = $to5($row['video']['final_beastiee_score']);
                            unset($row['video']['final_beastiee_score']);
                        }
                    }
                    return $row;
                }

                if (is_object($row)) {
                    // existing mappings...
                    if (isset($row->final_beastie_score)) {
                        $row->final_beastie_score = $to5($row->final_beastie_score);
                    }
                    if (isset($row->final_beastiescore)) {
                        $row->final_beastie_score = $to5($row->final_beastiescore);
                    }

                    
                    if (isset($row->final_beastiee_score)) {
                        $row->final_beastie_score = $to5($row->final_beastiee_score);
                        unset($row->final_beastiee_score);
                    }

                    // nested video
                    if (isset($row->video) && is_object($row->video)) {
                        if (isset($row->video->final_beastie_score)) {
                            $row->video->final_beastie_score = $to5($row->video->final_beastie_score);
                        }
                        if (isset($row->video->final_beastiescore)) {
                            $row->video->final_beastie_score = $to5($row->video->final_beastiescore);
                        }
                        
                        if (isset($row->video->final_beastiee_score)) {
                            $row->video->final_beastie_score = $to5($row->video->final_beastiee_score);
                            unset($row->video->final_beastiee_score);
                        }
                    }
                    return $row;
                }

                return $row;
            };


            // apply (unchanged)
            $featuredReviews   = collect($featuredReviews ?? [])->map($scaleFinalScore)->values();
            $mostViewedReviews = collect($mostViewedReviews ?? [])->map($scaleFinalScore)->values();

            if (isset($sections['top_deals'])) {
                $sections['top_deals'] = collect($sections['top_deals'] ?? [])->map($scaleFinalScore)->values();
            }
            if (isset($sections['trending_products'])) {
                $sections['trending_products'] = collect($sections['trending_products'] ?? [])->map($scaleFinalScore)->values();
            }
                       

            return response()->json([
                'status' => true,
                'message' => 'Categories fetched successfully',
                'data' => [
                    'categories' => $data,
                    'recommended_channels' => $recommended,
                    'featured_reviews' => $featuredReviews,
                    'most_followed_reviews' => $mostViewedReviews,
                    'top_deals' => $sections['top_deals'],
                    'trending_products' => $sections['trending_products'],
                    'channels' => $channelsByRegion,
                    'meet_the_reviewers' => $productReviewCharacters,
                    // 'is_paid_user' => $sections['is_paid_user'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function index_by_region_api_categories_detail(Request $request, $region = null, $category_id)
    {
        try {
            // 1) Resolve region code
            $input = strtoupper($region ?? $request->input('region', ''));
            $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL']; // Allowable regions
            $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

            // 2) Fetch the category based on the provided category_id and region
            $query = Category::select('id', 'name', 'slug', 'image', 'channel_id', 'created_at', 'updated_at')
                ->where('id', $category_id) // Filter by category_id
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode); // Filter by region code
                })
                // ->with([
                //     'channel:id,name,image,channel_category,created_at,updated_at',
                //     'regions:id,region_code',
                // ])
                ->with([
                    'channel:id,name,image,primary_color,secondary_color,accent_color,background_color,text_color,hover_color,highlight_color,cta,channel_category,created_at,updated_at',
                    'regions:id,region_code'
                ])

                ->latest();

            // Optional: Filter by channel_id if provided
            if ($request->filled('channel_id')) {
                $query->where('channel_id', (int) $request->input('channel_id'));
            }

            // Execute the query to get the category
            $category = $query->first(); // Get only the specific category

            if (!$category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Category not found for the provided region.',
                ], 404);
            }
            // Fetch recommended channels if the user is authenticated
            $user = $request->user('api') ?? $request->user('sanctum') ?? null;
            // Check if the user is blocked
            if ($user && $user->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked. Please contact support.',
                ], 403); // Forbidden
            }
            $followed = 0;
            if ($user) {
                $followed = CategoryFollow::where('user_id', $user->id)
                    ->where('category_id', $category->id)
                    ->exists() ? 1 : 0;
            }

            // Prepare the category data
            $data = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'channel' => $category->channel,
                'regions' => $category->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ]),
                'category_image' => $category->image ? asset($category->image) : null,
                'created_at' => $category->created_at->toDateTimeString(),
                'followed' => $followed,
            ];

            $recommended = collect();

            if ($user) {
                // $recommended = Channel::query()
                //     ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                //     ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                //         $join->on('vwh.video_id', '=', 'videos.id')
                //             ->where('vwh.user_id', '=', $user->id);
                //     })
                //     ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                //     ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                //     ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                //     ->whereNotNull('channels.id')
                //     ->groupBy(
                //         'channels.id',
                //         'channels.name',
                //         'channels.image',
                //         'channels.primary_color',
                //         'channels.secondary_color',
                //         'channels.accent_color',
                //         'channels.background_color',
                //         'channels.created_at',
                //         'channels.updated_at'
                //     )
                //     ->select(
                //         'channels.*',
                //         DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                //         DB::raw('MAX(vwh.created_at) as last_watched_at')
                //     )
                //     ->orderByDesc('watch_count')
                //     ->orderByDesc('last_watched_at')
                //     ->limit(20)
                //     ->get()
                //     ->map(function ($ch) {
                //         return [
                //             'id' => $ch->id,
                //             'name' => $ch->name,
                //             'image_url' => $ch->image_url,
                //             'created_at' => optional($ch->created_at)?->toDateTimeString(),
                //             'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                //             'watch_count' => (int) $ch->watch_count,
                //         ];
                //     });
                $recommended = Channel::query()
                    ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                    ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                        $join->on('vwh.video_id', '=', 'videos.id')
                            ->where('vwh.user_id', '=', $user->id);
                    })
                    ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                    ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                    ->when($regionCode !== 'GLOBAL', fn($q) => $q->where('rr.region_code', $regionCode))
                    ->whereNotNull('channels.id')
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at'
                    )
                    ->select(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at',
                        DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                        DB::raw('MAX(vwh.created_at) as last_watched_at'),
                        DB::raw('AVG(videos.final_beastie_score) as avg_final_beastie_score')
                    )
                    ->orderByDesc('watch_count')
                    ->orderByDesc('last_watched_at')
                    ->limit(20)
                    ->get()
                    ->map(function ($ch) {
                        return [
                            'id' => $ch->id,
                            'name' => $ch->name,
                            'image_url' => $ch->image_url,
                            'primary_color' => $ch->primary_color,
                            'secondary_color' => $ch->secondary_color,
                            'accent_color' => $ch->accent_color,
                            'background_color' => $ch->background_color,
                            'text_color' => $ch->text_color,
                            'hover_color' => $ch->hover_color,
                            'highlight_color' => $ch->highlight_color,
                            'cta' => $ch->cta,
                            'channel_category' => $ch->channel_category,
                            'created_at' => optional($ch->created_at)?->toDateTimeString(),
                            'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                            'watch_count' => (int) $ch->watch_count,
                            'final_beastie_score' => round((float)$ch->avg_final_beastie_score, 2),
                        ];
                    });
            }

            // Get subscription sections and channels for the region
            // $sections = $this->getSubscriptionSections($request, $region ?? $request->input('region', ''));
            $sections = $this->getSubscriptionSectionsCategory($request, $region ?? $request->input('region', ''), $category_id);


            $channelsByRegion = $this->getChannelsForRegionCategory($request, $region ?? $request->input('region', ''), $category_id);

            return response()->json([
                'status' => true,
                'message' => 'Category details fetched successfully',
                'data' => $data,
                'recommended_channels' => $recommended,
                'top_deals' => $sections['top_deals'],
                'trending_products' => $sections['trending_products'],
                'is_paid_user' => $sections['is_paid_user'],
                'channels' => $channelsByRegion,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch category details',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function recommendedChannels(Request $request, $region = null)
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

            $recommended = collect();

            if ($user) {
                // Fetch recommended channels based on the user's watch history
                $recommended = Channel::query()
                    ->leftJoin('videos', 'videos.channel_id', '=', 'channels.id')
                    ->leftJoin('video_watch_histories as vwh', function ($join) use ($user) {
                        $join->on('vwh.video_id', '=', 'videos.id')
                            ->where('vwh.user_id', '=', $user->id);
                    })
                    ->leftJoin('channel_region as cr', 'cr.channel_id', '=', 'channels.id')
                    ->leftJoin('regions as rr', 'rr.id', '=', 'cr.region_id')
                    // ->select(
                    //     'channels.*',
                    //     DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                    //     DB::raw('MAX(vwh.created_at) as last_watched_at')
                    // )
                    // ->groupBy(
                    //     'channels.id',
                    //     'channels.name',
                    //     'channels.image',
                    //     'channels.primary_color',
                    //     'channels.secondary_color',
                    //     'channels.accent_color',
                    //     'channels.background_color',
                    //     'channels.text_color',
                    //     'channels.hover_color',
                    //     'channels.highlight_color',
                    //     'channels.cta',
                    //     'channels.created_at',
                    //     'channels.updated_at'
                    // )
                    ->select(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at',
                        DB::raw('COUNT(DISTINCT vwh.id) as watch_count'),
                        DB::raw('MAX(vwh.created_at) as last_watched_at')
                    )
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.text_color',
                        'channels.hover_color',
                        'channels.highlight_color',
                        'channels.cta',
                        'channels.channel_category',
                        'channels.created_at',
                        'channels.updated_at'
                    )
                    ->orderByDesc('watch_count')
                    ->orderByDesc('last_watched_at')
                    ->limit(20)
                    ->get();
            }

            return response()->json([
                'status' => true,
                'message' => 'Recommended channels fetched successfully',
                'recommended_channels' => $recommended,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch recommended channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    /**
     * Store a new category.
     */
    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
            'channel_id' => 'required|exists:channels,id',
            'category_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('category_image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->category_image->extension();
            $request->category_image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'category_image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Show category details.
     */
    public function show_api($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category details fetched successfully',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'channel_id' => $category->channel_id,
                'category_image' => $category->category_image
                    ? asset($category->category_image)
                    : null,
                'created_at' => $category->created_at->toDateTimeString(),
            ]
        ], 200);
    }

    /**
     * Update a category.
     */
    public function update_api(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'channel_id' => 'required|exists:channels,id',
            'category_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = $category->category_image;
        if ($request->hasFile('category_image')) {
            $folderPath = public_path('category');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $imageName = time() . '_' . uniqid() . '.' . $request->category_image->extension();
            $request->category_image->move($folderPath, $imageName);
            $imagePath = 'category/' . $imageName;

            if ($category->category_image && file_exists(public_path($category->category_image))) {
                unlink(public_path($category->category_image));
            }
        }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'channel_id' => $request->channel_id,
            'category_image' => $imagePath,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ], 200);
    }

    /**
     * Delete a category.
     */
    public function destroy_api($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
            ], 404);
        }

        try {
            if ($category->category_image && file_exists(public_path($category->category_image))) {
                unlink(public_path($category->category_image));
            }

            $category->delete();

            return response()->json([
                'status' => true,
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
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
    // protected function fetchReviewsByRegion(Request $request, $region, $isFeatured, ?string $type = null)
    // {
    //     try {
    //         // Normalize region code
    //         $regionCode = strtoupper($region);
    //         $allowedRegions = ['AU', 'CA', 'UK', 'US'];
    //         if (!in_array($regionCode, $allowedRegions)) {
    //             $regionCode = 'GLOBAL';
    //         }
    //         $user = $request->user('api') ?? $request->user('sanctum') ?? null;
    //         // Check if the user is blocked
    //         if ($user && $user->is_blocked) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Your account has been blocked. Please contact support.',
    //             ], 403); // Forbidden
    //         }
    //         $userId = $user?->id;
    //         $isSubscribed = $user && $this->hasValidSubscription($userId);

    //         $reviews = ProductReview::query()
    //             ->where('is_featured', $isFeatured)
    //             ->where('is_active', 1)
    //             ->with(['video', 'character.regions'])
    //             ->whereHas('character.regions', function ($q) use ($regionCode) {
    //                 $q->where('region_code', $regionCode);
    //             })
    //             ->when($type, function ($q) use ($type) {
    //                 $q->whereHas('video.channel', function ($ch) use ($type) {
    //                     $ch->where('channel_category', $type);
    //                 });
    //             })
    //             ->get()
    //             // ->map(function ($review) {
    //             ->map(function ($review) use ($isSubscribed) {
    //                 $video = $review->video;

    //                 $paidFlag = $video && $video->type === 'vimeo';

    //                 return [
    //                     "id" => $review->id,
    //                     "character_id" => $review->character_id,
    //                     "video_id" => $review->video_id,
    //                     "review_url" => $review->review_url,
    //                     "is_featured" => $review->is_featured,
    //                     "is_active" => $review->is_active,
    //                     "thumbnail_image" => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
    //                     "title" => $video?->title,
    //                     "description" => $video?->description,
    //                     "type" => $video?->type,
    //                     "video_url" => $video?->video_url,
    //                     "paid" => $paidFlag,
    //                     "is_subscribed" => $isSubscribed,
    //                     "created_at" => $review->created_at,
    //                     "updated_at" => $review->updated_at,
    //                 ];
    //             });

    //         return response()->json([
    //             'status' => true,
    //             'message' => $reviews->isEmpty()
    //                 ? ($isFeatured ? 'No featured product reviews found' : 'No product reviews found')
    //                 : ($isFeatured ? 'Featured product reviews fetched successfully' : 'All product reviews fetched successfully'),
    //             'data' => $reviews,
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch product reviews',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function fetchReviewsByRegion(Request $request, $region, $isFeatured = 1, ?string $type = null)
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
            $regionCode = strtoupper($region);
            $allowedRegions = ['AU', 'CA', 'UK', 'US'];
            if (!in_array($regionCode, $allowedRegions)) {
                $regionCode = 'GLOBAL';
            }

            // Build the query to fetch featured videos
            $q = $this->buildVideosQueryWithoutSubscription3($request, $regionCode, $type);
            $videos = $q->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found for the selected region and filters.',
                    'data' => [],
                ], 404);
            }

            // Attach tag pairs
            $this->attachTagPairs($videos);

            // Check if user is a paid user/subscribed
            $isPaidUser = $user && $this->hasValidSubscription($userId);

            // Map the video data to the desired structure
            $data = $videos->map(function ($video) use ($isPaidUser) {
                if ($video->relationLoaded('regions')) {
                    // Hide pivot data from regions if necessary
                    $video->regions->each->makeHidden(['pivot']);
                }

                // Check if the video is a paid video
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
                'message' => 'Featured videos fetched successfully',
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


    // protected function fetchMostViewedReviewsByRegion(Request $request, $region, $limit = 5, $type = null)
    // {
    //     try {
    //         $regionCode = strtoupper($region);
    //         $allowedRegions = ['AU', 'CA', 'UK', 'US'];
    //         if (!in_array($regionCode, $allowedRegions)) {
    //             $regionCode = 'GLOBAL';
    //         }

    //         $user = $request->user('api') ?? $request->user('sanctum') ?? null;
    //         // Check if the user is blocked
    //         if ($user && $user->is_blocked) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Your account has been blocked. Please contact support.',
    //             ], 403); // Forbidden
    //         }
    //         $userId = $user?->id;
    //         $isSubscribed = $user && $this->hasValidSubscription($userId);

    //         $reviews = ProductReview::query()
    //             ->where('is_active', 1)
    //             ->with(['video.channel', 'character.regions'])
    //             ->whereHas('character.regions', fn($q) => $q->where('region_code', $regionCode))
    //             ->when(
    //                 $type,
    //                 fn($q) =>
    //                 $q->whereHas('video.channel', fn($sub) => $sub->where('channel_category', $type))
    //             )
    //             ->orderBy('views', 'desc')
    //             ->limit($limit)
    //             ->get()
    //             ->map(function ($review) use ($isSubscribed) {
    //                 $video = $review->video;
    //                 $paidFlag = $video && $video->type === 'vimeo';

    //                 return [
    //                     "id" => $review->id,
    //                     "character_id" => $review->character_id,
    //                     "video_id" => $review->video_id,
    //                     "review_url" => $review->review_url,
    //                     "is_featured" => $review->is_featured,
    //                     "is_active" => $review->is_active,
    //                     "thumbnail_image" => $video?->thumbnail_image ? asset($video->thumbnail_image) : null,
    //                     "title" => $video?->title,
    //                     "description" => $video?->description,
    //                     "type" => $video?->type,
    //                     "video_url" => $video?->video_url,
    //                     "paid" => $paidFlag,
    //                     "is_subscribed" => $isSubscribed,
    //                     "views" => $review->views,
    //                     "created_at" => $review->created_at,
    //                     "updated_at" => $review->updated_at,
    //                 ];
    //             });

    //         return response()->json([
    //             'status' => true,
    //             'message' => $reviews->isEmpty()
    //                 ? 'No product reviews found for this region'
    //                 : 'Most viewed product reviews fetched successfully',
    //             'data' => $reviews,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch product reviews',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    protected function fetchMostViewedReviewsByRegion(Request $request, $region, $limit = 5, $type = null)
    {
        try {
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

            // Query for most viewed videos in the specified region and type
            $videos = Video::query()
                ->where('status', 'published')
                ->with(['channel', 'regions'])
                ->whereHas('regions', fn($q) => $q->where('region_code', $regionCode))
                ->when(
                    $type,
                    fn($q) => $q->whereHas('channel', fn($sub) => $sub->where('channel_category', $type))
                )
                ->orderBy('views', 'desc') // Order by views
                ->limit($limit)
                ->get()
                ->map(function ($video) use ($isSubscribed) {
                    $paidFlag = $video->type === 'vimeo'; // Check if the video is paid

                    return [
                        "id" => $video->id,
                        "character_id" => $video->character_id,
                        "video_id" => $video->id,
                        "title" => $video->title,
                        "description" => $video->description,
                        "type" => $video->type,
                        "video_url" => $video->video_url,
                        "thumbnail_image" => $video->thumbnail_image ? asset($video->thumbnail_image) : null,
                        "paid" => $paidFlag,
                        "is_subscribed" => $isSubscribed,
                        "views" => $video->views,
                        "created_at" => $video->created_at,
                        "updated_at" => $video->updated_at,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => $videos->isEmpty()
                    ? 'No videos found for this region'
                    : 'Most viewed videos fetched successfully',
                'data' => $videos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch videos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // previously using product review table
    // public function getProductReviewCharacters(Request $request, $region, $type)
    // {

    //     try {
    //         // Normalize region code
    //         $regionCode = strtoupper($region);
    //         $allowedRegions = ['AU', 'CA', 'UK', 'US'];
    //         if (!in_array($regionCode, $allowedRegions)) {
    //             $regionCode = 'GLOBAL';
    //         }

    //         $user = $request->user('api') ?? $request->user('sanctum') ?? null;
    //         // Check if the user is blocked
    //         if ($user && $user->is_blocked) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Your account has been blocked. Please contact support.',
    //             ], 403); // Forbidden
    //         }
    //         $userId = $user?->id;
    //         $isSubscribed = $user && $this->hasValidSubscription($userId);
    //         // Get distinct characters that have product reviews in this region
    //         $characters = Character::query()
    //             ->whereHas('productReviews', function ($q) {
    //                 $q->where('is_active', 1);
    //             })
    //             ->whereHas('regions', function ($q) use ($regionCode) {
    //                 $q->where('region_code', $regionCode);
    //             })
    //             ->when($type, function ($q) use ($type) {
    //                 $q->whereHas('category.channel', function ($c) use ($type) {
    //                     $c->where('channel_category', $type);
    //                 });
    //             })

    //             ->with([
    //                 'videos' => function ($q) use ($regionCode) {
    //                     $q->where('status', 'published')
    //                         ->whereHas('regions', fn($r) => $r->where('region_code', $regionCode));
    //                 }
    //             ])
    //             ->get()
    //             ->map(function ($character) use ($isSubscribed) {

    //                 $paidFlag = $character->videos->contains(fn($v) => $v->type === 'vimeo');

    //                 return [
    //                     "id" => $character->id,
    //                     "name" => $character->name,
    //                     "image" => $character->image ? asset($character->image) : null,
    //                     "character_page_url_slug" => $character->character_page_url_slug,
    //                     "persona" => $character->persona,
    //                     "details" => $character->details,
    //                     "category_id" => $character->category_id,
    //                     "paid" => $paidFlag,
    //                     "is_subscribed" => $isSubscribed,
    //                 ];
    //             });

    //         return response()->json([
    //             'status' => true,
    //             'message' => $characters->isEmpty()
    //                 ? 'No characters found for this region'
    //                 : 'Characters fetched successfully',
    //             'data' => $characters,
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to fetch characters',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }





    public function getProductReviewCharacters(Request $request, $region, $type)
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

            // Get distinct characters that have videos of the specified type in this region
            $characters = Character::query()
                ->whereHas('videos', function ($q) use ($regionCode, $type) {
                    // Filter videos by region and type
                    $q->where('status', 'published')
                        ->whereHas('regions', fn($r) => $r->where('region_code', $regionCode))
                        ->when($type, function ($q) use ($type) {
                            $q->whereHas('channel', function ($c) use ($type) {
                                $c->where('channel_category', $type);
                            });
                        });
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->with([
                    'videos' => function ($q) use ($regionCode, $type) {
                        $q->where('status', 'published')
                            ->whereHas('regions', fn($r) => $r->where('region_code', $regionCode))
                            ->when($type, function ($q) use ($type) {
                                $q->whereHas('channel', function ($c) use ($type) {
                                    $c->where('channel_category', $type);
                                });
                            });
                    }
                ])
                ->get()
                ->map(function ($character) use ($isSubscribed) {
                    // Check if any video is of type 'vimeo' (paid content)
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
            // dd( $characters, $region, $type);


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

    public function getProductReviewCharacters2(Request $request, $region, $type)
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

            // Step 1: Get all video IDs and unique character_ids
            $allvideos = Video::query()
                ->where('status', 'published')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('channel', function ($c) use ($type) {
                        $c->where('channel_category', $type);
                    });
                })
                ->select('id', 'character_id') // Fetch only video ID and character ID
                ->get();

            // Step 2: Extract unique character_ids from videos
            $characterIds = $allvideos->pluck('character_id')->unique();

            // Step 3: Fetch character details using the unique character_ids
            $characters = Character::query()
                ->whereIn('id', $characterIds) // Use the unique character IDs
                ->get()
                ->map(function ($character) use ($isSubscribed) {
                    // Check if any video is of type 'vimeo' (paid content)
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

    public function getProductReviewCharactersMostFollowed(Request $request, $region, $type)
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

            // Step 1: Get all video IDs and unique character_ids
            $allvideos = Video::query()
                ->where('status', 'published')
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('channel', function ($c) use ($type) {
                        $c->where('channel_category', $type);
                    });
                })
                ->select('id', 'character_id') // Fetch only video ID and character ID
                ->get();

            // Step 2: Extract unique character_ids from videos
            $characterIds = $allvideos->pluck('character_id')->unique();

            // Step 3: Fetch character details using the unique character_ids


            $characters = Character::query()
                ->whereIn('id', $characterIds)
                ->orderBy('character_popularity_score', 'desc')
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
}
