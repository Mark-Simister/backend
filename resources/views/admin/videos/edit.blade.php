@extends('layouts.admin.master')
@section('title', 'Edit Video')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Video</h4>
        @can('video.edit')
        @include('admin.videos.form', ['video' => $video])
        @endcan
    </div>
</div>

{{-- Public SEO review page: explicit publish/withdraw + current state --}}
@can('video.edit')
    @if ($video->review_type === 'review')
        @include('admin.videos._seo-publish', ['video' => $video])
    @endif
@endcan
@endsection