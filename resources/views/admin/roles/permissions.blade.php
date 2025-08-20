@extends('layouts.admin.master')

@section('title', 'Edit Permissions for ' . $role->name)
@push('style')
<style>
    .card {
        border-radius: 15px;
    }

    .card-header {
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        font-size: 1.2rem;
    }

    .form-check-label {
        font-size: 1rem;
    }

    .btn-lg {
        font-size: 1.1rem;
    }

    .form-check-input {
        width: 1.4rem;
        height: 1.4rem;
    }

    .card-body {
        background-color: #f8f9fa;
    }

    .card-shadow {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
</style>
@endpush
@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Permissions for <strong>{{ $role->name }}</strong></h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.roles.updatePermissions', $role) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-4">
                            <label for="permissions" class="form-label">Select Permissions</label>
                            <div class="row">
                                @foreach($permissions as $permission)
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="form-check-input" 
                                                @if(in_array($permission->id, $rolePermissions)) checked @endif>
                                            <label class="form-check-label">{{ $permission->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="text-center">
                            <button class="btn btn-success btn-lg px-4 py-2">Update Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
