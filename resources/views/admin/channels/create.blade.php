@extends('layouts.admin.master')
@section('title', 'Create Channel')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Create Channel</h4>
        @can('channel.create')
        <form action="{{ route('admin.channels.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Channel Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success mt-4">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection