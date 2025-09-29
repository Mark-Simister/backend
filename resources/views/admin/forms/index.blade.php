@extends('layouts.admin.master')

@section('title', 'Forms')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Forms</h2>
        @can('form.create')
            <a href="{{ route('admin.forms.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Form
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="auto-alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert" id="auto-alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif


    @if ($forms->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="forms-table" class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Video</th>
                                <th>Fields</th>
                                <th>CTA Type</th> <!-- New column -->
                                <th>Status</th>
                                <th style="width: 140px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($forms as $form)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $form->name }}</td>
                                    <td>
                                        @if ($form->video_path)
                                            <video width="200" controls>
                                                <source src="{{ asset($form->video_path) }}" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ count($form->fields) }} fields</td>
                                    <td>
                                        @if ($form->cta_type === 'apply_now')
                                            <span class="badge bg-primary">Apply Now</span>
                                        @elseif($form->cta_type === 'reachout')
                                            <span class="badge bg-success">Reachout</span>
                                        @else
                                            <span class="badge bg-secondary">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $form->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="text-center">
                                        @can('form.edit')
                                            <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn-sm btn-warning"
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        @can('form.delete')
                                            <form action="{{ route('admin.forms.destroy', $form) }}" method="POST"
                                                class="d-inline delete-form">
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
            <strong>No forms found.</strong> Start by adding a new form.
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#forms-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search forms..."
                }
            });
        });

        // SweetAlert Delete Confirmation
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
        setTimeout(function() {
                const alert = document.getElementById('auto-alert');
                if (alert) {
                    alert.classList.remove('show');
                    alert.classList.add('hide');
                }
            }, 5000);
        </script>
@endpush
