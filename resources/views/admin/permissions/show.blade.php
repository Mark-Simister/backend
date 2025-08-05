@extends('layouts.admin.master')

@section('title', 'View Permission')

@section('content')
<h2>Permission Details</h2>

<div class="card">
    <div class="card-body">
        <p><strong>ID:</strong> {{ $permission->id }}</p>
        <p><strong>Name:</strong> {{ $permission->name }}</p>
        <p><strong>Created At:</strong> {{ $permission->created_at->format('d M Y, H:i') }}</p>
        <p><strong>Updated At:</strong> {{ $permission->updated_at->format('d M Y, H:i') }}</p>
    </div>
</div>

<a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-warning mt-3">Edit</a>
<a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary mt-3">Back to List</a>
@endsection