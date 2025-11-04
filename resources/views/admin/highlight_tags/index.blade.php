@extends('layouts.admin.master')

@section('title', 'Highlight Tags')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Highlight Tags</h2>
        {{-- @can('highlight_tag.create')
            <a href="{{ route('admin.highlight_tags.create') }}" class="btn btn-primary">+ Add Highlight Tag</a>
        @endcan --}}
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($tags->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="highlight-tags-table" class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Emoji</th>
                                <th>Label</th>
                                <th style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tags as $highlightTag)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($highlightTag->emoji)
                                            <img src="{{ asset($highlightTag->emoji) }}" alt="{{ $highlightTag->label }}"
                                                width="40" height="40" style="object-fit:contain;">
                                        @else
                                            No Image
                                        @endif
                                    </td>
                                    <td>{{ $highlightTag->label }}</td>
                                    <td>
                                        @can('highlight_tag.edit')
                                            <a href="{{ route('admin.highlight_tags.edit', $highlightTag) }}"
                                                class="btn btn-warning me-1">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        {{-- @can('highlight_tag.delete')
                                            <form action="{{ route('admin.highlight_tags.destroy', $highlightTag) }}"
                                                method="POST" class="d-inline delete-highlight-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-danger delete-btn">
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
        <div class="alert alert-info" role="alert">
            No highlight tags found.
        </div>
    @endif
@endsection

@push('scripts')
    <!-- DataTables -->
    <script>
        $(document).ready(function() {
            $('#highlight-tags-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search highlight tags..."
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
