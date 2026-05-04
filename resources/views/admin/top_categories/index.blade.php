@extends('layouts.admin.master')
@section('title', 'Top Categories')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Top Categories</h2>

    {{-- Add New Button --}}
    <a href="{{ route('admin.top-categories.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add New
    </a>
</div>

{{-- Success Message --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Top Categories Table --}}
@if($topCategories->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table id="top-categories-table" class="table align-middle table-striped">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Category Type</th>
                        <th>Title</th>
                        <th>Cover</th>
                        <th>Videos Count</th>
                        <th>Explain Video</th>
                        <th>Status</th>
                        <th style="width: 160px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topCategories as $cat)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ ucfirst($cat->category_type) }}</td>
                        <td>{{ $cat->title }}</td>
                        <td>
                            @if($cat->thumbnail)
                                <img src="{{ asset($cat->thumbnail) }}" 
                                    alt="thumbnail" 
                                    style="width:80px; height:50px; object-fit:cover; border-radius:6px;">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ is_array($cat->video_ids) ? count($cat->video_ids) : 0 }}</td>
                        <td>
                            @if($cat->explain_video)
                                <a href="{{ asset($cat->explain_video) }}" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-play-circle text-success"></i> View
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($cat->status)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.top-categories.edit', $cat->id) }}" class="btn btn-sm btn-warning me-1">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <form action="{{ route('admin.top-categories.destroy', $cat->id) }}" method="POST" class="d-inline-block delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger delete-btn">
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
    <strong>No Top Categories found.</strong> Start by adding a new one.
</div>
@endif

@endsection

@push('scripts')
<!-- DataTables Initialization -->
<script>
$(document).ready(function() {
    $('#top-categories-table').DataTable({
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search categories..."
        }
    });
});
</script>

<!-- SweetAlert Delete Confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this category!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
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