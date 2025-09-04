<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
     // Select2 AJAX search: /admin/tags?q=term&page=1
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $tags = Tag::when($q, fn($qry) => $qry->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'results' => $tags->map(fn($t) => ['id' => $t->id, 'text' => $t->name])->values(),
            'pagination' => ['more' => $tags->hasMorePages()],
        ]);
    }

    // Create if not exists
    public function store(Request $request)
    {
        $name = trim($request->validate(['name' => 'required|string|max:100'])['name']);
        $tag = Tag::firstOrCreate(['name' => $name]);
        return response()->json(['id' => $tag->id, 'text' => $tag->name]);
    }

    public function byIds(Request $request)
{
    $ids = collect($request->input('ids', []))
        ->map(fn($v) => (int) $v)
        ->filter()
        ->unique()
        ->values();

    if ($ids->isEmpty()) {
        return response()->json(['results' => []]);
    }

    $tags = Tag::whereIn('id', $ids)->orderBy('name')->get(['id','name']);

    return response()->json([
        'results' => $tags->map(fn($t) => ['id' => $t->id, 'text' => $t->name])->values(),
    ]);
}
}
