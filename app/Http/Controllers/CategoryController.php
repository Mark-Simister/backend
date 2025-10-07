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
                ->with([
                    'channel:id,name',
                    'regions:id,region_code'
                ])
                ->latest();

            if ($request->filled('channel_id')) {
                $query->where('channel_id', (int) $request->input('channel_id'));
            }

            $categories = $query->get();

            $data = $categories->map(function ($category) {
                // remove pivot keys
                if ($category->relationLoaded('regions')) {
                    $category->regions->each->makeHidden(['pivot']);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'channel' => $category->channel,
                    'regions' => $category->regions->map(fn($r) => [
                        'id' => $r->id,
                        'region_code' => $r->region_code,
                    ]),
                    'category_image' => $category->image
                        ? asset($category->image)
                        : null,
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
                ->with([
                    'channel:id,name,channel_category,image,created_at,updated_at',
                    'regions:id,region_code',
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

                // Ensure channel hides raw image and exposes image_url
                if ($category->relationLoaded('channel') && $category->channel) {
                    $category->channel->makeHidden(['image']);
                    $category->channel->append(['image_url']);
                }

                return [
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
                    // ->groupBy('channels.id', 'channels.name', 'channels.image', 'channels.created_at', 'channels.updated_at')
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.created_at',
                        'channels.updated_at',
                        'channels.channel_category'
                    )
                    ->select(
                        'channels.*',
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
            $mostViewedResponse = $this->fetchMostViewedReviewsByRegion($request, $regionCode, 5, $type);
            $mostViewedReviews = $mostViewedResponse->getData()->data ?? [];
            $productReviewCharactersResponse = $this->getProductReviewCharacters($request, $regionCode, $type);
            $productReviewCharacters = $productReviewCharactersResponse->getData()->data ?? [];

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
                ->with([
                    'channel:id,name,channel_category,image,created_at,updated_at',
                    'regions:id,region_code',
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

                // Ensure channel hides raw image and exposes image_url
                if ($category->relationLoaded('channel') && $category->channel) {
                    $category->channel->makeHidden(['image']);
                    $category->channel->append(['image_url']);
                }

                return [
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
                    // ->groupBy('channels.id', 'channels.name', 'channels.image', 'channels.created_at', 'channels.updated_at')
                    ->groupBy(
                        'channels.id',
                        'channels.name',
                        'channels.image',
                        'channels.primary_color',
                        'channels.secondary_color',
                        'channels.accent_color',
                        'channels.background_color',
                        'channels.created_at',
                        'channels.updated_at',
                        'channels.channel_category'
                    )
                    ->select(
                        'channels.*',
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
            $mostViewedResponse = $this->fetchMostViewedReviewsByRegion($request, $regionCode, 5, $type);
            $mostViewedReviews = $mostViewedResponse->getData()->data ?? [];
            $productReviewCharactersResponse = $this->getProductReviewCharacters($request, $regionCode, $type);
            $productReviewCharacters = $productReviewCharactersResponse->getData()->data ?? [];




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
                ->with([
                    'channel:id,name,image,created_at,updated_at',
                    'regions:id,region_code',
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
                        'channels.created_at',
                        'channels.updated_at'
                    )
                    ->select(
                        'channels.*',
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
                            'created_at' => optional($ch->created_at)?->toDateTimeString(),
                            'updated_at' => optional($ch->updated_at)?->toDateTimeString(),
                            'watch_count' => (int) $ch->watch_count,
                        ];
                    });
            }

            // Get subscription sections and channels for the region
            $sections = $this->getSubscriptionSections($request, $region ?? $request->input('region', ''));
            $channelsByRegion = $this->getChannelsForRegion($request, $region ?? $request->input('region', ''));

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
                    ->select(
                        'channels.*',
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
    protected function fetchReviewsByRegion(Request $request, $region, $isFeatured, ?string $type = null)
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
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('video.channel', function ($ch) use ($type) {
                        $ch->where('channel_category', $type);
                    });
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

            $reviews = ProductReview::query()
                ->where('is_active', 1)
                ->with(['video.channel', 'character.regions'])
                ->whereHas('character.regions', fn($q) => $q->where('region_code', $regionCode))
                ->when(
                    $type,
                    fn($q) =>
                    $q->whereHas('video.channel', fn($sub) => $sub->where('channel_category', $type))
                )
                ->orderBy('views', 'desc')
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
            // Get distinct characters that have product reviews in this region
            $characters = Character::query()
                ->whereHas('productReviews', function ($q) {
                    $q->where('is_active', 1);
                })
                ->whereHas('regions', function ($q) use ($regionCode) {
                    $q->where('region_code', $regionCode);
                })
                ->when($type, function ($q) use ($type) {
                    $q->whereHas('category.channel', function ($c) use ($type) {
                        $c->where('channel_category', $type);
                    });
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
}