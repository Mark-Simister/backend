@extends('layouts.admin.master')
@section('title', 'Edit Channel')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Channel</h4>
        @can('channel.edit')
        <form action="{{ route('admin.channels.update', $channel) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-group mb-3">
                <label>Channel Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $channel->name) }}" class="form-control" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group region-flex">
                <label for="regions">Select Regions:</label><br>
                @foreach($regions as $region)
                    <div class="form-check form-check-inline">
                        <input type="checkbox" 
                            name="regions[]" 
                            value="{{ $region->id }}"
                            class="form-check-input"
                            {{ in_array($region->id, $selectedRegions ?? []) ? 'checked' : '' }}>
                        
                        <label class="form-check-label">
                            {{ $region->region_name }}
                            @if($region->is_active != 1)
                                <span class="badge bg-danger ms-1">Inactive</span>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="form-group mb-3">
                <label>Channel Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror

                @if($channel->image)
                    <div class="mt-2">
                        <p>Current Image:</p>
                        <img src="{{ asset($channel->image) }}" 
                            alt="Channel Image" 
                            width="120" 
                            class="img-thumbnail">
                    </div>
                @endif
            </div>

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection