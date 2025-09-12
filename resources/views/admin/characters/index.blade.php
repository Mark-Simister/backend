@extends('layouts.admin.master')

@push('styles')
    <style>
        /* Custom styles if needed */
    </style>
@endpush

@section('title', 'Characters')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Characters</h2>
        @can('character.create')
            <a href="{{ route('admin.characters.create') }}" class="btn btn-primary">+ Add Character</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($characters->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="characters-table" class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Persona</th>
                                <th>Image</th>
                                <th style="width: 20px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($characters as $character)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $character->name }}</td>
                                    <td>{{ Str::limit($character->persona, 50) }}</td>
                                    <td>
                                        <img src="{{ asset($character->image) }}" alt="{{ $character->name }}"
                                            style="max-width: 200px; height: auto;">
                                    </td>
                                    <td>
                                        @can('character.edit')
                                            <a href="{{ route('admin.characters.edit', $character) }}"
                                                class="btn btn-warning me-1">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        @can('character.delete')
                                            <form action="{{ route('admin.characters.destroy', $character) }}" method="POST"
                                                class="d-inline delete-character-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-danger delete-btn">
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
            <strong>No characters found.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    <!-- DataTables -->
    <script>
        $(document).ready(function() {
            $('#characters-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search characters..."
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
