@extends('layouts.admin.master')
@section('title', 'Edit Character Role')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Character Role</h4>
        @can('character_role.edit')
        <form action="{{ route('admin.character_roles.update', $characterRole) }}" method="POST">
            @csrf
            @method('PUT') 
            <div class="form-group mt-3">
                <label for="name">Role Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $characterRole->name) }}" required>
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary mt-3">Update Role</button>
        </form>
        @endcan
    </div>
</div>
@endsection
