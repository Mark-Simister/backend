@extends('layouts.admin.master')

@section('title', isset($review) ? 'Edit Review' : 'Add Review')

@section('content')
    <div class="card">
        {{-- Success flash --}}
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Whoops! There were some problems with your input:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-body">
            <h4>{{ isset($review) ? 'Edit Review' : 'Add Review' }}</h4>

            <form action="{{ isset($review) ? route('admin.reviews.update', $review) : route('admin.reviews.store') }}"
                method="POST">
                @csrf
                @if (isset($review))
                    @method('PUT')
                @endif

                {{-- VIDEO --}}
                <div class="form-group">
                    <label>Video <span class="text-danger">*</span></label>
                    <select name="video_id" class="form-control" required>
                        <option value="">Select Video</option>
                        @foreach ($videos as $v)
                            <option value="{{ $v->id }}" @selected(old('video_id', isset($review) ? $review->video_id : $video->id ?? '') == $v->id)>
                                #{{ $v->id }} — {{ $v->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('video_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                {{-- USER --}}
                {{-- <div class="form-group mt-3">
                    <label>User <span class="text-danger">*</span></label>
                    <select name="user_id" class="form-control" required>
                        <option value="">Select User</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id', isset($review) ? $review->user_id : '') == $u->id)>
                                #{{ $u->id }} — {{ $u->name ?? 'User' }}
                                {{ isset($u->email) ? "({$u->email})" : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div> --}}

                {{-- RATING --}}
                <div class="form-group mt-3">
                    <label>Rating (1–5) <span class="text-danger">*</span></label>
                    <input type="number" name="rating" class="form-control" min="1" max="5"
                        value="{{ old('rating', isset($review) ? $review->rating : '') }}" required>
                    @error('rating')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                {{-- REVIEW TEXT --}}
                <div class="form-group mt-3">
                    <label>Review <span class="text-danger">*</span></label>
                    <textarea name="review" class="form-control" rows="5" required>{{ old('review', isset($review) ? $review->review : '') }}</textarea>
                    @error('review')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                {{-- STATUS (Admin moderation) --}}
                <div class="form-group mt-3">
                    <label>Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-control" required>
                        @foreach (['pending', 'approved', 'rejected'] as $st)
                            <option value="{{ $st }}" @selected(old('status', isset($review) ? $review->status : 'pending') === $st)>
                                {{ ucfirst($st) }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">{{ isset($review) ? 'Update' : 'Save' }}</button>
                    <a href="{{ route('admin.reviews.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
