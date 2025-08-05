@extends('layouts.admin.master')
@section('title', 'Edit Video')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Video</h4>
        @include('admin.videos.form', ['video' => $video])
    </div>
</div>
@endsection