@extends('layouts.admin.master')
@section('title', 'Edit Character Insight')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Character Insight for Video #{{ $videoId }}</h4>
        @can('character_insights.edit')
        <form action="{{ route('admin.videos.character-insights.update', [$videoId, $characterInsight->id]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group mt-3">
                <label>Current Image</label><br>
                @if($characterInsight->character_insight_image)
                    <img src="{{ asset($characterInsight->character_insight_image) }}" alt="{{ $characterInsight->title }}" width="200" class="img-thumbnail mb-2">
                @else
                    <span class="text-muted">No image uploaded</span>
                @endif
            </div>

            <div class="form-group mt-3">
                <label>Upload New Image</label>
                <input type="file" name="character_insight_image" class="form-control" accept="image/*">
                @error('character_insight_image') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            
            <div class="form-group mt-3">
                <label>Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $characterInsight->title) }}" required>
                @error('title') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Short Description</label>
                <textarea name="short_description" class="form-control" rows="3">{{ old('short_description', $characterInsight->short_description) }}</textarea>
                @error('short_description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-success mt-3">Update</button>
            <a href="{{ route('admin.videos.character-insights.index', $videoId) }}" class="btn btn-secondary mt-3">Back</a>
        </form>
        @endcan
    </div>
</div>
@endsection
