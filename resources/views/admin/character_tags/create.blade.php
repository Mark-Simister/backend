@extends('layouts.admin.master')
@section('title', 'Add Character Tag')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Character Tag</h4>
        @can('character_tag.create')
        <form action="{{ route('admin.character_tags.store') }}" method="POST">
            @csrf
            <div class="form-group mt-3">
                <label for="name">Tag Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-success mt-3">Save Tag</button>
        </form>
        @endcan
    </div>
</div>
@endsection
