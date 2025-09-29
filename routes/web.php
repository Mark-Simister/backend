<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CharacterTagController;
use App\Http\Controllers\CharacterRoleController;
use App\Http\Controllers\HighlightTagController;
use App\Http\Controllers\SubscriptionListingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\VimeoController;
use App\Http\Controllers\GlobalColorController;
use App\Http\Controllers\AffiliateLinkController;
use App\Http\Controllers\ProductMessageController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\BlooperController;
use App\Http\Controllers\CharacterInsightController;
use App\Http\Controllers\SimilarProductController;
use Illuminate\Support\Facades\Artisan;


// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return redirect()->route('login');
});



Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Category CRUD
        Route::resource('categories', CategoryController::class)
            ->middleware('permission:category.view|category.create|category.edit|category.delete');

        // Permissions management (restrict to super_admin only if you want)
        Route::resource('permissions', PermissionController::class)
            ->middleware('role:super_admin');

        // Channels
        Route::resource('channels', ChannelController::class)
            ->middleware('permission:channel.view|channel.create|channel.edit|channel.delete');

        Route::resource('regions', RegionController::class)
            ->middleware('permission:region.view|region.create|region.edit|region.delete');

        Route::resource('global-colors', GlobalColorController::class);

        // Character tags
        Route::resource('character_tags', CharacterTagController::class)
            ->middleware('permission:character_tag.view|character_tag.create|character_tag.edit|character_tag.delete');

        // Character roles
        Route::resource('character_roles', CharacterRoleController::class)
            ->middleware('permission:character_role.view|character_role.create|character_role.edit|character_role.delete');

        // Characters
        Route::resource('characters', CharacterController::class)
            ->middleware('permission:character.view|character.create|character.edit|character.delete');

        // Videos
        Route::resource('videos', VideoController::class)
            ->middleware('permission:video.view|video.create|video.edit|video.delete');
        Route::get('/videos/{id}/comments', [VideoController::class, 'showCommentsPage'])->name('videos.comments');
        Route::delete('/comments/{comment}', [VideoController::class, 'destroy_comment'])->name('comments.delete');
        Route::put('/comments/{comment}', [VideoController::class, 'update_comment'])->name('comments.update');
        Route::delete('/replies/{reply}', [VideoController::class, 'deleteReply'])->name('replies.delete');


        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::get('/tags/by-ids', [TagController::class, 'byIds'])
            ->name('tags.byIds');

        // Route for managing affiliate links (View all links for a video)
        Route::get('/videos/{videoId}/affiliate-links', [VideoController::class, 'manageLinks'])->name('videos.affiliate-links');

        // Route for storing a new affiliate link (POST method)
// Route::post('/videos/{videoId}/affiliate-links', [AffiliateLinkController::class, 'store'])->name('videos.store-affiliate-link');
    
        // Route for deleting an affiliate link (DELETE method)
        Route::delete('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'destroy'])->name('videos.affiliateLinks.destroy');

        // Route for updating an existing affiliate link (PATCH method)
// Route::patch('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'updateAffiliateLink'])->name('videos.update-affiliate-link');
        Route::post('/videos/{videoId}/affiliate-links', [AffiliateLinkController::class, 'store'])->name('videos.store-affiliate-link');
        Route::post('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'updateAffiliateLink'])->name('videos.update-affiliate-link');


        Route::resource('affiliate-links', AffiliateLinkController::class);

        Route::get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter');


        Route::get('videos/{video}/edit-seo', [VideoController::class, 'editSeo'])
            ->name('videos.edit.seo')
            ->middleware('permission:video.edit');

        Route::put('videos/{video}/update-seo', [VideoController::class, 'updateSeo'])
            ->name('videos.update.seo')
            ->middleware('permission:video.edit');


        Route::get('{video}/similar-products/edit', [VideoController::class, 'editSimilarProducts'])->name('similar-products.edit');
        Route::post('{video}/similar-products/update', [VideoController::class, 'updateSimilarProducts'])->name('similar-products.update');
        Route::get('similar-products/{videoId}/region/{regionId}', [VideoController::class, 'getSimilarProductsByRegion']);

        // Fetch products by region
        Route::get('/similar-products/{video}/region/{region}', [SimilarProductController::class, 'fetchByRegion']);

        // Create a new product
        Route::post('/similar-products/{video}/create', [SimilarProductController::class, 'store']);

        // Update an existing product
        Route::post('/similar-products/{product}/update', [SimilarProductController::class, 'update']);

        // Delete a product
        Route::delete('/similar-products/{product}/delete', [SimilarProductController::class, 'destroy']);

        //     Route::get('videos/{video}/seo/{region}', [VideoSeoController::class, 'regionData'])
        //  ->name('videos.seo.region');
    
        Route::get('videos/{video}/seo/{region}', [VideoController::class, 'getSeoByRegion'])
            ->name('videos.seo.by-region')
            ->middleware('permission:video.edit');

        Route::get('videos/{video}/edit-product', [VideoController::class, 'editProduct'])
            ->name('videos.edit.product')
            ->middleware('permission:video.edit');

        Route::put('videos/{video}/update-product', [VideoController::class, 'updateProduct'])
            ->name('videos.update.product')
            ->middleware('permission:video.edit');

        Route::get('/videos/{videoId}/character-insights', [CharacterInsightController::class, 'index'])->name('videos.character-insights.index');
        Route::get('/videos/{videoId}/character-insights/create', [CharacterInsightController::class, 'create'])->name('videos.character-insights.create');
        Route::post('/videos/{videoId}/character-insights', [CharacterInsightController::class, 'store'])->name('videos.character-insights.store');
        Route::get('/videos/{videoId}/character-insights/{characterInsight}/edit', [CharacterInsightController::class, 'edit'])->name('videos.character-insights.edit');
        Route::put('/videos/{videoId}/character-insights/{characterInsight}', [CharacterInsightController::class, 'update'])->name('videos.character-insights.update');
        Route::delete('/videos/{videoId}/character-insights/{characterInsight}', [CharacterInsightController::class, 'destroy'])->name('videos.character-insights.destroy');

        Route::get('/vimeo', [VimeoController::class, 'index'])->name('vimeo.index');
        Route::post('/admin/vimeo/assign', [VimeoController::class, 'assign'])
            ->middleware('can:video.update')
            ->name('admin.vimeo.assign');


        Route::get('product-reviews', [ProductReviewController::class, 'index'])->name('product-reviews.index');
        Route::get('product-reviews/{category}/characters', [ProductReviewController::class, 'product_review_character'])->name('product_review.character');
        Route::post('product-reviews/store', [ProductReviewController::class, 'store'])->name('product_review.store');
        Route::get('videos/fetch/{character_id}', [ProductReviewController::class, 'fetchVideos'])->name('fetch_videos');
        // routes/web.php
        Route::patch('product-reviews/{review}/featured', [ProductReviewController::class, 'updateFeatured'])->name('product_review.featured');

        Route::get('characters/{character}/bloopers', [BlooperController::class, 'index'])->name('bloopers.index');
        Route::get('characters/{character}/bloopers/create', [BlooperController::class, 'create'])->name('bloopers.create');
        Route::get('characters/{character}/bloopers/edit', [BlooperController::class, 'edit'])->name('bloopers.edit');
        Route::post('characters/{character}/bloopers', [BlooperController::class, 'store'])->name('bloopers.store');
        Route::delete('bloopers/{blooper}', [BlooperController::class, 'destroy'])->name('bloopers.destroy');


        // Users (maybe only super_admin + managers)
        Route::resource('users', UserController::class)
            ->middleware('permission:user.view|user.create|user.edit|user.delete');

        Route::post('/users/{user}/toggle-block', [UserController::class, 'toggleBlock'])
            ->name('users.toggle-block');


        // Highlight tags
        Route::resource('highlight_tags', HighlightTagController::class)
            ->middleware('permission:highlight_tag.view|highlight_tag.create|highlight_tag.edit|highlight_tag.delete');

        // Subscription listing
        Route::resource('subscription_listing', SubscriptionListingController::class)
            ->middleware('permission:subscription_list.view|subscription_list.create|subscription_list.edit|subscription_list.delete');
    });

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('sub-admins', [UserController::class, 'index_sub_admin'])->name('sub_admins.index');
    Route::get('sub-admins/create', [UserController::class, 'create_sub_admin'])->name('sub_admins.create');
    Route::post('sub-admins', [UserController::class, 'store_sub_admin'])->name('sub_admins.store');
    Route::get('sub-admins/{user}/edit', [UserController::class, 'edit_sub_admin'])->name('sub_admins.edit');
    Route::put('sub-admins/{user}', [UserController::class, 'update_sub_admin'])->name('sub_admins.update');
    Route::get('sub-admins/{user}/show', [UserController::class, 'show_sub_admin'])->name('sub_admins.show');
    Route::delete('sub-admins/{user}', [UserController::class, 'destroy_sub_admin'])->name('sub_admins.destroy');

    //Route::get('sub-admins/assign-roles', [UserController::class, 'assignRoles'])->name('sub_admins.assign_roles');
    Route::get('sub-admins/{user}/assign-role', [UserController::class, 'assignRoles'])->name('sub_admins.assign_role');
    Route::put('sub-admins/{user}/assign-role', [UserController::class, 'updateRole'])->name('sub_admins.update_role');

    Route::post('sub-admins/update-roles', [UserController::class, 'updateRoles'])->name('sub_admins.update_roles');
    Route::resource('roles', RoleController::class);
    Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.updatePermissions');
});


Route::prefix('admin')->middleware(['auth'])->name('admin.')->group(function () {
    // Reviews (any of these perms can access the resource routes you enabled)
    Route::resource('reviews', ReviewController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('permission:rating_review.view|rating_review.create|rating_review.edit|rating_review.delete');

    // Review approve/reject (separate explicit permissions)
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])
        ->name('reviews.approve')
        ->middleware('permission:rating_review.approve');

    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])
        ->name('reviews.reject')
        ->middleware('permission:rating_review.reject');

    // Subscriptions (read-only here)
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])
        ->name('subscriptions.index')
        ->middleware('permission:subscription.view');

    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
        ->name('subscriptions.show')
        ->middleware('permission:subscription.view');

    // Product Messages
    Route::get('product-messages', [ProductMessageController::class, 'index'])->name('product-messages.index');
    Route::get('product-messages/{id}', [ProductMessageController::class, 'show'])->name('product-messages.show');
    Route::delete('product-messages/{id}', [ProductMessageController::class, 'destroy'])->name('product-messages.delete');
});



// Stripe apyment related hooks

Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');



Route::get('/pm-maker', function () {
    return view('pm-maker', [
        'stripeKey' => config('services.stripe.key'),
    ]);
})->name('pm.maker');


// Cron-jobs

Route::get('/jobs/cancel-overdue-renewals', [CronJobController::class, 'cancelOverdueRenewals']);
Route::get('/cron/assign-highlight-tags', [CronJobController::class, 'assignHighlightTags']);


Route::middleware(['auth'])->get('/admin-test', function () {
    return 'Welcome Admin';
});


Route::get('/clear-all', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize');

    return "All caches cleared successfully!";
})->name('clear.all');




require __DIR__ . '/auth.php';
