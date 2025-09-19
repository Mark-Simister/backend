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

        return view('admin.product_review.character', compact('characters', 'category'));
    }
    public function fetchVideos($character_id)
    {
        // Fetch videos where the character_id matches
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
        $filePath = $file->getPathname();

        $videoUrl = $this->uploadToVimeo($filePath);

        $video = $request->video_id ? Video::findOrFail($request->video_id) : Video::create([
            'character_id' => $request->character_id,
            'video_url' => $videoUrl,
            'type' => $request->review_type,
        ]);

        ProductReview::create([
            'character_id' => $request->character_id,
            'review_url' => $videoUrl,
            'video_id' => $video->id,
        ]);

        return redirect()->back()->with('success', 'Product review submitted successfully!');
    }


    // Vimeo Upload Helper Method
    private function uploadToVimeo($filePath)
    {
        try {
            $uri = $this->vimeo->upload($filePath);

            return 'https://vimeo.com' . $uri;
        } catch (\Exception $e) {
            return null;
        }
    }
}
