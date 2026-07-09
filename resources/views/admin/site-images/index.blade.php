@extends('layouts.admin.master')
@section('title', 'Site Images')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4 class="mb-1">Site Images</h4>
            <p class="text-muted">Swap the hero / background images used across the public site — no code changes needed.</p>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                @foreach ($images as $slot)
                    <div class="col-md-6 mb-4">
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-3">{{ $slot->label }}</h5>

                            <div class="mb-3" style="background:#111;border-radius:8px;overflow:hidden;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;">
                                @if ($slot->image)
                                    <img src="{{ asset($slot->image) }}?v={{ $slot->updated_at?->timestamp }}" alt="{{ $slot->label }}" style="width:100%;height:100%;object-fit:cover;">
                                @else
                                    <span class="text-muted">No image set (using bundled default)</span>
                                @endif
                            </div>

                            <form action="{{ route('admin.site-images.update', $slot->key) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="input-group">
                                    <input type="file" name="image" class="form-control" accept="image/*" required>
                                    <button type="submit" class="btn btn-primary">Upload</button>
                                </div>
                                @error('image')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                                <small class="text-muted d-block mt-1">JPG / PNG / WEBP, up to 8&nbsp;MB. Wide (16:9) images work best.</small>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
