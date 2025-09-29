<?php

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterTagController;
use App\Http\Controllers\CharacterRoleController;
use App\Http\Controllers\SubscriptionListingController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\VideoEngagementController;
use App\Http\Controllers\GlobalColorController;
use App\Http\Controllers\ProductMessageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\FormController;
// use App\Http\Controllers\VimeoController;

use Illuminate\Support\Facades\Http;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('otp/resend', [AuthController::class, 'resendOtp']);
Route::post('otp/verify', [AuthController::class, 'verifyOtp']);

Route::middleware(['auth:api'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Categories list API
    Route::prefix('categories')->group(function () {
        // Route::get('/', [CategoryController::class, 'index_api']);
        Route::post('/', [CategoryController::class, 'store_api']);
        Route::get('/{category}', [CategoryController::class, 'show_api']);
        Route::put('/{category}', [CategoryController::class, 'update_api']);
        Route::delete('/{category}', [CategoryController::class, 'destroy_api']);
    });
    Route::prefix('regions')->group(function () {
        // Route::get('/', [RegionController::class, 'index_api']);
        Route::post('/', [RegionController::class, 'store_api']);
        Route::get('/{id}', [RegionController::class, 'show_api']);
        Route::put('/{id}', [RegionController::class, 'update_api']);
        Route::delete('/{id}', [RegionController::class, 'destroy_api']);
    });

    // Channels list API
    // Route::get('channels', [ChannelController::class, 'index_api']);
    Route::post('channels', [ChannelController::class, 'store_api']);
    Route::get('channels/{channel}', [ChannelController::class, 'show_api']);
    Route::put('channels/{channel}', [ChannelController::class, 'update_api']);
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy_api']);



    // Character Tags API
    // Route::get('character-tags', [CharacterTagController::class, 'index_api']);
    Route::post('character-tags', [CharacterTagController::class, 'store_api']);
    Route::get('character-tags/{characterTag}', [CharacterTagController::class, 'show_api']);
    Route::put('character-tags/{characterTag}', [CharacterTagController::class, 'update_api']);
    Route::delete('character-tags/{characterTag}', [CharacterTagController::class, 'destroy_api']);

    // Character Roles API
    // Route::get('character-roles', [CharacterRoleController::class, 'index_api']);
    Route::post('character-roles', [CharacterRoleController::class, 'store_api']);
    Route::get('character-roles/{characterRole}', [CharacterRoleController::class, 'show_api']);
    Route::put('character-roles/{characterRole}', [CharacterRoleController::class, 'update_api']);
    Route::delete('character-roles/{characterRole}', [CharacterRoleController::class, 'destroy_api']);

    // Route::get   ('/characters',          [CharacterController::class, 'index_api']);
    Route::post('characters', [CharacterController::class, 'store_api']);
    // Route::get   ('characters/{id}',     [CharacterController::class, 'show_api']);
    Route::post('characters/{id}', [CharacterController::class, 'update_api']); // if you prefer PUT/PATCH:
    // Route::match(['put','patch'], '/characters/{id}', [CharacterController::class, 'update_api']);
    Route::delete('characters/{id}', [CharacterController::class, 'destroy_api']);

    Route::post('/purchase/region/{region}', [BillingController::class, 'purchase']);

    Route::post('/subscriptions/{id}/cancel', [BillingController::class, 'cancel']);
    //Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook'); 
    // Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    // ->withoutMiddleware(['auth:api', 'auth:sanctum', ]) 
    // ->name('stripe.webhook.api');
    Route::post('purchase/confirm', [BillingController::class, 'confirm']);

    Route::get('videos/{region?}', [VideoController::class, 'index_api']);
    Route::get('paid-videos/{region}', [VideoController::class, 'paidVideos']);
    Route::get('paid-videos-trending/{region}', [VideoController::class, 'paidVideosTrending']);
    Route::get('paid-videos-top-deals/{region}', [VideoController::class, 'paidVideosTopDeals']);
    Route::get('paid-trending-characters/{region}', [VideoController::class, 'charactersFromPaidVideos']);
    // Route::get('/paid-videos/{region}/{id}', [VideoController::class, 'paidVideosDetail']);
    Route::get('/paid-videos/{region}/{id}', [VideoController::class, 'paidVideosDetail'])
    ->name('paidVideosDetail');
    Route::get('recommended-videos/{region?}', [VideoController::class, 'recommendedVideos']);



    Route::post('reviews', [ReviewController::class, 'store_api']);
    Route::get('my-reviews', [ReviewController::class, 'myReviews']);    

    Route::get('users/{id}', [UserController::class, 'show_api'])->whereNumber('id');
    Route::get('me/profile', [UserController::class, 'me_api']);
    Route::post('/profile/update', [UserController::class, 'update_api']);

    // VideoEngagements

    // Likes
    Route::post('videos/{video}/like', [VideoEngagementController::class, 'like'])->name('api.videos.like');
    Route::delete('videos/{video}/unlike', [VideoEngagementController::class, 'unlike'])->name('api.videos.unlike');
    Route::get('/videos-check/{video}/likes-count', [VideoEngagementController::class, 'likesCount']);

    // Favourites
    Route::post('videos/{video}/favorite', [VideoEngagementController::class, 'favorite'])->name('api.videos.favorite');
    Route::delete('videos/{video}/favorite', [VideoEngagementController::class, 'unfavorite'])->name('api.videos.unfavorite');

    // Watch history 
    Route::post('videos/{video}/watch', [VideoEngagementController::class, 'recordWatch'])->name('api.videos.watch');
    Route::post('video/{videoId}/update-watch-history', [VideoEngagementController::class, 'updateWatchHistory']); 


    //categories follow
    Route::post('/categories/{categoryId}/follow', [VideoEngagementController::class, 'followCategory']);
    Route::get('/followed-categories', [VideoEngagementController::class, 'listFollowedCategories']);

    Route::post('/product-review/{id}/view', [VideoEngagementController::class, 'trackView']);

    Route::post('/channels/{id}/follow', [ChannelController::class, 'follow'])->name('channels.follow');
    Route::get('recommended-channels/{region?}', [CategoryController::class, 'recommendedChannels']);

    //  Route::delete('/channels/{id}/unfollow', [FollowChannelController::class, 'unfollow'])->name('channels.unfollow');

    Route::get('/follows', [ChannelController::class, 'listFollows'])->name('channels.follows');

    // Lists (paginated)
    Route::get('me/likes', [VideoEngagementController::class, 'myLikes'])->name('api.me.likes');
    Route::get('me/favourites', [VideoEngagementController::class, 'myFavourites'])->name('api.me.favourites');
    Route::get('me/last-watched', [VideoEngagementController::class, 'myLastWatched'])->name('api.me.last_watched');

    // Likes
    Route::get('/me/likes', [VideoEngagementController::class, 'myLikes'])->name('api.me.likes');

    // Favourites
    Route::get('/me/favourites', [VideoEngagementController::class, 'myFavourites'])->name('api.me.favourites');

    // Last watched videos
    Route::get('/my-watch-histories', [VideoEngagementController::class, 'myWatchHistories'])->name('videos.watch_histories');

});

