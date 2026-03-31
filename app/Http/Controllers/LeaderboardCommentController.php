<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardComment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class LeaderboardCommentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'comment' => 'required|string|max:1000',
            'type' => ['required', Rule::in(['pet','people','global'])], 
        ]);

        $comment = LeaderboardComment::create([
            'user_id' => $request->user_id,
            'comment' => $request->comment,
            'type' => $request->type,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    public function index(Request $request)
    {
        //dd("index");
        $query = LeaderboardComment::with('user')->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('type')) {
            $typesArray = array_map('trim', explode(',', $request->type));
            $query->whereIn('type', $typesArray);
        }
        $comments = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Comments fetched successfully',
            'data' => $comments
        ]);
    }

    public function leaderboard(Request $request)
    {
        // Allow multiple types (comma-separated)
        $types = $request->input('type', 'global'); 

        // Convert string like "pet,people" → ['pet','people']
        $typesArray = array_map('trim', explode(',', $types));

        // Validate each type value
        $validTypes = ['pet', 'people', 'global'];
        $typesArray = array_intersect($typesArray, $validTypes);

        if (empty($typesArray)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type parameter.',
            ], 400);
        }

        $leaders = \DB::table('leaderboardcomments')
            ->join('users', 'leaderboardcomments.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                \DB::raw('COUNT(leaderboardcomments.id) as total_comments')
            )
            ->whereIn('leaderboardcomments.type', $typesArray) 
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_comments')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Leaderboard fetched successfully for types: ' . implode(', ', $typesArray),
            'data' => $leaders
        ]);
    }
    
}