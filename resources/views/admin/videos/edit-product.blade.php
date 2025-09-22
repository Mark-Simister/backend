@extends('layouts.admin.master')

@section('title', 'Edit Video – Product Fields')

@push('styles')
<style>
 /*  */
</style>
@endpush

@section('content')
<div class="product-hero mb-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
  <div class="d-flex align-items-center gap-3">
    <span class="badge bg-primary-subtle text-primary border border-primary">Product</span>
    <div>
      <h2 class="mb-1 h4 h3-lg">{{ $video->title }}</h2>
      <div class="text-muted small">
        Channel: {{ $video->channel->name ?? '—' }} • Character: {{ $video->character->name ?? '—' }} • Access: {{ ucfirst($video->access_level ?? '—') }}
      </div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('admin.videos.edit', $video) }}"><i class="bi bi-sliders"></i> Full Edit</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.videos.edit.seo', $video) }}"><i class="bi bi-megaphone"></i> SEO Fields</a>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i> Please fix the following:
    <ul class="mb-0 mt-1">
      @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<form method="POST" action="{{ route('admin.videos.update.product', $video) }}" enctype="multipart/form-data">
@csrf
@method('PUT')

<div class="row g-4">
  {{-- LEFT --}}
  <div class="col-12 col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="form-section-title">Basic Info</div>
        <div class="row g-3">
          <div class="col-12 col-md-8">
            <label class="form-label">Product Name</label>
            <input type="text" name="product_name" class="form-control form-control-lg"
                   placeholder="e.g., HyperWidget 3000"
                   value="{{ old('product_name', $video->product_name) }}">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">ASIN / SKU</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
              <input type="text" name="product_asin_sku" class="form-control"
                     placeholder="B0XXXX / SKU-123"
                     value="{{ old('product_asin_sku', $video->product_asin_sku) }}">
            </div>
          </div>
        </div>

        <div class="divider"></div>

        <div class="form-section-title">Scores & Ratings</div>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <label class="form-label">Public Rating (0–5)</label>
            <input type="number" step="0.1" min="0" max="5" name="public_rating" class="form-control"
                   value="{{ old('public_rating', $video->public_rating) }}">
            <div class="form-text">Shown publicly</div>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Character Score (0–10)</label>
            <input type="number" step="0.1" min="0" max="10" name="character_score" class="form-control"
                   value="{{ old('character_score', $video->character_score) }}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Editorial Score (0–10)</label>
            <input type="number" step="0.1" min="0" max="10" name="editorial_score" class="form-control"
                   value="{{ old('editorial_score', $video->editorial_score) }}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Final Beastie Score (0–10)</label>
            <input type="number" step="0.1" min="0" max="10" name="final_beastie_score" class="form-control"
                   value="{{ old('final_beastie_score', $video->final_beastie_score) }}">
          </div>
        </div>
        

        <div class="divider"></div>

        <div class="form-section-title">Review Details</div>
        <div class="mb-3">
          <textarea name="review_details" class="form-control" rows="6"
                    placeholder="Write a crisp, buyer-focused review...">{{ old('review_details', $video->review_details) }}</textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <a href="{{ route('admin.videos.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save"></i> Save Product
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- RIGHT --}}
  <div class="col-12 col-lg-4">
    <div class="sticky-side">
      <div class="card card-soft mb-3">
        <div class="card-header bg-white border-0 pb-0">
          <div class="form-section-title mb-0">Product Thumbnail</div>
        </div>
        <div class="card-body">
          @php
            use Illuminate\Support\Str;
            $thumb = $video->product_thumbnail ?? null;
            $thumbUrl = $thumb
              ? (Str::startsWith($thumb, ['http://','https://']) ? $thumb : asset($thumb))
              : null;
          @endphp

          <div class="thumb-frame mb-3">
            <div class="ratio ratio-16x9">
              <img id="thumbPreview"
                   src="{{ $thumbUrl ?? '' }}"
                   class="thumb-img {{ $thumbUrl ? '' : 'd-none' }}"
                   alt="Product thumbnail preview">
            </div>
            @if(!$thumbUrl)
              <div class="text-muted small mt-2">No image selected</div>
            @endif
          </div>

          <div class="input-group">
            <label class="input-group-text" for="thumbInput"><i class="bi bi-image"></i></label>
            <input class="form-control" type="file" id="thumbInput" name="product_thumbnail" accept="image/*">
          </div>
          <div class="form-text">Accepted: JPG, JPEG, PNG, WEBP. Max 5MB.</div>

          @error('product_thumbnail')
            <div class="text-danger mt-2">{{ $message }}</div>
          @enderror

          <div class="divider"></div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-cloud-upload"></i> Save Changes
            </button>
            <a href="{{ route('admin.videos.index') }}" class="btn btn-outline-secondary">Back to List</a>
          </div>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <div class="form-section-title">At a Glance</div>
          <ul class="list-unstyled small mb-0">
            <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i> Channel: <strong>{{ $video->channel->name ?? '—' }}</strong></li>
            <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i> Character: <strong>{{ $video->character->name ?? '—' }}</strong></li>
            <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i> Category: <strong>{{ $video->category->name ?? '—' }}</strong></li>
            <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i> Access: <strong>{{ ucfirst($video->access_level ?? '—') }}</strong></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('thumbInput');
  const preview = document.getElementById('thumbPreview');
  if (!input) return;

  input.addEventListener('change', function (e) {
    const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (ev) {
      if (preview) {
        preview.src = ev.target.result;
        preview.classList.remove('d-none');
      }
    };
    reader.readAsDataURL(file);
  });
});
</script>
@endpush
