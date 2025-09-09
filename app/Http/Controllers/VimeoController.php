<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VimeoService;
use Illuminate\Http\JsonResponse;
use Vimeo\Vimeo;

class VimeoController extends Controller
{
    protected Vimeo $vimeo;
    public function __construct()
    {
        // Prefer config/services.php but fall back to .env if needed
        $client = config('services.vimeo.client') ?? env('VIMEO_CLIENT');
        $secret = config('services.vimeo.secret') ?? env('VIMEO_SECRET');
        $access = config('services.vimeo.access') ?? env('VIMEO_ACCESS');

        // dd($client,$secret,$access);
        $this->vimeo = new Vimeo($client, $secret, $access);
    }
    public function index(VimeoService $vimeo): JsonResponse
    {
        // Trim payload to common fields; add "download" if you have video_files scope
        $fields = implode(',', [
            'uri','name','link','privacy','created_time','duration',
            'pictures.sizes.link',
            // 'download' // uncomment if you need direct file links (requires video_files)
        ]);

        $result = $vimeo->listAllMyVideos(100, $fields);

        return response()->json([
            'total'  => $result['total'],
            'videos' => $result['videos'],
        ]);
    }
}
