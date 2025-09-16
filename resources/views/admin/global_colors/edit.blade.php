@extends('layouts.admin.master')

@section('title', 'Edit Global Color')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Global Color</h4>
        @can('global-color.edit')
        <form action="{{ route('admin.global-colors.update', $global_color) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="name" 
                    class="form-control" 
                    value="{{ old('name', $global_color->name) }}" 
                    placeholder="e.g. Primary, Secondary, Accent" 
                    required
                >
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Hex Value -->
            <div class="form-group mt-3">
                <label>Hex Value <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="hex_value" 
                    class="form-control" 
                    value="{{ old('hex_value', $global_color->hex_value) }}" 
                    placeholder="#FF6B6B" 
                    required
                >
                @error('hex_value') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Usage -->
            <div class="form-group mt-3">
                <label>Usage (Optional)</label>
                <input 
                    type="text" 
                    name="usage" 
                    class="form-control" 
                    value="{{ old('usage', $global_color->usage) }}" 
                    placeholder="e.g. hero_bg, button, footer"
                >
                @error('usage') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Submit -->
            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection