<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CharacterTagController;
use App\Http\Controllers\CharacterRoleController;
use App\Http\Controllers\SubscriptionListingController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\StripeWebhookController;


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
        Route::get('/', [CategoryController::class, 'index_api']);
        Route::post('/', [CategoryController::class, 'store_api']);
        Route::get('/{category}', [CategoryController::class, 'show_api']);
        Route::put('/{category}', [CategoryController::class, 'update_api']);
        Route::delete('/{category}', [CategoryController::class, 'destroy_api']);
    });

    // Channels list API
    Route::get('channels', [ChannelController::class, 'index_api']);
    Route::post('channels', [ChannelController::class, 'store_api']);
    Route::get('channels/{channel}', [ChannelController::class, 'show_api']);
    Route::put('channels/{channel}', [ChannelController::class, 'update_api']);
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy_api']);

    // Character Tags API
    Route::get('character-tags', [CharacterTagController::class, 'index_api']);
    Route::post('character-tags', [CharacterTagController::class, 'store_api']);
    Route::get('character-tags/{characterTag}', [CharacterTagController::class, 'show_api']);
    Route::put('character-tags/{characterTag}', [CharacterTagController::class, 'update_api']);
    Route::delete('character-tags/{characterTag}', [CharacterTagController::class, 'destroy_api']);

    // Character Roles API
    Route::get('character-roles', [CharacterRoleController::class, 'index_api']);
    Route::post('character-roles', [CharacterRoleController::class, 'store_api']);
    Route::get('character-roles/{characterRole}', [CharacterRoleController::class, 'show_api']);
    Route::put('character-roles/{characterRole}', [CharacterRoleController::class, 'update_api']);
    Route::delete('character-roles/{characterRole}', [CharacterRoleController::class, 'destroy_api']);

    Route::get('/plans', [SubscriptionListingController::class, 'index_api']);
    Route::post('/purchase', [BillingController::class, 'purchase']);
    Route::post('/subscriptions/{id}/cancel', [BillingController::class, 'cancel']);
    //Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook'); 
    // Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    // ->withoutMiddleware(['auth:api', 'auth:sanctum', ]) 
    // ->name('stripe.webhook.api');
    Route::post('purchase/confirm', [BillingController::class, 'confirm']);

    // Catch-all for undefined API routes
    Route::any('{any}', function () {
        return response()->json([
            'message' => 'The requested API route could not be found.'
        ], 404);
    })->where('any', '.*');

});