// API's For Guest User
Route::get('regions', [RegionController::class, 'index_api']);
Route::get('channels', [ChannelController::class, 'index_api']);
Route::get('/channels/region/{region?}', [ChannelController::class, 'index_by_region_api']);
Route::get('/channels-people/region/{region?}', [ChannelController::class, 'index_people_by_region_api']);
Route::get('/channels-pets/region/{region?}', [ChannelController::class, 'index_pets_by_region_api']);

Route::get('global-colors', [GlobalColorController::class, 'index_api']);
Route::post('/product-messages', [ProductMessageController::class, 'store']);


Route::get(
    '/channel-detail/{channel}/{region?}',
    [ChannelController::class, 'showChannelDetailsByRegion']
)->name('channels.details');

Route::get('/region/{region?}', [ChannelController::class, 'filter_region_api']);
Route::get('/region_new/{region?}', [ChannelController::class, 'filter_region_api_new']);
Route::get('categories', [CategoryController::class, 'index_api']);
// Route::get('/categories/region/{region?}', [CategoryController::class, 'index_by_region_api']);
Route::get('/categories/region/{region?}', [CategoryController::class, 'index_by_region_api'])->name('categories.byRegion');
Route::get('/categories-pet/region/{region?}', [CategoryController::class, 'index_by_region_api_pets']);
Route::get('/categories-people/region/{region?}', [CategoryController::class, 'index_by_region_api_people']);
Route::get('/categories-detail/region/{region?}/{category_id}', [CategoryController::class, 'index_by_region_api_categories_detail']);

