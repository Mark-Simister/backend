<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionListing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SubscriptionListingController extends Controller
{
    public function index()
    {
        $subscriptionListings = SubscriptionListing::all();
        return view('admin.subscription_listing.index', compact('subscriptionListings'));
    }

    public function create()
    {
        return view('admin.subscription_listing.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'sub_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|string',
            'type' => 'required|in:one_time,recurring',
        ]);

        SubscriptionListing::create($request->all());

        return redirect()->route('admin.subscription_listing.index')->with('success', 'Subscription Package created successfully!');
    }

    public function edit($id)
    {
        $subscriptionListing = SubscriptionListing::findOrFail($id);
        return view('admin.subscription_listing.edit', compact('subscriptionListing'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'sub_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|string',
            'type' => 'required|in:one_time,recurring',
        ]);

        $subscription = SubscriptionListing::findOrFail($id);
        $subscription->update($request->all());

        return redirect()->route('admin.subscription_listing.index')->with('success', 'Subscription Package updated successfully!');
    }

    public function destroy($id)
    {
        $subscription = SubscriptionListing::findOrFail($id);
        $subscription->delete();

        return redirect()->route('admin.subscription_listing.index')->with('success', 'Subscription Package deleted successfully!');
    }

    public function index_api()
    {
        $plans = DB::table('subscription_listing')
            ->select('id','subscription_name','sub_description','price','duration','duration_unit','type')
            ->orderBy('id')
            ->get();

        return response()->json($plans);
    }
}
