<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    private function getVideoOrFail($videoId)
    {
        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'status' => false,
                'message' => 'Video not found.',
                'data' => []
            ], 404);
        }

        return $video;
    }

    public function index($videoId)
    {
        
        // Get the video, or return an error if not found
        $video = $this->getVideoOrFail($videoId);
        if ($video instanceof \Illuminate\Http\JsonResponse) {
            return $video; // Return the error response if video is not found
        }

        $comments = $video->topLevelComments()
            ->with(['user', 'replies.user'])
            ->withCount('replies')
            ->paginate(20);

        $formattedComments = $comments->getCollection()->map(function ($comment) {
            return [
                'id' => $comment->id,
                'video_id' => $comment->video_id,
                'user_id' => $comment->user_id,
                'name' => $comment->user->name ?? null,
                'email' => $comment->user->email ?? null,
                'profile_image' => $comment->user->profile_image ?? null,
                'parent_id' => $comment->parent_id,
                'body' => $comment->body,
                'replies_count' => $comment->replies_count,
                'created_at' => $comment->created_at->toISOString(),
                'updated_at' => $comment->updated_at->toISOString(),
                'replies' => $comment->replies->isEmpty() ? [] : $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'user_id' => $reply->user_id,
                        'name' => $reply->user->name ?? null,
                        'email' => $reply->user->email ?? null,
                        'profile_image' => $reply->user->profile_image ?? null,
                        'parent_id' => $reply->parent_id,
                        'body' => $reply->body,
                        'created_at' => $reply->created_at->toISOString(),
                        'updated_at' => $reply->updated_at->toISOString(),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Comments retrieved successfully.',
            'data' => [
                'current_page' => $comments->currentPage(),
                'data' => $formattedComments,
                'first_page_url' => $comments->url(1),
                'from' => $comments->firstItem(),
                'last_page' => $comments->lastPage(),
                'last_page_url' => $comments->url($comments->lastPage()),
                'links' => $comments->links(),
                'next_page_url' => $comments->nextPageUrl(),
                'path' => $comments->path(),
                'per_page' => $comments->perPage(),
                'prev_page_url' => $comments->previousPageUrl(),
                'to' => $comments->lastItem(),
                'total' => $comments->total(),
            ],
        ]);
    }



    public function store(Request $request, $videoId)
    {
        // Get the video, or return an error if not found
        $video = $this->getVideoOrFail($videoId);
        if ($video instanceof \Illuminate\Http\JsonResponse) {
            return $video; // Return the error response if video is not found
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', Rule::exists('comments', 'id')],
        ]);

        // If replying, ensure the parent belongs to the same video
        if (!empty($data['parent_id'])) {
            $parent = Comment::where('id', $data['parent_id'])
                ->where('video_id', $video->id)
                ->firstOrFail();
        }

        // Create the new comment
        $comment = Comment::create([
            'video_id' => $video->id,
            'user_id' => optional($request->user())->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        // Maintain counter cache if this is a reply
        if (!empty($data['parent_id'])) {
            Comment::where('id', $data['parent_id'])->increment('replies_count');
        }

        $commentData = $comment->load('user')->toArray();
        $formattedComment = [
            'video_id' => $commentData['video_id'],
            'user_id' => $commentData['user_id'],
            'name' => $commentData['user']['name'] ?? null,
            'email' => $commentData['user']['email'] ?? null,
            'profile_image' => $commentData['user']['profile_image'] ?? null,
            'parent_id' => $commentData['parent_id'],
            'body' => $commentData['body'],
            'updated_at' => $commentData['updated_at'],
            'created_at' => $commentData['created_at'],
            'id' => $commentData['id'],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Comment created successfully.',
            'data' => $formattedComment
        ], 201);
    }


    public function show($commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $comment->load(['user', 'video']);
        return response()->json([
            'status' => true,
            'message' => 'Comment retrieved successfully.',
            'data' => $comment
        ]);
    }

    public function thread($commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $comment->load([
            'user',
            'replies.user',
            'replies.replies.user',
            'replies.replies.replies.user',
        ])->loadCount('replies');

        return response()->json([
            'status' => true,
            'message' => 'Thread retrieved successfully.',
            'data' => $comment
        ]);
    }

    public function update(Request $request, $commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update(['body' => $data['body']]);

        return response()->json([
            'status' => true,
            'message' => 'Comment updated successfully.',
            'data' => $comment->fresh('user')
        ]);
    }

    public function destroy($commentId)
    {
        $comment = Comment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => false,
                'message' => 'Comment not found.',
                'data' => []
            ], 404);
        }

        $parentId = $comment->parent_id;

        $comment->delete();

        if ($parentId) {
            Comment::where('id', $parentId)->where('replies_count', '>', 0)->decrement('replies_count');
        }

        return response()->json([
            'status' => true,
            'message' => 'Comment deleted successfully.',
            'data' => ['deleted' => true]
        ]);
    }

}