Route::match(['GET', 'POST'], '/channels/by-region', [ChannelController::class, 'index_by_region_api']);
Route::get('characters', [CharacterController::class, 'index_api']);
Route::get('characters/{id}', [CharacterController::class, 'show_api']);
// Route::get('/characters/{id}/with-videos/{region}', [CharacterController::class, 'showWithVideos']);
Route::get('/character-detail/{id}/{region}', [CharacterController::class, 'showWithVideos'])->name('characters.withVideos');
Route::get('/character-details/{id}/{region}', [CharacterController::class, 'showWithVideosNew'])->name('characters.withVideosNew');
Route::get('/product-reviews/region/{region}', [CharacterController::class, 'getAllProductReviews']);
Route::get('/featured-reviews/region/{region}', [CharacterController::class, 'getAllFeaturedReviews']);
Route::get('/latest-product-reviews/region/{region}', [CharacterController::class, 'getLatestProductReviews']);
Route::get('/most-followed-product-reviews/region/{region}', [CharacterController::class, 'getMostViewedProductReviews']);
Route::get('/meet-the-reviewers/region/{region}', [CharacterController::class, 'getProductReviewCharacters']);

Route::get('/characters/region/{region?}', [CharacterController::class, 'index_by_region_api']);

Route::get('character-tags', [CharacterTagController::class, 'index_api']);
Route::get('character-roles', [CharacterRoleController::class, 'index_api']);
Route::get('/plans', [SubscriptionListingController::class, 'index_api']);

Route::get('free-videos/{region}', [VideoController::class, 'freeVideos']);
Route::get('free-videos-trending/{region}', [VideoController::class, 'freeVideosTrending']);
Route::get('free-videos-top-deals/{region}', [VideoController::class, 'freeVideosTopDeals']);
Route::get('free-trending-characters/{region}', [VideoController::class, 'charactersFromVideos']);
Route::get('/free-videos/{region}/{id}', [VideoController::class, 'freeVideosDetail']);


// All in one
Route::get('/trending-videos/{region}', [VideoController::class, 'trendingVideos']);
Route::get('/top-deals/{region}', [VideoController::class, 'topDeals']);
Route::get('/top-rated-products/{region}', [VideoController::class, 'topRatedProducts']);
Route::get('/trending-characters/{region}', [VideoController::class, 'charactersFromVideosAndPaid']);
Route::get('/videos-detail/{region}/{id}', [VideoController::class, 'allVideosDetail']);
Route::get('/top-this-week/{region}', [VideoController::class, 'trendingVideos']);
Route::get('/hot-this-week/{region}', [VideoController::class, 'hotThisWeek']);

// Route::get('/subscription-listings/by-region/{region?}', [SubscriptionListingController::class, 'index_region_api']);
Route::get('/subscription-listings/region/{region}', [SubscriptionListingController::class, 'index_region_api']);

Route::prefix('videos/{video}')->group(function () {
    Route::get('/comments', [CommentController::class, 'index']);
    Route::middleware('auth:api')->post('/comments', [CommentController::class, 'store']);
});

Route::get('/comments/{comment}', [CommentController::class, 'show']);
Route::get('/comments/{comment}/thread', [CommentController::class, 'thread']);
Route::patch('/comments/{comment}', [CommentController::class, 'update']);
Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
// Route::get('advanced-search', [SearchController::class, 'advancedSearch']);
// In routes/api.php
Route::get('advanced-search/region/{region}', [SearchController::class, 'advancedSearch']);

