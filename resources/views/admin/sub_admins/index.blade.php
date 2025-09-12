@extends('layouts.admin.master')

@section('title', 'Users')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Sub Admin Users</h2>
        <a href="{{ route('admin.sub_admins.create') }}" class="btn btn-primary">+ Add Sub-admin</a>
        {{-- <a href="{{ route('admin.sub_admins.assign_roles') }}" class="btn btn-secondary">+ Add Sub-admin Role</a> --}}
    </div>
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    @if ($users->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if ($user->getRoleNames()->join(', ') == 'sub_admin')
                                            Sub Admin
                                        @else
                                            {{ $user->getRoleNames()->join(', ') }}
                                        @endif
                                    </td>

                                    <td>
                                        <a href="{{ route('admin.sub_admins.edit', $user) }}" class="btn btn-warning me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="{{ route('admin.sub_admins.assign_role', $user) }}"
                                            class="btn btn-info me-1">
                                            <i class="bi bi-person-plus"></i> Assign Role
                                        </a>
                                        <form action="{{ route('admin.sub_admins.destroy', $user) }}" method="POST"
                                            class="d-inline">
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
        </div>
    @else
        <div class="alert alert-info text-center mt-4">
            <strong>No users found.</strong>
        </div>
    @endif
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            $('table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                order: [
                    [0, 'desc']
                ], // Latest entries on top
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search sub-admin users..."
                }
            });
        });
    </script>
@endpush
