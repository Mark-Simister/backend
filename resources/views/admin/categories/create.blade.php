@extends('layouts.admin.master')
@section('title', 'Add Category')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Category</h4>
        <form action="{{ route('admin.categories.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <button class="btn btn-success mt-3">Save</button>
        </form>
    </div>
</div>
@endsection
