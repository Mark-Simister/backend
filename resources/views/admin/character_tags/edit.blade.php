@extends('layouts.admin.master')
@section('title', 'Edit Character Tag')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Character Tag</h4>
        {{-- --}}
        <form action="{{ route('admin.character_tags.update', $characterTag) }}" method="POST">
            @csrf
            @method('PUT') {{-- Use @method('PUT') or @method('PATCH') for update operations --}}
            <div class="form-group mt-3">
                <label for="name">Tag Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $characterTag->name) }}" required> {{-- --}}
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary mt-3">Update Tag</button>
        </form>
    </div>
</div>
@endsection
