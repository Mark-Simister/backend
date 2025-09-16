@extends('layouts.admin.master')

@section('title', 'Global Colors')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Global Colors</h2>
    @can('global-color.create')
    <a href="{{ route('admin.global-colors.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Global Color
    </a>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($colors->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table id="global-colors-table" class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Hex Value</th>
                        <th>Usage</th>
                        <th style="width: 140px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($colors as $color)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $color->name }}</td>
                        <td>
                            <span class="badge" style="background-color: {{ $color->hex_value }}; color: #000;">
                                {{ $color->hex_value }}
                            </span>
                        </td>
                        <td>{{ $color->usage ?? '—' }}</td>
                        <td class="text-center">
                            @can('global-color.edit')
                            <a href="{{ route('admin.global-colors.edit', $color) }}" 
                               class="btn btn-sm btn-warning" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('global-color.delete')
                            <form action="{{ route('admin.global-colors.destroy', $color) }}" 
                                  method="POST" 
                                  class="d-inline delete-color-form">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endcan
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
    <strong>No global colors found.</strong> Start by adding a new one.
</div>
@endif
@endsection

@push('scripts')
<!-- DataTables Script -->
<script>
$(document).ready(function() {
    $('#global-colors-table').DataTable({
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search colors..."
        }
    });
});
</script>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SweetAlert Delete Confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-btn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the color permanently!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush