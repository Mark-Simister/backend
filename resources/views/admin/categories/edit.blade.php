@extends('layouts.admin.master')
@section('title', 'Edit Category')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Category</h4>
        @can('category.edit')
        <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data">
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
            <div class="form-group">
                <label>Select Regions</label><br>
                @foreach($regions as $region)
                    <div class="form-check form-check-inline">
                        <input 
                            type="checkbox" 
                            name="regions[]" 
                            value="{{ $region->id }}" 
                            id="region_{{ $region->id }}"
                            class="form-check-input"
                            {{ in_array($region->id, $selectedRegions ?? []) ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="region_{{ $region->id }}">
                            {{ $region->region_name }}
                        </label>
                    </div>
                @endforeach
            </div>


            {{-- <div class="form-group mt-3">
                <label>Category Image</label>
                <input type="file" name="image" class="form-control">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror

                @if($category->image)
                    <div class="mt-2">
                        <p>Current Image:</p>
                        <img src="{{ asset('/' . $category->image) }}" 
                            alt="Category Image" 
                            width="120" 
                            class="img-thumbnail">
                    </div>
                @endif
            </div> --}}

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection