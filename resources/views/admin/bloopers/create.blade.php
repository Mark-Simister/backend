@extends('layouts.admin.master')
@section('title', 'Add Blooper')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Blooper for "{{ $character->name }}"</h4>
        @can('bloopers.manage')
        <form action="{{ route('admin.bloopers.store', $character) }}" method="POST" enctype="multipart/form-data">
            @csrf

            
            <div class="form-group mt-3">
                <label>Upload Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            
            <div class="form-group mt-3">
                <label>Upload Video <span class="text-danger">*</span></label>
                <input type="file" name="video" class="form-control" required accept="video/*">
                @error('video') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Stars (0-5)</label>
                <input type="number" name="stars" class="form-control" min="0" max="5" value="{{ old('stars', 0) }}">
                @error('stars') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-success mt-3">Save</button>
            <a href="{{ route('admin.bloopers.index', $character) }}" class="btn btn-secondary mt-3">Back</a>
        </form>
        @endcan
    </div>
</div>

@endsection
