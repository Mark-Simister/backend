@extends('layouts.admin.master')

@section('title', 'Edit Review')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Edit Review</h4>

            <form action="{{ route('admin.reviews.update', $review->id) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Video --}}
                <div class="mb-3">
                    <label for="video_id" class="form-label">Video</label>
                    <select name="video_id" id="video_id" class="form-control" required>
                        <option value="">-- Select Video --</option>
                        @foreach($videos as $video)
                            <option value="{{ $video->id }}" {{ $review->video_id == $video->id ? 'selected' : '' }}>
                                {{ $video->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('video_id')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                {{-- User --}}
                {{-- <div class="mb-3">
                    <label for="user_id" class="form-label">User</label>
                    <select name="user_id" id="user_id" class="form-control" required>
                        <option value="">-- Select User --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $review->user_id == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div> --}}

                {{-- Rating --}}
                <div class="mb-3">
                    <label for="rating" class="form-label">Rating</label>
                    <select name="rating" id="rating" class="form-control" required>
                        <option value="">-- Select Rating --</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" {{ $review->rating == $i ? 'selected' : '' }}>
                                {{ $i }} Star{{ $i > 1 ? 's' : '' }}
                            </option>
                        @endfor
                    </select>
                    @error('rating')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Review Text --}}
                <div class="mb-3">
                    <label for="review" class="form-label">Review</label>
                    <textarea name="review" id="review" rows="4" class="form-control" required>{{ old('review', $review->review) }}</textarea>
                    @error('review')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Status --}}
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="pending"  {{ $review->status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ $review->status == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ $review->status == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                    @error('status')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Update Review</button>
                <a href="{{ route('admin.reviews.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
