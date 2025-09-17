<?php

namespace App\Http\Controllers;

use App\Models\ProductMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductMessageController extends Controller
{
    // Show list of all product messages
    public function index()
    {
        $productMessages = ProductMessage::all();
        return view('admin.product-messages.index', compact('productMessages'));
    }

    // Show a specific product message
    public function show($id)
    {
        $productMessage = ProductMessage::findOrFail($id);
        return view('admin.product-messages.show', compact('productMessage'));
    }

    // Delete a specific product message
    public function destroy($id)
    {
        $productMessage = ProductMessage::findOrFail($id);
        $productMessage->delete();

        return redirect()->route('admin.product-messages.index')->with('success', 'Product message deleted successfully.');
    }

    
    // APi
    // Store product message
    public function store(Request $request)
{
    // Check if the user is authenticated via 'api' or 'sanctum' guard
    $user = $request->user('api') ?? $request->user('sanctum') ?? null;

    if ($user) {
        // If logged in, fetch name and email from the Auth token
        $name = $user->name;
        $email = $user->email;
    } else {
        // If not logged in, request first name, last name, email, and message from the user
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:500', // Added message validation
        ]);

        // Combine first name and last name to create the full name
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');
        $name = $firstName . ' ' . $lastName;

        $email = $request->input('email');
    }

    // Store the product message
    $productMessage = ProductMessage::create([
        'name' => $name,
        'email' => $email,
        'message' => $request->input('message')
    ]);

    return response()->json(['status' => 'success', 'data' => $productMessage]);
}




}
