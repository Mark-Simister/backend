@extends('layouts.admin.master')
@section('title', 'Add Region')
@push('styles')
    <!-- Include Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />    
@endpush

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
                <input name="region_code" class="form-control" 
                    value="{{ old('region_code') }}" required>
                @error('region_code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>


            {{-- <div class="form-group mt-3">
                <label>Region Code <span class="text-danger">*</span></label>
                <select name="region_code" class="form-control select2" required>
                    <option value="">Select Region Code</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->currency_code }}" 
                            {{ old('region_code') == $currency->currency_code ? 'selected' : '' }}>
                            {{ $currency->currency_name }} ({{ $currency->currency_code }})
                        </option>
                    @endforeach
                </select>
                @error('region_code') <span class="text-danger">{{ $message }}</span> @enderror
            </div> --}}

            <div class="form-group mt-3">
                <label>Currency <span class="text-danger">*</span></label>
                <select name="currency" class="form-control select2" required>
                    <option value="">Select Currency</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->currency_code }}" 
                            {{ old('currency') == $currency->currency_code ? 'selected' : '' }}>
                            {{ $currency->currency_name }} ({{ $currency->currency_code }})
                        </option>
                    @endforeach
                </select>
                @error('currency') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Currency Symbol <span class="text-danger">*</span></label>
                <select name="currency_symbol" class="form-control select2" required>
                    <option value="">Select Currency Symbol</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" 
                            {{ old('currency_symbol') == $currency->id ? 'selected' : '' }}>
                            {{ $currency->currency_symbol }} ({{ $currency->currency_name }})
                        </option>
                    @endforeach
                </select>
                @error('currency_symbol') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-3">
    <label>Motif Type</label>
    <select name="motif_type" id="motif_type" class="form-control">
        {{-- <option value="">Select Motif Type</option> --}}
        <option value="solid" {{ old('motif_type') == 'solid' ? 'selected' : '' }}>Solid</option>
        <option value="gradient" {{ old('motif_type') == 'gradient' ? 'selected' : '' }}>Gradient</option>
        <option value="pattern" {{ old('motif_type') == 'pattern' ? 'selected' : '' }}>Pattern</option>
    </select>
    @error('motif_type') <span class="text-danger">{{ $message }}</span> @enderror
</div>

{{-- Solid color --}}
<div class="form-group mt-3 motif-color-field" id="motif_color_single">
    <label>Motif Color</label>
    <input type="color" name="motif_color" class="form-control-color" 
       value="{{ old('motif_color', '#ffffff') }}" 
       style="height: 45px; width: 70px; padding: 0; border: 2px solid #ccc; border-radius: 6px; background-color: #f8f9fa; display: block;">


    @error('motif_color') <span class="text-danger">{{ $message }}</span> @enderror
</div>

{{-- Gradient or Pattern colors --}}
<div class="form-group mt-3 motif-color-field" id="motif_color_multiple" style="display:none;">
    <label>Motif Colors (for gradient or pattern)</label>
    <div class="d-flex gap-2">
        <input type="color" name="motif_color_1" class="form-control form-control-color" value="{{ old('motif_color_1', '#ffffff') }}">
        <input type="color" name="motif_color_2" class="form-control form-control-color" value="{{ old('motif_color_2', '#000000') }}">
        <input type="color" name="motif_color_3" class="form-control form-control-color" value="{{ old('motif_color_3', '#ff0000') }}">
    </div>
</div>

<div class="form-group mt-3">
    <label>Opacity</label>
    <input type="number" name="opacity" class="form-control" min="0" max="1" step="0.1" value="{{ old('opacity', 1) }}">
    @error('opacity') <span class="text-danger">{{ $message }}</span> @enderror
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

@push('scripts')


    <!-- Include jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2(); // Apply Select2 to all elements with the class 'select2'
        });
        document.getElementById('motif_type').addEventListener('change', function() {
    const type = this.value;
    document.getElementById('motif_color_single').style.display = type === 'solid' || type === '' ? 'block' : 'none';
    document.getElementById('motif_color_multiple').style.display = type === 'gradient' || type === 'pattern' ? 'block' : 'none';
});
    </script>
@endpush
