@extends('layouts.admin.master')
@section('title', 'Add Category')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Category</h4>
        @can('category.create')
        <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Channel <span class="text-danger">*</span></label>
                <select name="channel_id" class="form-control" required>
                    <option value="">-- Select Channel --</option>
                    @foreach($channels as $channel)
                        <option value="{{ $channel->id }}" {{ old('channel_id') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select>
                @error('channel_id') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group region-flex">
                <label>Select Regions</label><br>
                @foreach($regions as $region)
                        <div class="form-check form-check-inline">
                            <input 
                                type="checkbox" 
                            name="regions[]" 
                            value="{{ $region->id }}" 
                            id="region_{{ $region->id }}"
                            class="form-check-input"
                        >
                        <label class="form-check-label" for="region_{{ $region->id }}">
                            {{ $region->region_name }}
                        </label>
                    </div>
                @endforeach
            </div>


            <div class="form-group mt-3">
                <label>Category Image</label>
                <input type="file" name="image" class="form-control">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-success mt-3">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection