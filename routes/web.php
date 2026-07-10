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
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\TopCategoryController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Route::get('/', function () { return redirect()->route('login'); });

// Public, server-rendered review pages (SEO / LLM crawlable HTML + JSON-LD).
// The single canonical public URL is /review/{slug} (payload-backed). The
// legacy numeric /review/{id} is a best-effort 301 into the slug system, so old
// numeric URLs don't 404 or compete for canonical. Order matters: the numeric
// (whereNumber) route is registered first so digit-only paths never hit the
// slug controller; everything else (contains letters) falls to the slug route.
//
// STATELESS: strip session/cookie/CSRF middleware so these anonymous, cacheable
// SEO responses never emit Set-Cookie (XSRF-TOKEN / laravel_session) — a
// Set-Cookie header makes a response uncacheable at the CDN and needlessly
// hands crawlers a session. These routes read no session and post no forms.
Route::withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
])->group(function () {
    Route::get('/review/{id}', [\App\Http\Controllers\PublicReviewPageController::class, 'legacyRedirect'])
        ->whereNumber('id');
    Route::get('/review/{slug}', [\App\Http\Controllers\PublicReviewPageController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-]+')->name('public.reviews.show');
    Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('reviews.sitemap');
    Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('reviews.robots');
});
Route::get('/dashboard', function () { return view('dashboard'); })->middleware(['auth', 'verified'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->as('admin.')->middleware(['auth'])->group(function () {
    Route::resource('themes', ThemeController::class);
    });

Route::prefix('admin')->name('admin.')->group(function() {
    Route::resource('top-categories', TopCategoryController::class);
});

// Site images (hero/background image manager) — any authenticated admin.
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('site-images', [\App\Http\Controllers\SiteImageController::class, 'index'])->name('site-images.index');
    Route::post('site-images/{key}', [\App\Http\Controllers\SiteImageController::class, 'update'])->name('site-images.update');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('channels', ChannelController::class)->middleware('permission:channel.view|channel.create|channel.edit|channel.delete'); // Channels
    Route::resource('categories', CategoryController::class)->middleware('permission:category.view|category.create|category.edit|category.delete'); // Category CRUD
    Route::get('categories/{channel}/regions', [CategoryController::class, 'getRegions'])->name('categories.getRegions');
    Route::resource('permissions', PermissionController::class)->middleware('role:super_admin'); // Permissions management (restrict to super_admin only if you want)
    Route::resource('regions', RegionController::class)->middleware('permission:region.view|region.create|region.edit|region.delete');
    Route::resource('global-colors', GlobalColorController::class);
    Route::resource('character_tags', CharacterTagController::class)->middleware('permission:character_tag.view|character_tag.create|character_tag.edit|character_tag.delete');
    Route::resource('character_roles', CharacterRoleController::class)->middleware('permission:character_role.view|character_role.create|character_role.edit|character_role.delete'); // Character roles
    Route::resource('characters', CharacterController::class)->middleware('permission:character.view|character.create|character.edit|character.delete'); // Characters
    Route::resource('faqs', FaqController::class)->middleware('permission:faq.view|faq.create|faq.edit|faq.delete'); // Faqs
    Route::get('characters/{category}/regions', [CharacterController::class, 'getRegions'])->name('characters.getRegions');
    Route::resource('videos', VideoController::class)->middleware('permission:video.view|video.create|video.edit|video.delete'); // Videos
    Route::get('videos/{character}/regions', [VideoController::class, 'getRegions'])->name('videos.getRegions');
    Route::get('/videos/{id}/comments', [VideoController::class, 'showCommentsPage'])->name('videos.comments');
    Route::delete('/comments/{comment}', [VideoController::class, 'destroy_comment'])->name('comments.delete');
    Route::put('/comments/{comment}', [VideoController::class, 'update_comment'])->name('comments.update');
    Route::delete('/replies/{reply}', [VideoController::class, 'deleteReply'])->name('replies.delete');
    Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    Route::get('/tags/by-ids', [TagController::class, 'byIds'])->name('tags.byIds');
    Route::get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter');
    Route::get('videos/{video}/edit-seo', [VideoController::class, 'editSeo'])->name('videos.edit.seo')->middleware('permission:video.edit');
    Route::put('videos/{video}/update-seo', [VideoController::class, 'updateSeo'])->name('videos.update.seo')->middleware('permission:video.edit');
    Route::get('{video}/similar-products/edit', [VideoController::class, 'editSimilarProducts'])->name('similar-products.edit');
    Route::post('{video}/similar-products/update', [VideoController::class, 'updateSimilarProducts'])->name('similar-products.update');
    Route::get('similar-products/{videoId}/region/{regionId}', [VideoController::class, 'getSimilarProductsByRegion']);
    Route::get('/similar-products/{video}/region/{region}', [SimilarProductController::class, 'fetchByRegion']); // Fetch products by region
    Route::post('/similar-products/{video}/create', [SimilarProductController::class, 'store']); // Create a new product
    Route::post('/similar-products/{product}/update', [SimilarProductController::class, 'update']); // Update an existing product
    Route::delete('/similar-products/{product}/delete', [SimilarProductController::class, 'destroy']); // Delete a product
    Route::get('videos/{video}/seo/{region}', [VideoController::class, 'getSeoByRegion'])->name('videos.seo.by-region')->middleware('permission:video.edit');
    Route::get('videos/{video}/edit-product', [VideoController::class, 'editProduct'])->name('videos.edit.product')->middleware('permission:video.edit');
    Route::put('videos/{video}/update-product', [VideoController::class, 'updateProduct'])->name('videos.update.product')->middleware('permission:video.edit');
    Route::get('/videos/{videoId}/character-insights', [CharacterInsightController::class, 'index'])->name('videos.character-insights.index');
    Route::get('/videos/{videoId}/character-insights/create', [CharacterInsightController::class, 'create'])->name('videos.character-insights.create');
    Route::post('/videos/{videoId}/character-insights', [CharacterInsightController::class, 'store'])->name('videos.character-insights.store');
    Route::get('/videos/{videoId}/character-insights/{characterInsight}/edit', [CharacterInsightController::class, 'edit'])->name('videos.character-insights.edit');
    Route::put('/videos/{videoId}/character-insights/{characterInsight}', [CharacterInsightController::class, 'update'])->name('videos.character-insights.update');
    Route::delete('/videos/{videoId}/character-insights/{characterInsight}', [CharacterInsightController::class, 'destroy'])->name('videos.character-insights.destroy');
    Route::post('videos/toggle-featured', [VideoController::class, 'toggleFeatured'])->name('videos.toggleFeatured');
    Route::get('/vimeo', [VimeoController::class, 'index'])->name('vimeo.index');
    Route::post('/admin/vimeo/assign', [VimeoController::class, 'assign'])->middleware('can:video.update')->name('admin.vimeo.assign');
    Route::get('forms/submissions-page', [FormController::class, 'submissionsPageNew'])->name('forms.submissions_page')->middleware('permission:form.view'); // Page to select a form first
    Route::resource('forms', FormController::class)->middleware('permission:form.view|form.create|form.edit|form.delete'); // Then the resource route
    Route::post('forms/{form}/submit', [FormController::class, 'submit'])->name('forms.submit')->middleware('permission:form.view|form.create|form.edit|form.delete'); // Handle frontend form submissions
    Route::get('forms/{form}/submissions', [FormController::class, 'submissions'])->name('forms.submissions')->middleware('permission:form.view'); // Existing route for specific form submissions
    Route::get('product-reviews', [ProductReviewController::class, 'index'])->name('product-reviews.index');
    Route::get('product-reviews/{category}/characters', [ProductReviewController::class, 'product_review_character'])->name('product_review.character');
    Route::post('product-reviews/store', [ProductReviewController::class, 'store'])->name('product_review.store');
    Route::get('videos/fetch/{character_id}', [ProductReviewController::class, 'fetchVideos'])->name('fetch_videos');
    Route::patch('product-reviews/{review}/featured', [ProductReviewController::class, 'updateFeatured'])->name('product_review.featured');
    Route::get('characters/{character}/bloopers', [BlooperController::class, 'index'])->name('bloopers.index');
    Route::get('characters/{character}/bloopers/create', [BlooperController::class, 'create'])->name('bloopers.create');
    Route::get('characters/{character}/bloopers/{blooper}/edit', [BlooperController::class, 'edit'])->name('bloopers.edit');
    Route::post('characters/{character}/bloopers', [BlooperController::class, 'store'])->name('bloopers.store');
    Route::match(['put','patch'], 'characters/{character}/bloopers/{blooper}', [BlooperController::class, 'update'])->name('bloopers.update');
    Route::delete('bloopers/{blooper}', [BlooperController::class, 'destroy'])->name('bloopers.destroy');
    Route::resource('users', UserController::class)->middleware('permission:user.view|user.create|user.edit|user.delete'); // Users (maybe only super_admin + managers)
    Route::post('/users/{user}/toggle-block', [UserController::class, 'toggleBlock'])->name('users.toggle-block');
    Route::resource('highlight_tags', HighlightTagController::class)->middleware('permission:highlight_tag.view|highlight_tag.create|highlight_tag.edit|highlight_tag.delete'); // Highlight tags
    Route::resource('subscription_listing', SubscriptionListingController::class)->middleware('permission:subscription_list.view|subscription_list.create|subscription_list.edit|subscription_list.delete'); // Subscription listing
    Route::get('/videos/{videoId}/affiliate-links', [VideoController::class, 'manageLinks'])->name('videos.affiliate-links'); // Route for managing affiliate links (View all links for a video)
    // Route::post('/videos/{videoId}/affiliate-links', [AffiliateLinkController::class, 'store'])->name('videos.store-affiliate-link'); // Route for storing a new affiliate link (POST method)
    Route::delete('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'destroy'])->name('videos.affiliateLinks.destroy'); // Route for deleting an affiliate link (DELETE method)
    // Route::patch('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'updateAffiliateLink'])->name('videos.update-affiliate-link'); // Route for updating an existing affiliate link (PATCH method)
    Route::post('/videos/{videoId}/affiliate-links', [AffiliateLinkController::class, 'store'])->name('videos.store-affiliate-link');
    Route::post('/videos/{video}/affiliate-links/{affiliateLink}', [AffiliateLinkController::class, 'updateAffiliateLink'])->name('videos.update-affiliate-link');
    Route::resource('affiliate-links', AffiliateLinkController::class);
    // Route::get('videos/{video}/seo/{region}', [VideoSeoController::class, 'regionData'])->name('videos.seo.region');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('sub-admins', [UserController::class, 'index_sub_admin'])->name('sub_admins.index');
    Route::get('sub-admins/create', [UserController::class, 'create_sub_admin'])->name('sub_admins.create');
    Route::post('sub-admins', [UserController::class, 'store_sub_admin'])->name('sub_admins.store');
    Route::get('sub-admins/{user}/edit', [UserController::class, 'edit_sub_admin'])->name('sub_admins.edit');
    Route::put('sub-admins/{user}', [UserController::class, 'update_sub_admin'])->name('sub_admins.update');
    Route::get('sub-admins/{user}/show', [UserController::class, 'show_sub_admin'])->name('sub_admins.show');
    Route::delete('sub-admins/{user}', [UserController::class, 'destroy_sub_admin'])->name('sub_admins.destroy');
    // Route::get('sub-admins/assign-roles', [UserController::class, 'assignRoles'])->name('sub_admins.assign_roles');
    Route::get('sub-admins/{user}/assign-role', [UserController::class, 'assignRoles'])->name('sub_admins.assign_role');
    Route::put('sub-admins/{user}/assign-role', [UserController::class, 'updateRole'])->name('sub_admins.update_role');
    Route::post('sub-admins/update-roles', [UserController::class, 'updateRoles'])->name('sub_admins.update_roles');
    Route::resource('roles', RoleController::class);
    Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.updatePermissions');
});

Route::prefix('admin')->middleware(['auth'])->name('admin.')->group(function () {
    Route::resource('reviews', ReviewController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('permission:rating_review.view|rating_review.create|rating_review.edit|rating_review.delete'); // Reviews (any of these perms can access the resource routes you enabled)
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve')->middleware('permission:rating_review.approve'); // Review approve/reject (separate explicit permissions)
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject')->middleware('permission:rating_review.reject');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index')->middleware('permission:subscription.view'); // Subscriptions (read-only here)
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show')->middleware('permission:subscription.view');
    Route::get('product-messages', [ProductMessageController::class, 'index'])->name('product-messages.index'); // Product Messages
    Route::get('product-messages/{id}', [ProductMessageController::class, 'show'])->name('product-messages.show');
    Route::delete('product-messages/{id}', [ProductMessageController::class, 'destroy'])->name('product-messages.delete');
});

// Stripe apyment related hooks
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::get('/pm-maker', function () {
    return view('pm-maker', [
        'stripeKey' => config('services.stripe.key'),
    ]);
})->name('pm.maker');

// Cron-jobs
Route::get('/jobs/cancel-overdue-renewals', [CronJobController::class, 'cancelOverdueRenewals']);
Route::get('/cron/assign-highlight-tags', [CronJobController::class, 'assignHighlightTags']);
Route::middleware(['auth'])->get('/admin-test', function () {return 'Welcome Admin';});

Route::get('/explain_videos/{filename}', function ($filename) {
    $filePath = public_path('explain_videos/' . $filename);

    if (!file_exists($filePath)) {
        abort(404);
    }

    $fileSize = filesize($filePath);
    $start = 0;
    $end = $fileSize - 1;
    $length = $fileSize;

    $headers = [
        'Content-Type'  => 'video/mp4',
        'Accept-Ranges' => 'bytes',
        'Content-Length'=> $fileSize,
    ];

    // ── Handle Range request (seeking) ──
    if (request()->hasHeader('Range')) {
        preg_match('/bytes=(\d+)-(\d*)/', request()->header('Range'), $matches);

        $start  = intval($matches[1]);
        $end    = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
        $length = $end - $start + 1;

        $headers['Content-Range']  = "bytes $start-$end/$fileSize";
        $headers['Content-Length'] = $length;

        return response()->stream(function () use ($filePath, $start, $length) {
            $file = fopen($filePath, 'rb');
            fseek($file, $start);
            $remaining = $length;
            while (!feof($file) && $remaining > 0) {
                $chunk = min(8192, $remaining);
                echo fread($file, $chunk);
                $remaining -= $chunk;
                flush();
            }
            fclose($file);
        }, 206, $headers);
    }

    // ── Full file (no seeking yet) ──
    return response()->stream(function () use ($filePath) {
        readfile($filePath);
    }, 200, $headers);
});

Route::get('/clear-all', function () {
    $commands = [
        'config:clear',
        'cache:clear',
        'route:clear',
        'view:clear',
        'optimize',
    ];
    $output = [];
    foreach ($commands as $command) {
        try {
            $result = Artisan::call($command);
            $output[$command] = Artisan::output();
        } catch (\Exception $e) {
            Log::error("Artisan command '$command' failed: " . $e->getMessage());
            $output[$command] = 'Error: ' . $e->getMessage();
        }
    }
    // Show output in browser
    return response()->json($output);
})->name('clear.all');

require __DIR__ . '/auth.php';
