<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Character;
use App\Models\Channel;
use App\Models\Category;
use Illuminate\Http\Request;

class VideoController extends Controller
{
     public function index()
    {
        $videos = Video::latest()->get();
        return view('admin.videos.index', compact('videos'));
    }

    public function create()
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        return view('admin.videos.create', compact('channels', 'characters', 'categories'));
    }

   public function store(Request $request)
{
    //  Use Validator instead of request->validate
    $validator = \Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'type' => 'required|in:youtube,vimeo',
        'video_url' => 'required|url',

        // Step 2 fields
        'character_id' => 'nullable|exists:characters,id',
        'channel_id'   => 'nullable|exists:channels,id',
        'category_id'  => 'nullable|exists:categories,id',
        'access_level' => 'required|in:public,premium,early_access',

        // Step 3 optional fields
        'affiliate_link'   => 'nullable|url',
        'thumbnail_url'    => 'nullable|required_without:thumbnail_image|url',
        'thumbnail_image'  => 'nullable|required_without:thumbnail_url|image|mimes:jpg,jpeg,png|max:2048',

        // Step 4 meta fields
        // 'meta_title'       => 'nullable|string|max:70',
        // 'meta_description' => 'nullable|string|max:160',
        // 'keywords'         => 'nullable|string',

        // ---- New Fields ----
        'tags'             => 'nullable|string',
        'tags.*'           => 'string',
        'rating_type'      => 'required|in:rating,review',
        'sponsorship_type' => 'required|in:sponsored,unsponsored',
        'highlight_tags'   => 'nullable|string', //  fixed
        'auto_tags'        => 'nullable|string', //  fixed
        'video_type'       => 'required|in:short,full_review,reel,live,compilation',
        'video_platforms'  => 'nullable|array',
        'video_platforms.*'=> 'string',

        'raw_video_file'   => 'nullable|file|mimes:mp4,mov,avi|max:51200',
        'caption_file'     => 'nullable|file|mimes:vtt,srt,txt|max:1024',

        'status'           => 'required|in:draft,published',
        'is_ai_generated'  => 'boolean',
        'is_finalized'     => 'boolean',
        'is_qa_passed'     => 'boolean',
        'post_schedule_at' => 'nullable|date',

        'seo_title'        => 'nullable|string|max:255',
        'seo_description'  => 'nullable|string|max:500',
        'hashtags'         => 'nullable|string', //  fixed
        'cta_text'         => 'nullable|string|max:255',
        'og_image_url'     => 'nullable|url',
        'twitter_title'    => 'nullable|string|max:255',
        'twitter_description' => 'nullable|string|max:500',
    ]);

    //  If validation fails, dump errors instead of redirect
    // if ($validator->fails()) {
    //     dd('Validation Failed:', $validator->errors()->all());
    // }
    if ($validator->fails()) {
    return redirect()->back()
        ->withErrors($validator)
        ->withInput();
}

    $validated = $validator->validated();

    //  Convert comma-separated strings into arrays
    $highlight_tags = $request->highlight_tags 
        ? array_map('trim', explode(',', $request->highlight_tags)) 
        : [];
    $auto_tags = $request->auto_tags 
        ? array_map('trim', explode(',', $request->auto_tags)) 
        : [];
    $hashtags = $request->hashtags 
        ? array_map('trim', explode(',', $request->hashtags)) 
        : [];

    // Replace in validated array
    $validated['highlight_tags'] = $highlight_tags;
    $validated['auto_tags'] = $auto_tags;
    $validated['hashtags'] = $hashtags;

    // dd('Validated Successfully ', $validated);

    //  Handle Thumbnail Upload
    if ($request->hasFile('thumbnail_image')) {
        $destinationPath = public_path('thumbnails');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        $filename = time() . '_' . uniqid() . '.' . $request->file('thumbnail_image')->getClientOriginalExtension();
        $request->file('thumbnail_image')->move($destinationPath, $filename);

        $validated['thumbnail_url'] = null;
        $validated['thumbnail_image'] = 'thumbnails/' . $filename;
    } else {
        unset($validated['thumbnail_image']);
    }

    //  Handle raw video upload
    if ($request->hasFile('raw_video_file')) {
        $filename = time() . '_' . uniqid() . '.' . $request->file('raw_video_file')->getClientOriginalExtension();
        $request->file('raw_video_file')->move(public_path('videos/raw'), $filename);
        $validated['raw_video_file'] = 'videos/raw/' . $filename;
    }

    //  Handle caption upload
    if ($request->hasFile('caption_file')) {
        $filename = time() . '_' . uniqid() . '.' . $request->file('caption_file')->getClientOriginalExtension();
        $request->file('caption_file')->move(public_path('videos/captions'), $filename);
        $validated['caption_file'] = 'videos/captions/' . $filename;
    }

    //  Convert arrays to JSON before saving
    foreach (['tags','highlight_tags','auto_tags','video_platforms','hashtags'] as $jsonField) {
        if (isset($validated[$jsonField])) {
            $validated[$jsonField] = json_encode($validated[$jsonField]);
        }
    }

    Video::create($validated);

    return redirect()->route('admin.videos.index')->with('success', 'Video created successfully.');
}


    public function edit(Video $video)
    {
        $channels = Channel::all();
        $characters = Character::all();
        $categories = Category::all();
        return view('admin.videos.edit', compact('video', 'channels', 'characters', 'categories'));
    }

    public function update(Request $request, Video $video)
{
    // Use Validator (same as store)
    $validator = \Validator::make($request->all(), [
        // Step 1
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'type' => 'required|in:youtube,vimeo',
        'video_url' => 'required|url',

        // Step 2
        'character_id' => 'nullable|exists:characters,id',
        'channel_id'   => 'nullable|exists:channels,id',
        'category_id'  => 'nullable|exists:categories,id',
        'access_level' => 'required|in:public,premium,early_access',

        // Step 3
        'affiliate_link'   => 'nullable|url',
        'thumbnail_url'    => 'nullable|required_without:thumbnail_image|url',
        'thumbnail_image'  => 'nullable|required_without:thumbnail_url|image|mimes:jpg,jpeg,png|max:2048',

        // Step 4 (SEO)
        // 'meta_title'       => 'nullable|string|max:70',
        // 'meta_description' => 'nullable|string|max:160',
        // 'keywords'         => 'nullable|string',

        // ---- New Fields ----
        'tags'             => 'nullable|string',
        'tags.*'           => 'string',
        'rating_type'      => 'required|in:rating,review',
        'sponsorship_type' => 'required|in:sponsored,unsponsored',
        'highlight_tags'   => 'nullable|string', // fixed (same as store)
        'auto_tags'        => 'nullable|string', // fixed (same as store)
        'video_type'       => 'required|in:short,full_review,reel,live,compilation',
        'video_platforms'  => 'nullable|array',
        'video_platforms.*'=> 'string',

        'raw_video_file'   => 'nullable|file|mimes:mp4,mov,avi|max:51200',
        'caption_file'     => 'nullable|file|mimes:vtt,srt,txt|max:1024',

        'status'           => 'required|in:draft,published',
        'is_ai_generated'  => 'boolean',
        'is_finalized'     => 'boolean',
        'is_qa_passed'     => 'boolean',
        'post_schedule_at' => 'nullable|date',

        'seo_title'        => 'nullable|string|max:255',
        'seo_description'  => 'nullable|string|max:500',
        'hashtags'         => 'nullable|string', // fixed (same as store)
        'cta_text'         => 'nullable|string|max:255',
        'og_image_url'     => 'nullable|url',
        'twitter_title'    => 'nullable|string|max:255',
        'twitter_description' => 'nullable|string|max:500',
    ]);

    if ($validator->fails()) {
        dd('Validation Failed:', $validator->errors()->all());
    }
    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    $validated = $validator->validated();

    // Convert comma-separated strings into arrays
    $highlight_tags = $request->highlight_tags 
        ? array_map('trim', explode(',', $request->highlight_tags)) 
        : [];
    $auto_tags = $request->auto_tags 
        ? array_map('trim', explode(',', $request->auto_tags)) 
        : [];
    $hashtags = $request->hashtags 
        ? array_map('trim', explode(',', $request->hashtags)) 
        : [];

    $validated['highlight_tags'] = $highlight_tags;
    $validated['auto_tags'] = $auto_tags;
    $validated['hashtags'] = $hashtags;

    // Handle thumbnail logic
    if ($request->hasFile('thumbnail_image')) {
        $directory = public_path('thumbnails');
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = $request->file('thumbnail_image');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        $validated['thumbnail_image'] = 'thumbnails/' . $filename;
        $validated['thumbnail_url'] = null;
    } elseif ($request->filled('thumbnail_url')) {
        $validated['thumbnail_image'] = null;
    } else {
        unset($validated['thumbnail_image']);
        unset($validated['thumbnail_url']);
    }

    // Handle raw video upload
    if ($request->hasFile('raw_video_file')) {
        $filename = time() . '_' . uniqid() . '.' . $request->file('raw_video_file')->getClientOriginalExtension();
        $request->file('raw_video_file')->move(public_path('videos/raw'), $filename);
        $validated['raw_video_file'] = 'videos/raw/' . $filename;
    }

    // Handle caption upload
    if ($request->hasFile('caption_file')) {
        $filename = time() . '_' . uniqid() . '.' . $request->file('caption_file')->getClientOriginalExtension();
        $request->file('caption_file')->move(public_path('videos/captions'), $filename);
        $validated['caption_file'] = 'videos/captions/' . $filename;
    }

    // Convert arrays to JSON before saving
    foreach (['tags','highlight_tags','auto_tags','video_platforms','hashtags'] as $jsonField) {
        if (isset($validated[$jsonField])) {
            $validated[$jsonField] = json_encode($validated[$jsonField]);
        }
    }

    $video->update($validated);

    return redirect()->route('admin.videos.index')->with('success', 'Video updated successfully.');
}


    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted.');
    }
}