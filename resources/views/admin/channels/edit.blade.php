@extends('layouts.admin.master')
@section('title', 'Edit Channel')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Channel</h4>
        @can('channel.edit')
        <form action="{{ route('admin.channels.update', $channel) }}" method="POST">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Channel Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $channel->name) }}" class="form-control" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-primary mt-3">Update</button>
        </form>
        @endcan
    </div>
</div>
@endsection