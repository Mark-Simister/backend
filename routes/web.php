<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ChannelController;
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
use App\Http\Middleware\VerifyCsrfToken;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes — Protected by auth + role:admin
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    // Category CRUD Routes
    Route::resource('categories', CategoryController::class);
    Route::resource('permissions', PermissionController::class);
    Route::resource('channels', ChannelController::class);
    Route::resource('character_tags', CharacterTagController::class);
    Route::resource('character_roles', CharacterRoleController::class);
    Route::resource('characters', CharacterController::class);
    Route::resource('videos', VideoController::class);
    Route::resource('users', UserController::class);

    Route::resource('highlight_tags', HighlightTagController::class);
    Route::resource('subscription_listing', SubscriptionListingController::class);

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




// USER submits review (always pending)
Route::middleware('auth')->post('/videos/{video}/reviews', [ReviewController::class, 'store'])
    ->name('reviews.store');

// PUBLIC fetch approved reviews for a video
Route::get('/videos/{video}/reviews', [ReviewController::class, 'publicIndex'])
    ->name('reviews.public.index');

// ADMIN moderation
Route::prefix('admin')->middleware(['auth', 'role:super_admin|sub_admin'])->name('admin.')->group(function () {
    Route::resource('reviews', ReviewController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])->name('reviews.reject');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
});

// Stripe apyment related hooks
// Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
//     ->withoutMiddleware(['auth:api', 'auth:sanctum','auth']) // list any auth middlewares you use
//     ->name('stripe.webhook');
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

Route::get('/pm-maker', function () {
    return view('pm-maker', [
        'stripeKey' => config('services.stripe.key'),
    ]);
})->name('pm.maker');


// Cron-jobs
// Route::post('/jobs/cancel-overdue-renewals', [CronJobController::class, 'cancelOverdueRenewals'])
//     ->name('jobs.cancel-overdue-renewals');
Route::get('/jobs/cancel-overdue-renewals', [CronJobController::class, 'cancelOverdueRenewals']);

Route::middleware(['auth'])->get('/admin-test', function () {
    return 'Welcome Admin';
});


// users

// // user submits review
// Route::middleware('auth')->post('/videos/{video}/reviews', [ReviewController::class, 'store'])
//     ->name('reviews.store');

// // public: fetch approved reviews
// Route::get('/videos/{video}/reviews', [ReviewController::class, 'publicIndex'])
//     ->name('reviews.public.index');


require __DIR__ . '/auth.php';
