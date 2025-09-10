<?php

namespace App\Http\Controllers;

use App\Models\Region;
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


}
