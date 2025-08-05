@extends('layouts.admin.master')

@section('title', 'Add User')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Add User</h4>
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    @error('email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    @error('phone')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                    @error('password')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <div class="form-group mt-3">
    <label>Role <span class="text-danger">*</span></label>
    <select name="role" class="form-control" required>
        <option value="">Select Role</option>
        @foreach ($roles as $key => $role)
            <option value="{{ $key }}" {{ old('role') == $key ? 'selected' : '' }}>
                {{ ucfirst(str_replace('_', ' ', $role)) }}
            </option>
        @endforeach
    </select>
    @error('role')
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>


                {{-- Hidden field (optional if handled in controller) --}}
                <input type="hidden" name="is_verified" value="1">

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Save</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
