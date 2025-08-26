@extends('layouts.admin.master')
@section('title', 'Edit Category')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Category</h4>
        @can('category.edit')
        <form action="{{ route('admin.categories.update', $category) }}" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Name</label>
                <input name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Channel <span class="text-danger">*</span></label>
                <select name="channel_id" class="form-control" required>
                    <option value="">-- Select Channel --</option>
                    @foreach($channels as $channel)
                        <option value="{{ $channel->id }}" 
                            {{ old('channel_id', $category->channel_id) == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select>
                @error('channel_id') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection