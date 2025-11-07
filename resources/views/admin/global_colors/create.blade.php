@extends('layouts.admin.master')

@section('title', 'Add Global Color')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Global Color</h4>
        @can('global-color.create')
        <form action="{{ route('admin.global-colors.store') }}" method="POST">
            @csrf

            <!-- Name -->
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="name" 
                    class="form-control" 
                    value="{{ old('name') }}" 
                    placeholder="e.g. Primary, Secondary, Accent" 
                    required
                >
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Hex Value -->
            {{-- <div class="form-group mt-3">
                <label>Hex Value <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="hex_value" 
                    class="form-control" 
                    value="{{ old('hex_value') }}" 
                    placeholder="#FF6B6B" 
                    required
                >
                @error('hex_value') <span class="text-danger">{{ $message }}</span> @enderror
            </div> --}}
            <div class="form-group mt-3 d-flex align-items-center gap-2">
    <input 
        type="color" 
        id="colorPicker" 
        class="form-control form-control-color"
        value="{{ old('hex_value', '#FF6B6B') }}"
    >
    <input 
        type="text" 
        name="hex_value" 
        id="hexValue"
        class="form-control"
        value="{{ old('hex_value', '#FF6B6B') }}"
        placeholder="#FF6B6B" 
        required
    >
</div>


            <!-- Usage -->
            <div class="form-group mt-3">
                <label>Usage (Optional)</label>
                <input 
                    type="text" 
                    name="usage" 
                    class="form-control" 
                    value="{{ old('usage') }}" 
                    placeholder="e.g. hero_bg, button, footer"
                >
                @error('usage') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Submit -->
            <button class="btn btn-success mt-3">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection

@push('scripts')

<script>
    const colorPicker = document.getElementById('colorPicker');
    const hexValue = document.getElementById('hexValue');

    colorPicker.addEventListener('input', () => {
        hexValue.value = colorPicker.value;
    });

    hexValue.addEventListener('input', () => {
        if(/^#([0-9A-F]{3}){1,2}$/i.test(hexValue.value)) {
            colorPicker.value = hexValue.value;
        }
    });
</script>

@endpush