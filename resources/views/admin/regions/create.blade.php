@extends('layouts.admin.master')
@section('title', 'Add Region')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Region</h4>
        @can('region.create')
        <form action="{{ route('admin.regions.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Region Name <span class="text-danger">*</span></label>
                <input type="text" name="region_name" class="form-control" value="{{ old('region_name') }}" required>
                @error('region_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Region Code <span class="text-danger">*</span></label>
                <input type="text" name="region_code" class="form-control" value="{{ old('region_code') }}" required>
                @error('region_code') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Currency <span class="text-danger">*</span></label>
                <input type="text" name="currency" class="form-control" value="{{ old('currency') }}" required>
                @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('is_active') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-success mt-3">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection