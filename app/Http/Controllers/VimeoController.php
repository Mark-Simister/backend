<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VimeoService;
use Illuminate\Http\JsonResponse;
use Vimeo\Vimeo;
use App\Models\Video;
use Carbon\Carbon;

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
    public function index(VimeoService $vimeo)
    {
        $fields = implode(',', [
            'uri',
            'name',
            'description',
            'link',
            'privacy',
            'created_time',
            'duration',
            'pictures.sizes.link',
        ]);

        $result = $vimeo->listAllMyVideos(100, $fields);
        $videos = $result['videos'] ?? [];

        $total = $result['total'] ?? count($videos);

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

        $assignedVideos = Video::where('type', 'vimeo')
            ->with('character')
            ->latest()
            ->get();
        $vimeoByUrl = collect($assigned)->keyBy('link');

        return view('admin.vimeo.index', compact(
            'videos',
            'total',
            'assigned',
            'unassigned',
            'alreadyAssigned',
            'assignedVideos',
            'vimeoByUrl'
        ));
    }




    /**
     * Assign (upsert) into your existing `videos` table with type=vimeo.
     */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'uri' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['required', 'url'],
            'thumbnail' => ['nullable', 'url'],
        ]);

        $video = Video::updateOrCreate(
            ['type' => 'vimeo', 'video_url' => $data['link']],
            [
                'title' => $data['name'],
                'description' => $data['description'] ?? '',
                'thumbnail_url' => $data['thumbnail'] ?? null,
                'type' => 'vimeo',
            ]
        );

        return back()->with('success', 'Assigned Vimeo video: ' . $video->title);
    }
}
