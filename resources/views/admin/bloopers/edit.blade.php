@extends('layouts.admin.master')
@section('title', 'Edit Blooper')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Blooper for "{{ $character->name }}"</h4>

        @can('bloopers.manage')
        <form action="{{ route('admin.bloopers.update', ['character' => $character, 'blooper' => $blooper]) }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group mt-3">
                <label>Upload New Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror
                @if ($blooper->image)
                    <div class="mt-2">
                        <p class="mb-2">Current Image:</p>
                        <img src="{{ asset($blooper->image) }}" alt="{{ $blooper->name }}" width="120" class="img-thumbnail">
                    </div>
                @endif
                <small class="text-muted d-block mt-1">Leave empty to keep the current image.</small>
            </div>

            <div class="form-group mt-3">
                <label>Upload New Video</label>
                <input type="file" name="video" class="form-control" accept="video/*">
                @error('video') <span class="text-danger">{{ $message }}</span> @enderror
                @if ($blooper->video)
                    <div class="mt-2">
                        <p class="mb-2">Current Video:</p>
                        <video width="250" controls>
                            <source src="{{ asset($blooper->video) }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                @endif
                <small class="text-muted d-block mt-1">Leave empty to keep the current video.</small>
            </div>

            <div class="form-group mt-3">
                <label>Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $blooper->name) }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $blooper->description) }}</textarea>
                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Stars (0-5)</label>
                <input type="number" name="stars" class="form-control" min="0" max="5"
                       value="{{ old('stars', $blooper->stars ?? 0) }}">
                @error('stars') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-primary mt-3">Update</button>
            <a href="{{ route('admin.bloopers.index', $character) }}" class="btn btn-secondary mt-3">Back</a>
        </form>
        @endcan
    </div>
</div>
@endsection
