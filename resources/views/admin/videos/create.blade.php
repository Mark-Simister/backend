@extends('layouts.admin.master')
@section('title', 'Add Video')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add New Video</h4>
        @include('admin.videos.form', ['video' => null])
    </div>
</div>
@endsection