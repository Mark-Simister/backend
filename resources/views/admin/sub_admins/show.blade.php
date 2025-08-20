@extends('layouts.admin.master')

@section('title', 'Sub-admin Details')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Sub-admin Details</h4>
        
        <div class="mb-3">
            <strong>Name:</strong> {{ $user->name }}
        </div>
        
        <div class="mb-3">
            <strong>Email:</strong> {{ $user->email }}
        </div>
        
        <div class="mb-3">
            <strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}
        </div>

        <div class="mb-3">
            <strong>Role:</strong> 
            @foreach($user->roles as $role)
                {{ $role->name }}
            @endforeach
        </div>

        <div class="mb-3">
            <strong>Verified:</strong> 
            {{ $user->is_verified ? 'Yes' : 'No' }}
        </div>

        <div class="mb-3">
            <strong>Created At:</strong> {{ $user->created_at->format('d-m-Y') }}
        </div>

        <div class="mb-3">
            <strong>Updated At:</strong> {{ $user->updated_at->format('d-m-Y') }}
        </div>

        <a href="{{ route('admin.sub_admins.index_sub_admin') }}" class="btn btn-secondary">Back to Sub-admin List</a>
    </div>
</div>
@endsection
