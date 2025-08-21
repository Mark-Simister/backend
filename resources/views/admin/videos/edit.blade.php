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
@endsection