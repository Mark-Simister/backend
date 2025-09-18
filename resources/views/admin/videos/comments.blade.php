@extends('layouts.admin.master')

@section('title', 'Video Comments')

@push('styles')
    <style>
        /* Custom styles for comments (same as before) */
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .btn-edit {
            background-color: #ffc107;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-edit:hover {
            background-color: #e0a800;
        }
    </style>
@endpush

@section('content')
    <div class="container comments-container">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between mb-4">
            <h2 class="mb-0">Comments for Video: {{ $video->title }}</h2>
            <a href="{{ route('admin.videos.index') }}" class="btn btn-back">Back to Videos</a>
        </div>

        @foreach ($comments as $comment)
            <div class="comment-card">
                <!-- Avatar -->
                <img src="{{ $comment['user']->profile_image ?? 'https://via.placeholder.com/60' }}" class="comment-avatar" alt="User Avatar">

                <div class="comment-content">
                    <div class="comment-header">
                        <span>{{ $comment['name'] }}</span>
                        <small>{{ $comment['created_at'] }}</small>

                        <!-- Delete Form -->
                        <form action="{{ route('admin.comments.delete', $comment['id']) }}" method="POST" class="d-inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete">Delete Comment</button>
                        </form>
                    </div>

                    <div class="comment-body">
                        <p>{{ $comment['body'] }}</p>
                    </div>

                    <!-- Replies Section -->
                    @if (count($comment['replies']) > 0)
                        <div class="replies-section">
                            @foreach ($comment['replies'] as $reply)
                                <div class="reply-card">
                                    <div class="reply-header">{{ $reply['name'] }}:</div>
                                    <p>{{ $reply['body'] }}</p>

                                    <!-- Delete Reply Button -->
                                    <form action="{{ route('admin.replies.delete', $reply['id']) }}" method="POST" class="d-inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-delete">Delete Reply</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="no-replies">
                            <p>No replies yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <!-- Pagination for comments -->
        <div class="d-flex justify-content-center mt-4">
            {{ $comments->links() }}
        </div>
    </div>
@endsection
