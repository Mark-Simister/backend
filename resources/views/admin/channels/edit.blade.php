@extends('layouts.admin.master')
@section('title', 'Edit Channel')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Channel</h4>
        @can('channel.edit')
        <form action="{{ route('admin.channels.update', $channel) }}" method="POST">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $channel->name) }}" class="form-control" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Category</label>
                <select name="category_id" class="form-control" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $channel->category_id == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection