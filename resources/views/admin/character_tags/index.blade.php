@extends('layouts.admin.master')

@section('title', 'Character Tags')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Character Tags</h2>
    @can('character_tag.create')
        <a href="{{ route('admin.character_tags.create') }}" class="btn btn-primary">+ Add Character Tag</a>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($characterTags->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="character-tags-table" class="table table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Name</th>
                    <th style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($characterTags as $characterTag)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $characterTag->name }}</td>
                    <td>
                        @can('character_tag.edit')
                        <a href="{{ route('admin.character_tags.edit', $characterTag) }}" class="btn btn-warning me-1">
                            <i class="bi bi-pencil-square"></i> Edit
                        </a>
                        @endcan
                        @can('character_tag.delete')
                        <form action="{{ route('admin.character_tags.destroy', $characterTag) }}" method="POST" class="d-inline delete-character-tag-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger delete-btn">
                                <i class="bi bi-trash"></i> Delete
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
@else
    <div class="alert alert-info" role="alert">
        No character tags found.
    </div>
@endif
@endsection

@push('scripts')
<!-- DataTables Script -->
<script>
$(document).ready(function() {
    $('#character-tags-table').DataTable({
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search character tags..."
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
