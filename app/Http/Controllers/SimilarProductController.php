<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Video;

class SimilarProductController extends Controller
{
    // Fetch products by region
public function fetchByRegion(Video $video, $regionId)
{
    $products = \DB::table('similar_products')
        ->where('video_id', $video->id)
        ->where('region_id', $regionId)
        ->get();

    return response()->json($products);
}

// Store new product
// Store new product
public function store(Request $request, Video $video)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'short_description' => 'nullable|string|max:1000',
        'url' => 'nullable|url|max:255',
        'image' => 'nullable|file|image|max:8192', // 8 MB
        'region_id' => 'required|integer',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $file = $request->file('image');
        $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $file->move(public_path('/similar_products'), $filename);
        $imagePath = '/similar_products/' . $filename;
    }

    \DB::table('similar_products')->insert([
        'video_id' => $video->id,
        'region_id' => $data['region_id'],
        'name' => $data['name'],
        'short_description' => $data['short_description'] ?? null,
        'url' => $data['url'] ?? null,
        'image' => $imagePath,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['success' => true]);
}

// Update existing product
public function update(Request $request, $productId)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'short_description' => 'nullable|string|max:1000',
        'url' => 'nullable|url|max:255',
        'image' => 'nullable|file|image|max:8192', // 8 MB
    ]);

    $updateData = [
        'name' => $data['name'],
        'short_description' => $data['short_description'] ?? null,
        'url' => $data['url'] ?? null,
        'updated_at' => now(),
    ];

    if ($request->hasFile('image')) {
        $file = $request->file('image');
        $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $file->move(public_path('/similar_products'), $filename);
        $updateData['image'] = '/similar_products/' . $filename;
    }

    \DB::table('similar_products')->where('id', $productId)->update($updateData);

    return response()->json(['success' => true]);
}


// Delete product
public function destroy($productId)
{
    \DB::table('similar_products')->where('id', $productId)->delete();
    return response()->json(['success' => true]);
}
}
