@extends('layouts.admin.master')

@section('title', 'Bloopers')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Bloopers for "{{ $character->name }}"</h2>
        @can('bloopers.manage')
            <a href="{{ route('admin.bloopers.create', $character) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Blooper
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($bloopers->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="bloopers-table" class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Video</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Stars</th>
                                <th style="width: 140px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloopers as $blooper)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <video width="250" controls>
                                            <source src="{{ asset($blooper->video) }}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </td>
                                    <td>
                                        @if ($blooper->image)
                                            <img src="{{ asset($blooper->image) }}" alt="{{ $blooper->name }}"
                                                width="200" class="img-thumbnail">
                                        @endif
                                    </td>
                                    <td>{{ $blooper->name ?? '—' }}</td>
                                    <td>{{ Str::limit($blooper->description, 50) ?? '—' }}</td>
                                    <td>
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= $blooper->stars)
                                                <i class="bi bi-star-fill text-warning"></i>
                                            @else
                                                <i class="bi bi-star text-secondary"></i>
                                            @endif
                                        @endfor
                                    </td>
                                    <td class="text-center">
                                        @can('bloopers.edit')
                                            <a href="{{ route('admin.bloopers.edit', ['character' => $character, 'blooper' => $blooper]) }}"
                                                class="btn btn-sm btn-warning" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        @can('bloopers.delete')
                                            <form action="{{ route('admin.bloopers.destroy', $blooper) }}" method="POST"
                                                class="d-inline delete-blooper-form">
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
            <strong>No bloopers found for this character.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    <!-- DataTables Script -->
    <script>
        $(document).ready(function() {
            $('#bloopers-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search bloopers..."
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
