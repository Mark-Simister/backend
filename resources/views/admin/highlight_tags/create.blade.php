@extends('layouts.admin.master')
@section('title', 'Add Highlight Tag')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Highlight Tag</h4>
        @can('highlight_tag.create')
        <form action="{{ route('admin.highlight_tags.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- <div class="form-group mt-3">
                <label for="emoji">Emoji <span class="text-danger">*</span></label>
                <input type="text" name="emoji" id="emoji" class="form-control" placeholder="e.g. 🔥" value="{{ old('emoji') }}" required>
                @error('emoji')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div> --}}
            <div class="form-group mt-3">
                <label for="emoji">Emoji Image</label>
                <input type="file" name="emoji" id="emoji" class="form-control" accept="image/*">
                @if(isset($highlightTag) && $highlightTag->emoji)
                    <div class="mt-2">
                        <img src="{{ asset($highlightTag->emoji) }}" 
                            alt="{{ $highlightTag->label }}" 
                            width="50" height="50" 
                            style="object-fit:contain;">
                    </div>
                @endif
                @error('emoji')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>


            <div class="form-group mt-3">
                <label for="label">Label <span class="text-danger">*</span></label>
                <input type="text" name="label" id="label" class="form-control" placeholder="e.g. Top Deal" value="{{ old('label') }}" required>
                @error('label')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            {{-- <div class="form-group mt-3">
                <label>
                    <input type="checkbox" name="automated" value="1" {{ old('automated') ? 'checked' : '' }}>
                    Automated Tag
                </label>
                @error('automated')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div> --}}

            <button type="submit" class="btn btn-success mt-3">Save Highlight Tag</button>
        </form>
        @endcan
    </div>
</div>
@endsection