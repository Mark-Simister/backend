@extends('layouts.admin.master')

@section('title', 'Create Permission')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Create New Permission</h4>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.permissions.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="name">Permission Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-success">Create</button>
                <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>
@endsection