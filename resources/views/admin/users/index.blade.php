@extends('layouts.admin.master')

@push('styles')
    <style>
        /* Custom styles (optional) */
        .status-badge {
            font-size: 0.85rem;
        }
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
                <div class="table-responsive">
                    <table id="users-table" class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Is verified</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Image</th>
                                <th style="width:240px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $index => $user)
                                <tr data-user-id="{{ $user->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->is_verified ? 'Yes' : 'No' }}</td>
                                    <td class="status-cell">
                                        <span
                                            class="badge status-badge {{ $user->is_blocked ? 'bg-danger' : 'bg-success' }}">
                                            {{ $user->is_blocked ? 'Blocked' : 'Active' }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at }}</td>
                                    <td>
                                        @if ($user->image)
                                            <img src="{{ asset($user->image) }}" alt="User Image" width="60"
                                                height="60" class="rounded-circle">
                                        @else
                                            <span>No Image</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button"
                                            class="btn {{ $user->is_blocked ? 'btn-success' : 'btn-outline-danger' }} btn-sm me-1 btn-toggle-block"
                                            data-id="{{ $user->id }}">
                                            {{ $user->is_blocked ? 'Unblock' : 'Block' }}
                                        </button>

                                        <a href="{{ route('admin.users.edit', $user) }}"
                                            class="btn btn-warning btn-sm me-1">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <a href="{{ route('admin.users.show', $user->id) }}"
                                            class="btn btn-sm btn-outline-primary me-1">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                            class="d-inline form-delete-user">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i>
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
            <strong>No users found.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            const token = $('meta[name="csrf-token"]').attr('content');

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // DataTable
            const lastCol = $('#users-table thead th').length - 1;
            $('#users-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                columnDefs: [{
                    orderable: false,
                    targets: [lastCol]
                }],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search users..."
                }
            });

            // Block/Unblock with SweetAlert
            $(document).on('click', '.btn-toggle-block', function() {
                const btn = $(this);
                const userId = btn.data('id');
                const actionText = btn.text().trim();

                Swal.fire({
                    title: `Are you sure you want to ${actionText.toLowerCase()} this user?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.prop('disabled', true);
                        $.post(`{{ url('admin/users') }}/${userId}/toggle-block`, {
                                _token: token
                            })
                            .done(function(res) {
                                if (res.success) {
                                    // Update button
                                    if (res.is_blocked) {
                                        btn.removeClass('btn-outline-danger').addClass(
                                            'btn-success').text('Unblock');
                                    } else {
                                        btn.removeClass('btn-success').addClass(
                                            'btn-outline-danger').text('Block');
                                    }
                                    // Update badge
                                    const badge = btn.closest('tr').find(
                                        '.status-cell .status-badge');
                                    if (res.is_blocked) {
                                        badge.removeClass('bg-success').addClass('bg-danger')
                                            .text('Blocked');
                                    } else {
                                        badge.removeClass('bg-danger').addClass('bg-success')
                                            .text('Active');
                                    }
                                } else {
                                    Swal.fire('Error', 'Failed to update user status.',
                                    'error');
                                }
                            })
                            .fail(function() {
                                Swal.fire('Error', 'Something went wrong.', 'error');
                            })
                            .always(function() {
                                btn.prop('disabled', false);
                            });
                    }
                });
            });

            // Delete user with SweetAlert
            $(document).on('submit', '.form-delete-user', function(e) {
                e.preventDefault();
                const form = this;
                Swal.fire({
                    title: 'Are you sure you want to delete this user?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
