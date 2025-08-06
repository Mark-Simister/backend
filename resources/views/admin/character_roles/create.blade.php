@extends('layouts.admin.master')
@section('title', 'Add Character Role')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Character Role</h4>
        <form action="{{ route('admin.character_roles.store') }}" method="POST">
            @csrf
            <div class="form-group mt-3">
                <label for="name">Role Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-success mt-3">Save Role</button>
        </form>
    </div>
</div>
@endsection
