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
            // $reviewUrl = $this->uploadToVimeo($filePath);
            $reviewUrl = $this->uploadToVimeo($filePath, true); // mark as review


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


//     public function uploadToVimeo($filePath)
// {
//     try {
//         // Upload the video
//         $uri = $this->vimeo->upload($filePath); 
//         // $uri is like "/videos/1121145795"

//         // Make video public
//         $this->vimeo->request($uri, [
//             'privacy' => [
//                 'view' => 'anybody'   // this makes it public
//             ]
//         ], 'PATCH');

//         // Extract numeric ID and return short URL
//         $videoId = (int) str_replace('/videos/', '', $uri);
//         return 'https://vimeo.com/' . $videoId;

//     } catch (\Exception $e) {
//         \Log::error('Vimeo upload failed: ' . $e->getMessage());
//         return null;
//     }
// }
public function uploadToVimeo($filePath, $isReview = false)
{
    try {
        // Upload the video
        $uri = $this->vimeo->upload($filePath); 
        // Make video public
        $this->vimeo->request($uri, [
            'privacy' => [
                'view' => 'anybody' 
            ],
            // Add tag if it's a review
            'tags' => $isReview ? ['product_review'] : []
        ], 'PATCH');

        $videoId = (int) str_replace('/videos/', '', $uri);
        return 'https://vimeo.com/' . $videoId;

    } catch (\Exception $e) {
        \Log::error('Vimeo upload failed: ' . $e->getMessage());
        return null;
    }
}



}
