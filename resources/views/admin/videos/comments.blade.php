@extends('layouts.admin.master')

@section('title', 'Video Comments')

@push('styles')
    <style>
        /*  */
    </style>
@endpush

@section('content')
    <div class="container comments-container">
        <!-- Display success message -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Display error message -->
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between mb-4">
            <h2 class="mb-0">Comments for Video: {{ $video->title }}</h2>
            <a href="{{ route('admin.videos.index') }}" class="btn btn-warning btn-back">Back to Videos</a>
        </div>

        @if ($comments->isEmpty())
            <div class="no-comments">
                <p><strong>Sorry, no comments for this video yet.</strong></p>
            </div>
        @else
            @foreach ($comments as $comment)
                <div class="comment-card">
                    <div class="comment-header">
                        <span>{{ $comment['name'] }}</span>
                        <small>{{ $comment['created_at'] }}</small>
                        <!-- Delete Form -->
                        <form action="{{ route('admin.comments.delete', $comment['id']) }}" method="POST"
                            class="d-inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-delete btn-danger">Delete Comment</button>
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
                                    <form action="{{ route('admin.replies.delete', $reply['id']) }}" method="POST"
                                        class="d-inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-delete btn-danger">Delete Reply</button>
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
            @endforeach
        @endif
    </div>

    <script>
        // Hide success or error message after 5 seconds
        setTimeout(function() {
            var successAlert = document.getElementById('success-alert');
            var errorAlert = document.getElementById('error-alert');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
        }, 5000);
    </script>
@endsection
