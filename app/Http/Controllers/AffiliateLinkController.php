<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Models\Region;
use App\Models\Video;
use Illuminate\Http\Request;

class AffiliateLinkController extends Controller
{
     public function index()
    {
        // Fetch all affiliate links
        $affiliateLinks = AffiliateLink::with(['video', 'region'])->get();

        return view('admin.affiliate-links.index', compact('affiliateLinks'));
    }

    public function create()
    {
        // Fetch videos and regions to choose from
        $videos = Video::all();
        $regions = Region::all();

        return view('admin.affiliate-links.create', compact('videos', 'regions'));
    }

    // Store a new affiliate link
public function store(Request $request, $videoId)
{
    $request->validate([
        'video_id' => 'required|exists:videos,id',
        'region_id' => 'required|exists:regions,id',
        'retailer' => 'required|string|max:255',
        'url' => 'required|url',
    ]);

    try {
        // Create the affiliate link
        AffiliateLink::create([
            'video_id' => $videoId,
            'region_id' => $request->region_id,
            'retailer' => $request->retailer,
            'url' => $request->url,
        ]);

        return back()->with('success', 'Affiliate link created successfully.');

    } catch (\Illuminate\Database\QueryException $e) {
        // Catch the Duplicate entry error
        if ($e->getCode() === '23000') {  // 23000 is the code for Integrity constraint violation (duplicate)
            return back()->withErrors(['retailer' => 'This affiliate link already exists for the selected video and region.'])->withInput();
        }

        // If it's another type of error, rethrow it
        throw $e;
    }
}

public function updateAffiliateLink(Request $request, $videoId, $affiliateLinkId)
{
    $request->validate([
        'region_id' => 'required|exists:regions,id',
        'retailer' => 'required|string|max:255',
        'url' => 'required|url',
    ]);

    try {
        // Find the affiliate link and update it
        $affiliateLink = AffiliateLink::findOrFail($affiliateLinkId);
        $affiliateLink->update([
            'region_id' => $request->region_id,
            'retailer' => $request->retailer,
            'url' => $request->url,
        ]);

        return back()->with('success', 'Affiliate link updated successfully.');

    } catch (\Illuminate\Database\QueryException $e) {
        // Catch the Duplicate entry error
        if ($e->getCode() === '23000') {  // 23000 is the code for Integrity constraint violation (duplicate)
            return back()->withErrors(['retailer' => 'This affiliate link already exists for the selected video and region.'])->withInput();
        }

        // If it's another type of error, rethrow it
        throw $e;
    }
}







    public function destroy($videoId, $affiliateLinkId)
{
    $affiliateLink = AffiliateLink::where('video_id', $videoId)->findOrFail($affiliateLinkId);
    $affiliateLink->delete();

    return redirect()->route('admin.videos.affiliate-links', $videoId)->with('success', 'Affiliate link deleted successfully.');
}

}
