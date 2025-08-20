@extends('layouts.admin.master')

@section('title', 'Edit Role')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Role</h4>
        <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')  <!-- This is necessary to send a PUT request for updating the resource -->

            <div class="form-group">
                <label>Role Name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" value="{{ old('name', $role->name) }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Guard Name is not required to be input, it's passed statically -->
            <input type="hidden" name="guard_name" value="web">

            <button class="btn btn-success mt-3">Update</button>
        </form>
    </div>
</div>
@endsection
