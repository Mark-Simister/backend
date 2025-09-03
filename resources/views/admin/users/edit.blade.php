@extends('layouts.admin.master')

@section('title', 'Edit User')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Edit User</h4>

            <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required >
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required readonly>
                    @error('email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                    @error('phone')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control">
                    @error('password')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control">
                </div>

                <div class="form-group mt-3">
                    <label>Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        @foreach ($roles as $key => $role)
                            <option value="{{ $key }}" {{ old('role', $user->getRoleNames()->first()) == $key ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>


                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
