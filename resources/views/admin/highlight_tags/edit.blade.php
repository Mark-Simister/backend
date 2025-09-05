@extends('layouts.admin.master')
@section('title', 'Edit Highlight Tag')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Edit Highlight Tag</h4>
            @can('highlight_tag.view')
                <form action="{{ route('admin.highlight_tags.update', $highlightTag) }}" method="POST"  enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- <div class="form-group mt-3">
                <label for="emoji">Emoji <span class="text-danger">*</span></label>
                <input type="text" name="emoji" id="emoji" class="form-control"
                    value="{{ old('emoji', $highlightTag->emoji) }}" required>
                @error('emoji')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div> --}}
                    <div class="form-group mt-3">
                        <label for="emoji">Emoji Image</label>
                        <input type="file" name="emoji" id="emoji" class="form-control" accept="image/*">

                        @if ($highlightTag->emoji)
                            <div class="mt-2">
                                <img src="{{ asset($highlightTag->emoji) }}" alt="{{ $highlightTag->label }}" width="50"
                                    height="50" style="object-fit:contain;">
                            </div>
                        @endif

                        @error('emoji')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>


                    <div class="form-group mt-3">
                        <label for="label">Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="label" class="form-control"
                            value="{{ old('label', $highlightTag->label) }}" required>
                        @error('label')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- <div class="form-group mt-3">
                <label>
                    <input type="checkbox" name="automated" value="1" {{ old('automated', $highlightTag->automated) ? 'checked' : '' }}>
                    Automated Tag
                </label>
                @error('automated')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div> --}}

                    <button type="submit" class="btn btn-primary mt-3">Update Highlight Tag</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
