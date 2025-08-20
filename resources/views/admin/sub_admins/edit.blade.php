@extends('layouts.admin.master')

@section('title', 'Edit Sub-admin')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Sub-admin</h4>
        <form action="{{ route('admin.sub_admins.update', $user) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                @error('email') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="role">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="" disabled selected>-- Select Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" 
                            {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                            {{ ucfirst($role->name) }}
                        </option>
                    @endforeach
                </select>
                @error('role') <span class="text-danger">{{ $message }}</span> @enderror
            </div>


             <!-- Password Field -->
            <div class="form-group">
                <label>Password <span class="text-muted">(Leave blank to keep current)</span></label>
                <input type="password" name="password" class="form-control">
                @error('password') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <!-- Confirm Password Field (only visible if password is entered) -->
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" >
                @error('password_confirmation') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button class="btn btn-success mt-3">Save Changes</button>
        </form>
    </div>
</div>
@endsection
