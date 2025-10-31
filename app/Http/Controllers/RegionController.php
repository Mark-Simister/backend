<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controllers\Middleware;

class RegionController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // Web CRUD permissions
            new Middleware('permission:region.view', only: ['index']),
            new Middleware('permission:region.create', only: ['create', 'store']),
            new Middleware('permission:region.edit', only: ['edit', 'update']),
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
        $currencies = Currency::all(['id', 'currency_code', 'currency_name', 'currency_symbol']);
        return view('admin.regions.create', compact('currencies'));
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'region_name' => 'required|string|max:255',
    //         'region_code' => 'required|string|max:50|unique:regions,region_code',
    //         'currency' => 'required|string|max:10',
    //         'currency_symbol' => 'required|string|max:10',
    //         'description' => 'nullable|string',
    //         'is_active' => 'boolean',
    //         'motif_color' => 'nullable|string|max:20',
    //         'motif_type'  => 'nullable|string|max:50',
    //         'opacity'     => 'nullable|numeric|min:0|max:1',
    //     ]);

    //     Region::create($request->all());

    //     return redirect()->route('admin.regions.index')
    //         ->with('success', 'Region created successfully!');
    // }
    public function store(Request $request)
{
    $request->validate([
        'region_name' => 'required|string|max:255',
        'region_code' => 'required|string|max:50|unique:regions,region_code',
        'currency' => 'required|string|max:10',
        'currency_symbol' => 'required|string|max:10',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
        'motif_color' => 'nullable|string|max:20',
        'motif_type'  => 'nullable|string|max:50',
        'opacity'     => 'nullable|numeric|min:0|max:1',
        'motif_color_1' => 'nullable|string|max:20',
        'motif_color_2' => 'nullable|string|max:20',
        'motif_color_3' => 'nullable|string|max:20',
    ]);

    // Handle motif color logic
    $motifColor = $request->motif_color;

    if (in_array($request->motif_type, ['gradient', 'pattern'])) {
        $colors = array_filter([
            $request->motif_color_1,
            $request->motif_color_2,
            $request->motif_color_3,
        ]);
        $motifColor = implode(',', $colors); // store as comma-separated string
    }

    Region::create([
        'region_name'     => $request->region_name,
        'region_code'     => $request->region_code,
        'currency'        => $request->currency,
        'currency_symbol' => $request->currency_symbol,
        'description'     => $request->description,
        'is_active'       => $request->is_active,
        'motif_color'     => $motifColor,
        'motif_type'      => $request->motif_type,
        'opacity'         => $request->opacity,
    ]);

    return redirect()->route('admin.regions.index')
        ->with('success', 'Region created successfully!');
}


    public function edit(Region $region)
    {
        $currencies = Currency::all(['id', 'currency_code', 'currency_name', 'currency_symbol']);
        return view('admin.regions.edit', compact('region', 'currencies'));
    }


    // public function update(Request $request, Region $region)
    // {
    //     $request->validate([
    //         'region_name' => 'required|string|max:255',
    //         'region_code' => 'required|string|max:50|unique:regions,region_code,' . $region->id,
    //         'currency' => 'required|string|max:10',
    //         'currency_symbol' => 'required|string|max:10',
    //         'description' => 'nullable|string',
    //         'is_active' => 'boolean',
    //         'motif_color' => 'nullable|string|max:20',
    //         'motif_type'  => 'nullable|string|max:50',
    //         'opacity'     => 'nullable|numeric|min:0|max:1',
    //     ]);

    //     $region->update($request->all());

    //     return redirect()->route('admin.regions.index')
    //         ->with('success', 'Region updated successfully!');
    // }
    public function update(Request $request, Region $region)
{
    $request->validate([
        'region_name' => 'required|string|max:255',
        'region_code' => 'required|string|max:50|unique:regions,region_code,' . $region->id,
        'currency' => 'required|string|max:10',
        'currency_symbol' => 'required|string|max:10',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
        'motif_color' => 'nullable|string|max:20',
        'motif_type'  => 'nullable|string|max:50',
        'opacity'     => 'nullable|numeric|min:0|max:1',
        'motif_color_1' => 'nullable|string|max:20',
        'motif_color_2' => 'nullable|string|max:20',
        'motif_color_3' => 'nullable|string|max:20',
    ]);

    // Handle motif color logic
    $motifColor = $request->motif_color;

    if (in_array($request->motif_type, ['gradient', 'pattern'])) {
        $colors = array_filter([
            $request->motif_color_1,
            $request->motif_color_2,
            $request->motif_color_3,
        ]);
        $motifColor = implode(',', $colors); // store multiple colors as comma-separated string
    }

    $region->update([
        'region_name'     => $request->region_name,
        'region_code'     => $request->region_code,
        'currency'        => $request->currency,
        'currency_symbol' => $request->currency_symbol,
        'description'     => $request->description,
        'is_active'       => $request->is_active,
        'motif_color'     => $motifColor,
        'motif_type'      => $request->motif_type,
        'opacity'         => $request->opacity,
    ]);

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

    // public function index_api()
    // {
    //     $regions = Region::latest()->get();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Regions fetched successfully',
    //         'data'    => $regions
    //     ]);
    // }
    public function index_api()
{
    $regions = Region::where('is_active', 1)
        ->with('currency_get')
        ->latest()
        ->get();

    $regionsData = $regions->map(function ($region) {
        $motifColors = $region->motif_color;

        if ($region->motif_type !== 'solid') {
            if (is_string($motifColors)) {
                $decoded = json_decode($motifColors, true);
                $motifColors = is_array($decoded) ? $decoded : [$motifColors];
            } elseif (is_array($motifColors)) {
                $motifColors = $motifColors;
            } else {
                $motifColors = [$motifColors];
            }
        }

        return [
            'id' => $region->id,
            'region_name' => $region->region_name,
            'region_code' => $region->region_code,
            'description' => $region->description,
            'is_active' => $region->is_active,
            'currency' => $region->currency,
            'motif_type' => $region->motif_type,
            'motif_color' => $motifColors, // array if not solid
            'opacity' => $region->opacity,
            'created_at' => $region->created_at,
            'updated_at' => $region->updated_at,
            'currency_symbol' => $region->currency_get ? $region->currency_get->currency_symbol : null,
        ];
    });

    return response()->json([
        'status' => true,
        'message' => 'Regions fetched successfully',
        'data' => $regionsData,
    ]);
}



    public function store_api(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code',
            'currency' => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $region = Region::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Region created successfully',
            'data' => $region
        ], 201);
    }

    public function show_api($id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status' => false,
                'message' => 'Region not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Region details fetched successfully',
            'data' => $region
        ]);
    }

    public function update_api(Request $request, $id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status' => false,
                'message' => 'Region not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'region_name' => 'required|string|max:255',
            'region_code' => 'required|string|max:50|unique:regions,region_code,' . $id,
            'currency' => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $region->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Region updated successfully',
            'data' => $region
        ]);
    }

    public function destroy_api($id)
    {
        $region = Region::find($id);

        if (!$region) {
            return response()->json([
                'status' => false,
                'message' => 'Region not found'
            ], 404);
        }

        $region->delete();

        return response()->json([
            'status' => true,
            'message' => 'Region deleted successfully'
        ]);
    }
}
