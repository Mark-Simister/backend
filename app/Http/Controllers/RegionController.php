<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class RegionController extends Controller
{
     // Map regions; US is also the GLOBAL fallback
    private array $map = [
        'AU' => 'https://au.fstg.beastierated.com/',
        'CA' => 'https://ca.fstg.beastierated.com/',
        'UK' => 'https://uk.fstg.beastierated.com/',
        'US' => 'https://us.fstg.beastierated.com/', // GLOBAL / default
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // Web CRUD permissions
            new Middleware('permission:region.view',   only: ['index']),
            new Middleware('permission:region.create', only: ['create','store']),
            new Middleware('permission:region.edit',   only: ['edit','update']),
            new Middleware('permission:region.delete', only: ['destroy']),
        ];
    }
    /* ---------------- WEB CRUD ---------------- */

    public function index()
    {
        $regions = Region::latest()->paginate(10);
        return view('admin.regions.index', compact('regions'));
    }

    public function create()
    {
        return view('admin.regions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code',
            'currency'    => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        Region::create($request->all());

        return redirect()->route('admin.regions.index')
            ->with('success', 'Region created successfully!');
    }

    public function edit(Region $region)
    {
        return view('admin.regions.edit', compact('region'));
    }

    public function update(Request $request, Region $region)
    {
        $request->validate([
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code,' . $region->id,
            'currency'    => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $region->update($request->all());

        return redirect()->route('admin.regions.index')
            ->with('success', 'Region updated successfully!');
    }

    public function destroy(Region $region)
    {
        $region->delete();
        return redirect()->route('admin.regions.index')
            ->with('success', 'Region deleted successfully!');
    }

    /* ---------------- API CRUD ---------------- */

    public function index_api()
    {
        $regions = Region::latest()->get();

        return response()->json([
            'status'  => true,
            'message' => 'Regions fetched successfully',
            'data'    => $regions
        ]);
    }

    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code',
            'currency'    => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $region = Region::create($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Region created successfully',
            'data'    => $region
        ], 201);
    }

    public function show_api($id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status'  => false,
                'message' => 'Region not found'
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Region details fetched successfully',
            'data'    => $region
        ]);
    }

    public function update_api(Request $request, $id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status'  => false,
                'message' => 'Region not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code,' . $id,
            'currency'    => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation errors',
                'errors'  => $validator->errors()
            ], 422);
        }

        $region->update($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Region updated successfully',
            'data'    => $region
        ]);
    }

    public function destroy_api($id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status'  => false,
                'message' => 'Region not found'
            ], 404);
        }

        $region->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Region deleted successfully'
        ]);
    }



    

    // API to Redirect to regions
    // public function json(Request $request)
    // {
    //     [$country, $ip] = $this->countryFromIp($request);
    //     $url = $this->pickUrl($country);

    //     return response()->json([
    //         'url'          => $url,
    //         'country_code' => $country,
    //         'region'       => $url === $this->map['US'] ? 'GLOBAL' : $country,
    //         'client_ip'    => $ip,
    //     ]);
    // }

    // public function redirect(Request $request)
    // {
    //     [$country] = $this->countryFromIp($request);
    //     return redirect()->away($this->pickUrl($country), 302);
    // }

    // private function pickUrl(string $country): string
    // {
    //     return $this->map[$country] ?? $this->map['US'];
    // }

    private function countryFromIp(Request $request): array
    {
        // Try common proxy/CDN headers first, then Laravel's $request->ip()
        $ip = $request->headers->get('CF-Connecting-IP')
           ?? $request->headers->get('X-Forwarded-For')
           ?? $request->ip();

        // Call a global IP info API (ipapi.co). No API key needed for basic use.
        // Docs: https://ipapi.co/api/#complete-location
        try {
            $resp = Http::timeout(3)
                ->get("https://ipapi.co/{$ip}/json/");
            $code = strtoupper(
                $resp->json('country')              // e.g., "US"
                ?? $resp->json('country_code')      // alt field name on some services
                ?? 'US'
            );
        } catch (\Throwable $e) {
            $code = 'US';
        }

        // Normalize GB -> UK to match your table/URLs
        if ($code === 'GB') $code = 'UK';

        return [$code, $ip];
    }
}
