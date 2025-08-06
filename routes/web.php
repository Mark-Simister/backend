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

Route::get('/', function () {
    return view('welcome');
});


// Authenticated User Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Authenticated Profile Routes
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

});

Route::middleware(['auth'])->get('/admin-test', function () {
    return 'Welcome Admin';
});


require __DIR__.'/auth.php';
