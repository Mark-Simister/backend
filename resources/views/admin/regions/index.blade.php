@extends('layouts.admin.master')

@section('title', 'Regions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    {{-- <h2 class="mb-0">Regions</h2>
    @can('region.create')
    <a href="{{ route('admin.regions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Region
    </a>
    @endcan --}}
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($regions->count())
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="regions-table" class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Currency</th>
                            <th>Status</th>
                            <th style="width: 160px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($regions as $region)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $region->region_name }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $region->region_code }}</span>
                                </td>
                                <td>{{ $region->currency }}</td>
                                <td>
                                    @if($region->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @can('region.edit')
                                    <a href="{{ route('admin.regions.edit', $region) }}" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    @endcan
                                    {{-- @can('region.delete')
                                    <form action="{{ route('admin.regions.destroy', $region) }}" 
                                          method="POST" 
                                          class="d-inline delete-region-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endcan --}}
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
        <strong>No regions found.</strong> Start by adding a new one.
    </div>
@endif
@endsection

@push('scripts')
<!-- DataTables Script -->
<script>
    $(document).ready(function() {
        $('#regions-table').DataTable({
            responsive: true,
            pageLength: 10,
            ordering: true,
            autoWidth: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search regions..."
            }
        });
    });
</script>

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
                text: "You won't be able to revert this!",
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
