@extends('layouts.admin.master')

@section('title', 'Edit Theme')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Edit Theme - {{ $theme->name }}</h2>
    <a href="{{ route('admin.themes.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Themes
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body user-color-management">
        <form action="{{ route('admin.themes.update', $theme->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <!-- Name -->
                <div class="col-md-12">
                    <label for="name" class="form-label">Theme Name <span class="text-danger">*</span></label>
                    <input 
                        type="text" 
                        class="form-control @error('name') is-invalid @enderror"
                        name="name"
                        id="name"
                        value="{{ old('name', $theme->name) }}"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Button Color -->
                <div class="col-md-3 btn-fx" >
                    <label for="button_color" class="form-label">Button Color</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="button_color"
                        name="button_color"
                        value="{{ old('button_color', $theme->button_color) }}"
                    >
                </div>

                <!-- Link Color -->
                <div class="col-md-3 btn-fx">
                    <label for="link_color" class="form-label">Link Color</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="link_color"
                        name="link_color"
                        value="{{ old('link_color', $theme->link_color) }}"
                    >
                </div>

                <!-- Dark BG -->
                <div class="col-md-3 btn-fx">
                    <label for="dark_bg_color" class="form-label">Dark Background</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="dark_bg_color"
                        name="dark_bg_color"
                        value="{{ old('dark_bg_color', $theme->dark_bg_color) }}"
                    >
                </div>

                <!-- Light BG -->
                <div class="col-md-3 btn-fx" >
                    <label for="light_bg_color" class="form-label">Light Background</label>
                    <input 
                        type="color"
                        class="form-control form-control-color"
                        id="light_bg_color"
                        name="light_bg_color"
                        value="{{ old('light_bg_color', $theme->light_bg_color) }}"
                    >
                </div>
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Update Theme
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
