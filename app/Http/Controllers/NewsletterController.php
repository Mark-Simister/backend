<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function index()
    {
        $emails = NewsletterSubscription::orderBy('created_at', 'desc')->get();
        return view('admin.newsletter.index', compact('emails'));
    }

    // Api
    
    public function subscribe_api(Request $request)
{
    // Validate the email input
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|unique:newsletter_subscriptions,email',
    ]);

    if ($validator->fails()) {
        if ($validator->errors()->has('email')) {
            return response()->json([
                'errors' => [
                    'email' => [
                        'You have already subscribed to the newsletter.'
                    ]
                ]
            ], 422);
        }
        return response()->json(['errors' => $validator->errors()], 422);
    }

    NewsletterSubscription::create([
        'email' => $request->email
    ]);

    return response()->json(['message' => 'Subscription successful.'], 200);
}

    
    public function index_api()
    {
        $emails = NewsletterSubscription::latest()->get();
        return response()->json($emails);
    }
}
