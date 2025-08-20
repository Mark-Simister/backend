@extends('layouts.admin.master')

@section('title', 'Create Role')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Create Role</h4>
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Role Name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>


            <button class="btn btn-success mt-3">Save</button>
        </form>
    </div>
</div>
@endsection
