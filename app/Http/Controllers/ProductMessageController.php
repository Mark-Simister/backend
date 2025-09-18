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
    $user = $request->user('api') ?? $request->user('sanctum');

    // Validate input (validation applies regardless of authentication)
    $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'message' => 'required|string|max:500',
        'subject' => 'required|string|max:255',
    ]);

    $firstName = $request->input('first_name');
    $lastName = $request->input('last_name');
    $name = $firstName . ' ' . $lastName;

    $email = $request->input('email');
    $subject = $request->input('subject');
    $message = $request->input('message');

    // Create the product message
    $productMessage = ProductMessage::create([
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'subject' => $subject,
    ]);

    return response()->json([
        'status' => 'success',
        'data' => $productMessage
    ]);
}







}
