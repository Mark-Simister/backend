@extends('layouts.admin.master')

@section('title', 'Create Sub-admin')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Create Sub-admin</h4>
        <form action="{{ route('admin.sub_admins.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required>
                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
                @error('password') <span class="text-danger">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label>Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="role">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="" disabled selected>-- Select Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
                @error('role') <span class="text-danger">{{ $message }}</span> @enderror
            </div>


            <button class="btn btn-success mt-3">Save</button>
        </form>
    </div>
</div>
@endsection
