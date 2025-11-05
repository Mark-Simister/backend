<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $faqs = Faq::latest()->get();
        return view('admin.faqs.index', compact('faqs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.faqs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
        ]);

        Faq::create($request->only(['question', 'answer']));

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
{
    $faq = Faq::find($id);

    if (!$faq) {
        return redirect()->route('admin.faqs.index')->with('error', 'FAQ not found!');
    }

    return view('admin.faqs.edit', compact('faq'));
}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    $faq = Faq::find($id);

    if (!$faq) {
        return redirect()->route('admin.faqs.index')->with('error', 'FAQ not found!');
    }

    $request->validate([
        'question' => 'required|string|max:255',
        'answer' => 'required|string',
    ]);

    $faq->update($request->only(['question', 'answer']));

    return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated successfully!');
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
{
    $faq = Faq::find($id);

    if (!$faq) {
        return redirect()->route('admin.faqs.index')->with('error', 'FAQ not found!');
    }

    $faq->delete();

    return back()->with('success', 'FAQ deleted successfully!');
}

public function index_api(): JsonResponse
{
    $faqs = Faq::select('id', 'question', 'answer')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($faq) {
            $faq->answer = strip_tags($faq->answer);
            return $faq;
        });

    return response()->json([
        'success' => true,
        'data' => $faqs,
    ]);
}


}
