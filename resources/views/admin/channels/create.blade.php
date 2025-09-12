@extends('layouts.admin.master')
@section('title', 'Create Channel')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Create Channel</h4>
        @can('channel.create')
        <form action="{{ route('admin.channels.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="form-group mb-3">
                <label>Channel Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group region-flex chcrt">
                <label>Select Regions</label>
                @foreach($regions as $region)
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="regions[]" value="{{ $region->id }}" id="region_{{ $region->id }}">
                        <label class="form-check-label" for="region_{{ $region->id }}">{{ $region->region_name }}</label>
                    </div>
                @endforeach
            </div>
            <div class="form-group mb-3">
                <label>Channel Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                @error('image') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success mt-3">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection