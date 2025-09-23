<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Character;
use App\Models\ProductReview;
use App\Models\Video;
use App\Models\Role;
use Vimeo\Vimeo;
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

use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    protected Vimeo $vimeo;

    public function __construct()
    {
        $client = config('services.vimeo.client') ?? env('VIMEO_CLIENT');
        $secret = config('services.vimeo.secret') ?? env('VIMEO_SECRET');
        $access = config('services.vimeo.access') ?? env('VIMEO_ACCESS');

        $this->vimeo = new Vimeo($client, $secret, $access);
    }

    //    public function index()
// {
//     $user = Auth::user(); // Get the authenticated user

    //     // Get all roles associated with the user
//     $roles = $user->getRoleNames(); 

    //     // Get all permissions assigned to the user (both via direct assignment and through roles)
//     $permissions = $user->getAllPermissions(); 

    //     // Check if you want to load the permissions for a specific role (e.g., 'sub_admin')
//     // Assuming $roleId is passed as a parameter, or replace it with a valid role ID
//     $roleId = 2;  // For example, replace with your desired role ID
//     $role = Role::with('permissions')->find($roleId);  // Load the role with permissions

    //     // Load permissions after the role is retrieved
//     if ($role) {
//         $role->load('permissions');
//     }
// $role = Role::with('permissions')->where('name', 'sub_admin')->first();
// dd($role->permissions);
//     // Dump the permissions related to the role
//     dd($roles, $user, $permissions, $role ? $role->permissions : 'Role not found');

    //     // Fetch the latest categories (assuming you need this for your view)
//     $categories = Category::latest()->get();

    //     // Return the view with categories
//     return view('admin.product_review.index', compact('categories'));
// }
// public function index()
//     {

    //     // Get the authenticated user
//     $user = Auth::user(); 

    //     // Get all roles of the logged-in user
//     $roles = $user->getRoleNames(); // This will return an array of role names the user has
//     // Check if the user has any role assigned
//     if ($roles->isEmpty()) {
//         dd('User has no roles assigned.');
//     }

    //     // Get the role model dynamically based on the first role the user has
//     $roleName = $roles->first();  // Get the first role assigned to the user (you can also loop through all roles if needed)

    //     // Fetch the role with its permissions dynamically
//     $role = Role::with('permissions')->where('name', $roleName)->first();
//     // Check if role exists
//     if (!$role) {
//         dd('Role not found.');
//     }

    //     // Get the permissions for the role
//     $permissions = $role->permissions;

    //     // Show the role and permissions of the logged-in user
//     return response()->json([
//         'role' => $roleName,  // The role name
//         'permissions' => $permissions->pluck('name')  // List of permission names for the role
//     ]);
//     }
    public function index()
    {
        $categories = Category::latest()->get();
        return view('admin.product_review.index', compact('categories'));
    }


    public function product_review_character(Category $category)
    {
        $characters = Character::with('category')
            ->where('category_id', $category->id)
            ->latest()
            ->get();

        $reviews = ProductReview::with(['character:id,name', 'video:id,title'])
            ->whereHas('character', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })
            ->latest()
            ->get();

        return view('admin.product_review.character', compact('characters', 'category', 'reviews'));

    }
    public function fetchVideos($character_id)
    {
        $videos = Video::where('character_id', $character_id)->get();

        return response()->json(data: $videos);
    }
    public function store(Request $request)
    {
        $request->validate([
            'character_id' => 'required|exists:characters,id',
            'review_type' => 'required|in:full_video,mp3,short_video',
            'file' => 'required|file|mimes:mp4,mp3',
            'video_id' => 'required|exists:videos,id',
        ]);

        $file = $request->file('file');
        $reviewType = $request->review_type;
        $ext = strtolower($file->getClientOriginalExtension());

        // Validate extensions
        if ($reviewType === 'mp3' && $ext !== 'mp3') {
            return redirect()->back()->withErrors(['file' => 'Please upload an MP3 file for the MP3 review type.'])->withInput();
        }
        if (in_array($reviewType, ['full_video', 'short_video']) && $ext !== 'mp4') {
            return redirect()->back()->withErrors(['file' => 'Please upload an MP4 file for video review types.'])->withInput();
        }

        $video = Video::findOrFail($request->video_id);

        if ($reviewType === 'mp3') {
            // Store mp3 locally
            $destination = public_path('product_review');
            if (!is_dir($destination)) {
                @mkdir($destination, 0755, true);
            }

            $filename = 'review_' . uniqid() . '.mp3';
            $file->move($destination, $filename);

            $reviewUrl = url('product_review/' . $filename);
        } else {
            // Upload mp4 to Vimeo and get the actual Vimeo URL
            $filePath = $file->getPathname();
            $reviewUrl = $this->uploadToVimeo($filePath);

        }

        ProductReview::create([
            'character_id' => $request->character_id,
            'review_url' => $reviewUrl,
            'video_id' => $video->id,
            'type' => $reviewType,
        ]);

        return redirect()->back()->with('success', 'Product review submitted successfully!');
    }


    public function updateFeatured(ProductReview $review, Request $request)
    {
        $validated = $request->validate([
            'is_featured' => 'required|boolean',
        ]);

        $review->is_featured = (bool) $validated['is_featured'];
        $review->save();

        return response()->json([
            'ok' => true,
            'is_featured' => (bool) $review->is_featured,
            'message' => $review->is_featured ? 'Marked as featured.' : 'Removed from featured.'
        ]);
    }




    // Vimeo Upload Helper Method
    // private function uploadToVimeo($filePath)
    // {
    //     try {
    //         $uri = $this->vimeo->upload($filePath);

    //         return 'https://vimeo.com' . $uri;
    //     } catch (\Exception $e) {
    //         return null;
    //     }
    // }
    private function uploadToVimeo($filePath)
{
    try {
        $uri = $this->vimeo->upload($filePath); 
        // Example response: "/videos/1121145795"

        // Extract numeric ID from URI
        $videoId = (int) str_replace('/videos/', '', $uri);

        // Return clean short URL
        return 'https://vimeo.com/' . $videoId;
    } catch (\Exception $e) {
        return null;
    }
}


}
