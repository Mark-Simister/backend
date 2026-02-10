@extends('layouts.admin.master')

@section('title', 'Add Theme')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Add Theme</h2>
    <a href="{{ route('admin.themes.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Themes
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form action="{{ route('admin.themes.store') }}" method="POST">
            @csrf

            <div class="row g-4">
                <!-- Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">Theme Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        class="form-control @error('name') is-invalid @enderror"
                        name="name"
                        id="name"
                        placeholder="Enter theme name"
                        value="{{ old('name') }}"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Button Color -->
                <div class="col-md-3">
                    <label for="button_color" class="form-label">Button Color</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="button_color"
                        name="button_color"
                        value="{{ old('button_color', '#D4B98A') }}"
                    >
                </div>

                <!-- Link Color -->
                <div class="col-md-3">
                    <label for="link_color" class="form-label">Link Color</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="link_color"
                        name="link_color"
                        value="{{ old('link_color', '#00bfff') }}"
                    >
                </div>

                <!-- Dark BG -->
                <div class="col-md-3">
                    <label for="dark_bg_color" class="form-label">Dark Background</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="dark_bg_color"
                        name="dark_bg_color"
                        value="{{ old('dark_bg_color', '#0D1321') }}"
                    >
                </div>

                <!-- Light BG -->
                <div class="col-md-3">
                    <label for="light_bg_color" class="form-label">Light Background</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="light_bg_color"
                        name="light_bg_color"
                        value="{{ old('light_bg_color', '#1b2b3a') }}"
                    >
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Theme
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
