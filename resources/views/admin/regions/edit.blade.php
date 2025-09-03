@extends('layouts.admin.master')
@section('title', 'Edit Region')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Region</h4>
        @can('region.edit')
        <form action="{{ route('admin.regions.update', $region) }}" method="POST">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Region Name <span class="text-danger">*</span></label>
                <input name="region_name" class="form-control" 
                       value="{{ old('region_name', $region->region_name) }}" required>
                @error('region_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Region Code <span class="text-danger">*</span></label>
                <input name="region_code" class="form-control" 
                       value="{{ old('region_code', $region->region_code) }}" required>
                @error('region_code') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Description</label>
                <textarea name="description" class="form-control">{{ old('description', $region->description) }}</textarea>
                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Currency <span class="text-danger">*</span></label>
                <input name="currency" class="form-control" 
                       value="{{ old('currency', $region->currency) }}" required>
                @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" {{ old('is_active', $region->is_active) == 1 ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('is_active', $region->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('is_active') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection
