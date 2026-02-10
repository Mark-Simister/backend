<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserThemeController extends Controller
{
    /**
     * Get the current user's selected theme
     */
    public function getUserTheme()
    {
        $theme = UserTheme::with('theme')->where('user_id', Auth::id())->first();
        return response()->json($theme);
    }

    /**
     * Store or update selected theme (or reset if null)
     */
public function saveTheme(Request $request)
{
    $validated = $request->validate([
        'theme_id' => 'nullable|exists:themes,id',
        'button_color' => 'nullable|string|max:20',
        'link_color' => 'nullable|string|max:20',
        'dark_bg_color' => 'nullable|string|max:20',
        'light_bg_color' => 'nullable|string|max:20',
    ]);

    $userId = Auth::id();

    //  Reset to default if no theme selected
    if (is_null($validated['theme_id'])) {
        UserTheme::where('user_id', $userId)->delete();
        return response()->json([
            'message' => 'Theme reset to default',
        ]);
    }

    //  Get existing user theme (to preserve colors)
    $existingTheme = UserTheme::where('user_id', $userId)->first();

    //  Update or create while preserving old colors if not passed in request
    $userTheme = UserTheme::updateOrCreate(
        ['user_id' => $userId],
        [
            'theme_id' => $validated['theme_id'],
            'button_color' => $validated['button_color'] ?? $existingTheme->button_color ?? null,
            'link_color' => $validated['link_color'] ?? $existingTheme->link_color ?? null,
            'dark_bg_color' => $validated['dark_bg_color'] ?? $existingTheme->dark_bg_color ?? null,
            'light_bg_color' => $validated['light_bg_color'] ?? $existingTheme->light_bg_color ?? null,
        ]
    );

    return response()->json([
        'message' => 'Theme preference saved',
        'userTheme' => $userTheme->load('theme'),
    ]);
}

/**
 * Update user theme custom colors
 */
public function updateCustomColors(Request $request)
{
    $validated = $request->validate([
        'button_color' => 'nullable|string|max:20',
        'link_color' => 'nullable|string|max:20',
        'dark_bg_color' => 'nullable|string|max:20',
        'light_bg_color' => 'nullable|string|max:20',
    ]);

    $userId = Auth::id();

    // Ensure user has a theme selected
    $userTheme = UserTheme::where('user_id', $userId)->first();

    if (!$userTheme) {
        return response()->json([
            'message' => 'No theme found for user. Please select a theme first.',
        ], 404);
    }

    // Update custom color fields only
    $userTheme->update([
        'button_color' => $validated['button_color'] ?? $userTheme->button_color,
        'link_color' => $validated['link_color'] ?? $userTheme->link_color,
        'dark_bg_color' => $validated['dark_bg_color'] ?? $userTheme->dark_bg_color,
        'light_bg_color' => $validated['light_bg_color'] ?? $userTheme->light_bg_color,
    ]);

    return response()->json([
        'message' => 'Custom theme colors updated successfully.',
        'userTheme' => $userTheme->load('theme'),
    ]);
}


}
