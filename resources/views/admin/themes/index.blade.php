@extends('layouts.admin.master')

@section('title', 'Themes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Themes</h2>

    {{-- Add Theme Button --}}
    <a href="{{ route('admin.themes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Theme
    </a>
</div>

{{-- Success Message --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Themes Table --}}
@if($themes->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table id="themes-table" class="table align-middle table-striped">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Button Color</th>
                        <th>Link Color</th>
                        <th>Dark BG</th>
                        <th>Light BG</th>
                        <th style="width: 160px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($themes as $theme)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $theme->name }}</td>

                        <td>
                            <div class="indx-col">
                            <span class="badge border" style="background: {{ $theme->button_color }};">&nbsp;&nbsp;&nbsp;</span>
                            <small class="text-muted ms-1">{{ $theme->button_color }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="indx-col">
                            <span class="badge border" style="background: {{ $theme->link_color }};">&nbsp;&nbsp;&nbsp;</span>
                            <small class="text-muted ms-1">{{ $theme->link_color }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="indx-col">
                            <span class="badge border" style="background: {{ $theme->dark_bg_color }};">&nbsp;&nbsp;&nbsp;</span>
                            <small class="text-muted ms-1">{{ $theme->dark_bg_color }}</small>
                            </div>
                        </td>
                        <td>
                            <div class="indx-col">
                            <span class="badge border" style="background: {{ $theme->light_bg_color }};">&nbsp;&nbsp;&nbsp;</span>
                            <small class="text-muted ms-1">{{ $theme->light_bg_color }}</small>
                            </div>
                        </td>

                        <td class="text-center">
                            <a href="{{ route('admin.themes.edit', $theme->id) }}" class="btn btn-sm btn-warning me-1">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>

                            <form action="{{ route('admin.themes.destroy', $theme->id) }}" method="POST" class="d-inline-block delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger delete-btn">
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
</div>
@else
<div class="alert alert-info text-center mt-4">
    <strong>No Themes found.</strong> Start by adding a new one.
</div>
@endif
@endsection

@push('scripts')
<!-- DataTables Initialization -->
<script>
$(document).ready(function() {
    $('#themes-table').DataTable({
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search themes..."
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
                text: "This will permanently delete the theme!",
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
