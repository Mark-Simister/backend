@extends('layouts.admin.master')

@push('styles')
    <style>
       /*  */
    </style>
@endpush

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Users</h2>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Add user</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($users->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table id="users-table" class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Is verified</th>
                            <th>Created At</th>
                            
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $index => $user)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->is_verified ? 'Yes' : 'No' }}</td>
                                <td>{{ $user->created_at }}</td>
                                
                                
                                <td>
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                        class="btn btn-warning me-1">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                        class="d-inline" onsubmit="return confirm('Delete this user?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    @else
        <div class="alert alert-info text-center mt-4">
            <strong>No users found.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    
    

    <script>
        $(document).ready(function() {
            $('#users-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                order: [
                    [0, 'desc']
                ], // Latest entries on top
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search users..."
                }
            });
        });
    </script>
@endpush
