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
    <div class="d-flex align-items-center gap-2">
        <!-- Color Picker -->
        <input 
            type="color" 
            id="colorPicker" 
            class="form-control form-control-color" 
            value="{{ old('hex_value', $global_color->hex_value ?? '#FF6B6B') }}"
        >

        <!-- Hex Input -->
        <input 
            type="text" 
            name="hex_value" 
            id="hexValue"
            class="form-control"
            value="{{ old('hex_value', $global_color->hex_value ?? '#FF6B6B') }}"
            placeholder="#FF6B6B" 
            required
        >
    </div>

    @error('hex_value') 
        <span class="text-danger">{{ $message }}</span> 
    @enderror
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

@push('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const colorPicker = document.getElementById('colorPicker');
        const hexValue = document.getElementById('hexValue');

        // Sync color picker → text input
        colorPicker.addEventListener('input', () => {
            hexValue.value = colorPicker.value;
        });

        // Sync text input → color picker (only valid hex)
        hexValue.addEventListener('input', () => {
            if (/^#([0-9A-F]{3}){1,2}$/i.test(hexValue.value)) {
                colorPicker.value = hexValue.value;
            }
        });
    });
</script>

@endpush