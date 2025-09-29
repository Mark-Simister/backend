<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VimeoService;
use Illuminate\Http\JsonResponse;
use Vimeo\Vimeo;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class VimeoController extends Controller
{
    protected Vimeo $vimeo;
    public function __construct()
    {
        $client = config('services.vimeo.client') ?? env('VIMEO_CLIENT');
        $secret = config('services.vimeo.secret') ?? env('VIMEO_SECRET');
        $access = config('services.vimeo.access') ?? env('VIMEO_ACCESS');

        $this->vimeo = new Vimeo($client, $secret, $access);
    }
    public function index(Request $request, VimeoService $vimeo)
{
    $page = max(1, (int) $request->integer('page', 1));
    $perPage = 24;

    $fields = implode(',', [
        'uri',
        'name',
        'description',
        'link',
        'privacy',
        'created_time',
        'duration',
        'pictures.sizes.link',
        'tags.name',
    ]);

    $result = $vimeo->listAllMyVideos($perPage, $fields, $page);
    // dd($result); // make sure your service accepts $page
    $videos = $result['videos'] ?? [];
    $total  = $result['total']  ?? count($videos);

    $prevPage = $page > 1 ? $page - 1 : null;
    $nextPage = ($page * $perPage) < $total ? $page + 1 : null;

    // Already assigned Vimeo links -> local video id
    $alreadyAssigned = Video::where('type', 'vimeo')->pluck('id', 'video_url');

    $assigned = [];
    $unassigned = [];
    foreach ($videos as $v) {
        $link = $v['link'] ?? '';
        if ($link && $alreadyAssigned->has($link)) {
            $assigned[] = $v;
        } else {
            $unassigned[] = $v;
        }
    }

    // Videos you can assign TO (filter to things not yet linked to any provider URL)
    $assignableVideos = Video::query()
        ->whereNull('video_url')                
        ->orWhere('type', '!=', 'vimeo')      
        ->orderByDesc('id')
        ->get(['id', 'title']);                

    
    $assignedVideos = Video::where('type', 'vimeo')
        ->with('character')
        ->latest()->get();

    $vimeoByUrl = collect($assigned)->keyBy('link');

    // Optional error handling passed to view
    $error = $result['error'] ?? null;

    return view('admin.vimeo.index', compact(
        'videos',
        'total',
        'assigned',
        'unassigned',
        'alreadyAssigned',
        'assignedVideos',
        'vimeoByUrl',
        'assignableVideos',
        'page',
        'prevPage',
        'nextPage',
        'error'
    ));
}

public function assign(Request $request)
{
    $data = $request->validate([
        'video_id'   => ['required', Rule::exists('videos', 'id')],
        'vimeo_link' => ['required', 'url'],
        'title'      => ['nullable', 'string', 'max:255'],
        'thumb'      => ['nullable', 'url'],
        'duration'   => ['nullable', 'integer', 'min:0'],
        'description'=> ['nullable', 'string'],
    ]);

    $video = Video::findOrFail($data['video_id']);

    // if this Vimeo link is already used by a different row, block it
    $conflict = Video::where('video_url', $data['vimeo_link'])
        ->where('id', '!=', $video->id)
        ->exists();

    if ($conflict) {
        return back()->with('error', 'That Vimeo video is already assigned to another item.');
    }

    // Update the row
    $video->type = 'vimeo';
    $video->video_url = $data['vimeo_link'];

    // Optionally hydrate some metadata
    if (!empty($data['title']) && empty($video->title)) {
        $video->title = $data['title'];
    }
    if (isset($data['duration'])) {
        $video->duration_seconds = $data['duration'];
    }
    if (!empty($data['thumb']) && property_exists($video, 'thumbnail_url')) {
        $video->thumbnail_url = $data['thumb'];
    }
    if (!empty($data['description']) && property_exists($video, 'description')) {
        $video->description = $data['description'];
    }

    $video->save();

    return back()->with('success', 'Vimeo video assigned successfully.');
}
}
