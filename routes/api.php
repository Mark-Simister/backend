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
    Route::post  ('characters',          [CharacterController::class, 'store_api']);
    Route::get   ('characters/{id}',     [CharacterController::class, 'show_api']);
    Route::post  ('characters/{id}',     [CharacterController::class, 'update_api']); // if you prefer PUT/PATCH:
    // Route::match(['put','patch'], '/characters/{id}', [CharacterController::class, 'update_api']);
    Route::delete('characters/{id}',     [CharacterController::class, 'destroy_api']);

    // Route::get('/plans', [SubscriptionListingController::class, 'index_api']);
    Route::post('/purchase', [BillingController::class, 'purchase']);
    Route::post('/subscriptions/{id}/cancel', [BillingController::class, 'cancel']);
    //Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook'); 
    // Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    // ->withoutMiddleware(['auth:api', 'auth:sanctum', ]) 
    // ->name('stripe.webhook.api');
    Route::post('purchase/confirm', [BillingController::class, 'confirm']);

   Route::get('videos/{region?}', [VideoController::class, 'index_api']);
    // Route::get('videos/free', [VideoController::class, 'freeVideos']);
    // Route::get('videos/paid', [VideoController::class, 'paidVideos']);
    Route::get('paid-videos/{region}', [VideoController::class, 'freeVideos']);
    Route::post('reviews', [ReviewController::class, 'store_api']);
    Route::get('my-reviews', [ReviewController::class, 'myReviews']);

    Route::get('users/{id}', [UserController::class, 'show_api'])->whereNumber('id');
    Route::get('me/profile', [UserController::class, 'me_api']);


});

// API's For Guest User
Route::get('regions', [RegionController::class, 'index_api']);
Route::get('channels', [ChannelController::class, 'index_api']);
Route::get('/channels/region/{region?}', [ChannelController::class, 'index_by_region_api']);
Route::get('/region/{region?}', [ChannelController::class, 'filter_region_api']);
Route::get('categories', [CategoryController::class, 'index_api']);
Route::get('/categories/region/{region?}', [CategoryController::class, 'index_by_region_api']);
Route::match(['GET','POST'], '/channels/by-region', [ChannelController::class, 'index_by_region_api']);
Route::get   ('characters',          [CharacterController::class, 'index_api']);
Route::get('/characters/region/{region?}', [CharacterController::class, 'index_by_region_api']);

Route::get('character-tags', [CharacterTagController::class, 'index_api']);
Route::get('character-roles', [CharacterRoleController::class, 'index_api']);
Route::get('/plans', [SubscriptionListingController::class, 'index_api']);


Route::get('free-videos/{region}', [VideoController::class, 'freeVideos']);
    
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
                'ip'           => $j['ip'] ?? $ip,
                'country_code' => strtoupper($j['country_code']),
                'source'       => 'ipwho.is',
            ]);
        }
    } catch (\Throwable $e) {}

    try {
        $r2 = Http::timeout(5)->retry(2, 200)->get("https://ipapi.co/{$ip}/json/");
        $j2 = $r2->json();
        if (!empty($j2['country'])) {
            return response()->json([
                'ip'           => $j2['ip'] ?? $ip,
                'country_code' => strtoupper($j2['country']),
                'source'       => 'ipapi.co',
            ]);
        }
    } catch (\Throwable $e) {}

    return response()->json([
        'ip'           => $ip,
        'country_code' => 'UNKNOWN',
        'source'       => 'fallback',
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
    $source  = 'fallback';

    // Try ipwho.is
    try {
        $r = Http::timeout(5)->retry(2, 200)->get("https://ipwho.is/{$ip}");
        $j = $r->json();
        if (!empty($j['success']) && !empty($j['country_code'])) {
            $country = strtoupper($j['country_code']);
            $source  = 'ipwho.is';
        }
    } catch (\Throwable $e) {}

    // Fallback to ipapi.co
    if (!$country) {
        try {
            $r2 = Http::timeout(5)->retry(2, 200)->get("https://ipapi.co/{$ip}/json/");
            $j2 = $r2->json();
            if (!empty($j2['country'])) {
                $country = strtoupper($j2['country']);
                $source  = 'ipapi.co';
            }
        } catch (\Throwable $e) {}
    }

    // Normalize GB -> UK for your URLs
    if ($country === 'GB') $country = 'UK';

    // Choose URL:
    // - US → us subdomain
    // - AU/CA/UK → their subdomains
    // - Anything else → global root
    $url = $map[$country] ?? $globalRoot;

    // Extra safety: ensure URL is only one of the allowed ones
    if (!in_array($url, $allowed, true)) {
        $url = $globalRoot;
    }

    return response()->json([
        'ip'           => $ip,
        'country_code' => $country ?? 'UNKNOWN',
        'url'          => $url,
        'source'       => $source,
        'region'       => $url === $globalRoot ? 'GLOBAL' : ($country ?? 'UNKNOWN'),
    ]);
});






// Catch-all for undefined API routes
Route::any('{any}', function () {
    return response()->json([
        'message' => 'The requested API route could not be found.'
    ], 404);
})->where('any', '.*');
    