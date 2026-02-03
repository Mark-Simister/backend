<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardComment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class LeaderboardCommentController extends Controller
{
    // POST /api/leaderboardcomments
    public function store(Request $request)
    {
        //dd("store");
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'comment' => 'required|string|max:1000',
        ]);

        $comment = LeaderboardComment::create([
            'user_id' => $request->user_id,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Comment added successfully',
            'data' => $comment
        ], 201);
    }

    // GET /api/leaderboardcomments
    public function index(Request $request)
    {
        //dd("index");
        $query = LeaderboardComment::with('user')->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $comments = $query->get();

        return response()->json([
            'status' => true,
            'message' => 'Comments fetched successfully',
            'data' => $comments
        ]);
    }

public function leaderboard()
{
    $leaders = \DB::table('leaderboardcomments')
        ->join('users', 'leaderboardcomments.user_id', '=', 'users.id')
        ->select(
            'users.id',
            'users.name',
            \DB::raw('COUNT(leaderboardcomments.id) as total_comments')
        )
        ->groupBy('users.id', 'users.name')
        ->orderByDesc('total_comments')
        ->limit(10)
        ->get();

    return response()->json([
        'status' => true,
        'message' => 'Leaderboard fetched successfully',
        'data' => $leaders
    ]);
}


}