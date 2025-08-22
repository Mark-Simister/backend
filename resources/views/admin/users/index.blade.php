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
      <th style="width:60px;">#</th>
      <th>Name</th>
      <th>Email</th>
      <th>Is verified</th>
      <th>Status</th> 
      <th>Created At</th>
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
        <span class="badge status-badge {{ $user->is_blocked ? 'bg-danger' : 'bg-success' }}">
          {{ $user->is_blocked ? 'Blocked' : 'Active' }}
        </span>
      </td>
      <td>{{ $user->created_at }}</td>
      <td>
        <button type="button"
          class="btn {{ $user->is_blocked ? 'btn-success' : 'btn-outline-danger' }} btn-sm me-1 btn-toggle-block"
          data-id="{{ $user->id }}">
          {{ $user->is_blocked ? 'Unblock' : 'Block' }}
        </button>

        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning btn-sm me-1">
          <i class="bi bi-pencil-square"></i> Edit
        </a>

        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-sm btn-outline-primary me-1">
          View Details
        </a>

        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline"
          onsubmit="return confirm('Delete this user?')">
          @csrf
          @method('DELETE')
          <button class="btn btn-danger btn-sm">
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
$(function () {
  const token = $('meta[name="csrf-token"]').attr('content');

  // Ensure Laravel sees this as an AJAX request and gets the CSRF header
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  // DataTable – lock last column (Actions) as non-orderable
  const lastCol = $('#users-table thead th').length - 1;
  $('#users-table').DataTable({
    responsive: true,
    pageLength: 10,
    ordering: true,
    //order: [[0, 'desc']],
    autoWidth: false,
    columnDefs: [{ orderable: false, targets: [lastCol] }],
    language: { search: "_INPUT_", searchPlaceholder: "Search users..." }
  });

  // Delegated handler survives redraws
  $(document).on('click', '.btn-toggle-block', function () {
    const btn = $(this);
    const userId = btn.data('id');

    btn.prop('disabled', true);

    $.ajax({
      url: `{{ url('admin/users') }}/${userId}/toggle-block`,
      method: 'POST',
      data: { _token: token } // send token in body too
    })
    .done(function (res) {
      if (!res || !res.success) {
        alert('Failed to update user status.');
        return;
      }

      // Button
      if (res.is_blocked) {
        btn.removeClass('btn-outline-danger').addClass('btn-success').text('Unblock');
      } else {
        btn.removeClass('btn-success').addClass('btn-outline-danger').text('Block');
      }

      // Badge
      const badge = btn.closest('tr').find('.status-cell .status-badge');
      if (res.is_blocked) {
        badge.removeClass('bg-success').addClass('bg-danger').text('Blocked');
      } else {
        badge.removeClass('bg-danger').addClass('bg-success').text('Active');
      }
    })
    .fail(function (xhr) {
      if (xhr.status === 419) {
        alert('Session expired. Please refresh the page and try again.');
      } else {
        alert('Something went wrong.');
      }
    })
    .always(function () {
      btn.prop('disabled', false);
    });
  });
});
</script>
@endpush
