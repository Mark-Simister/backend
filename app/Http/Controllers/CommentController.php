<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    // GET /api/videos/{video}/comments
    public function index(Video $video)
    {
        $comments = $video->topLevelComments()
            ->with(['user', 'replies.user'])
            ->withCount('replies')
            ->paginate(20);

        return response()->json($comments);
    }

    // POST /api/videos/{video}/comments
    public function store(Request $request, Video $video)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', Rule::exists('comments', 'id')],
        ]);

        // if replying, ensure the parent belongs to the same video
        if (!empty($data['parent_id'])) {
            $parent = Comment::where('id', $data['parent_id'])
                ->where('video_id', $video->id)
                ->firstOrFail();
        }

        $comment = Comment::create([
            'video_id'  => $video->id,
            'user_id'   => optional($request->user())->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body'      => $data['body'],
        ]);

        // maintain counter cache
        if (!empty($data['parent_id'])) {
            Comment::where('id', $data['parent_id'])->increment('replies_count');
        }

        return response()->json($comment->load('user'), 201);
    }

    // GET /api/comments/{comment}
    public function show(Comment $comment)
    {
        $comment->load(['user', 'video']);
        return response()->json($comment);
    }

    // GET /api/comments/{comment}/thread
    public function thread(Comment $comment)
    {
        $comment->load([
            'user',
            'replies.user',
            'replies.replies.user',          // 3 levels deep
            'replies.replies.replies.user',
        ])->loadCount('replies');

        return response()->json($comment);
    }

    // PATCH /api/comments/{comment}
    public function update(Request $request, Comment $comment)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update(['body' => $data['body']]);

        return response()->json($comment->fresh('user'));
    }

    // DELETE /api/comments/{comment}
    public function destroy(Comment $comment)
    {
        $parentId = $comment->parent_id;

        $comment->delete();

        if ($parentId) {
            Comment::where('id', $parentId)->where('replies_count', '>', 0)->decrement('replies_count');
        }

        return response()->json(['deleted' => true]);
    }
}
