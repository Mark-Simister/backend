@extends('layouts.admin.master')

@section('title', 'Assign Role to ' . $user->name)

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white">
        <h4>Assign Role to <strong>{{ $user->name }}</strong></h4>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.sub_admins.update_role', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="roles" class="form-label">Assign Roles</label>
                <select name="roles[]" class="form-control" multiple required>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
                @error('roles') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success mt-3">Assign Role</button>
        </form>
    </div>
</div>
@endsection