Route::post('/subscribe', [NewsletterController::class, 'subscribe_api']);
Route::get('/newsletter-subscriptions', [NewsletterController::class, 'index_api']);

// forms
Route::get('forms', [FormController::class, 'apiIndex']);
Route::post('forms/{form}/submit', [FormController::class, 'apiSubmit']);



Route::get('/my-country', function (Request $request) {
    // Try real client IP from common proxy/CDN headers, else fallback to Laravel's IP.
    $ip = $request->header('CF-Connecting-IP')
        ?? ($request->header('X-Forwarded-For') ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) : null)
        ?? $request->header('X-Real-IP')
        ?? $request->ip();

    // Query a public IP geo API with *that* client IP.
    try {
        $r = Http::timeout(5)->retry(2, 200)->get("https://ipwho.is/{$ip}");
        $j = $r->json();
        if (!empty($j['success']) && !empty($j['country_code'])) {
            return response()->json([
                'ip' => $j['ip'] ?? $ip,
                'country_code' => strtoupper($j['country_code']),
                'source' => 'ipwho.is',
            ]);
        }
    } catch (\Throwable $e) {
    }

    try {
        $r2 = Http::timeout(5)->retry(2, 200)->get("https://ipapi.co/{$ip}/json/");
        $j2 = $r2->json();
        if (!empty($j2['country'])) {
            return response()->json([
                'ip' => $j2['ip'] ?? $ip,
                'country_code' => strtoupper($j2['country']),
                'source' => 'ipapi.co',
            ]);
        }
    } catch (\Throwable $e) {
    }

    return response()->json([
        'ip' => $ip,
        'country_code' => 'UNKNOWN',
        'source' => 'fallback',
    ]);
});


Route::get('/my-country-get', function (Request $request) {
    // Explicit region → URL map
    $map = [
        'AU' => 'https://au.fstg.beastierated.com/',
        'CA' => 'https://ca.fstg.beastierated.com/',
        'UK' => 'https://uk.fstg.beastierated.com/',
        'US' => 'https://us.fstg.beastierated.com/',
    ];

    // Global/root URL for ALL non-mapped countries
    $globalRoot = 'https://fstg.beastierated.com/';

    // Allowed URLs (for safety)
    $allowed = array_values($map);
    $allowed[] = $globalRoot;

    // Detect client IP (prefer proxy/CDN headers)
    $ip = $request->header('CF-Connecting-IP')
        ?? ($request->header('X-Forwarded-For') ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) : null)
        ?? $request->header('X-Real-IP')
        ?? $request->ip();

    $country = null;
    $source = 'fallback';

    // Try ipwho.is
    try {
        $r = Http::timeout(5)->retry(2, 200)->get("https://ipwho.is/{$ip}");
        $j = $r->json();
        if (!empty($j['success']) && !empty($j['country_code'])) {
            $country = strtoupper($j['country_code']);
            $source = 'ipwho.is';
        }
    } catch (\Throwable $e) {
    }

    // Fallback to ipapi.co
    if (!$country) {
        try {
            $r2 = Http::timeout(5)->retry(2, 200)->get("https://ipapi.co/{$ip}/json/");
            $j2 = $r2->json();
            if (!empty($j2['country'])) {
                $country = strtoupper($j2['country']);
                $source = 'ipapi.co';
            }
        } catch (\Throwable $e) {
        }
    }

    // Normalize GB -> UK for your URLs
    if ($country === 'GB')
        $country = 'UK';


    $url = $map[$country] ?? $globalRoot;

    // Extra safety: ensure URL is only one of the allowed ones
    if (!in_array($url, $allowed, true)) {
        $url = $globalRoot;
    }

    return response()->json([
        'ip' => $ip,
        'country_code' => $country ?? 'UNKNOWN',
        'url' => $url,
        'source' => $source,
        'region' => $url === $globalRoot ? 'GLOBAL' : ($country ?? 'UNKNOWN'),
    ]);
});






// Catch-all for undefined API routes
Route::any('{any}', function () {
    return response()->json([
        'message' => 'The requested API route could not be found.'
    ], 404);
})->where('any', '.*');
