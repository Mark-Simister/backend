@extends('layouts.admin.master')

@section('title', 'Character Tags')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Character Tags</h2>
    <a href="{{ route('admin.character_tags.create') }}" class="btn btn-primary">+ Add Character Tag</a>
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
                    @foreach($characterTags as $index => $characterTag)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $characterTag->name }}</td>
                            <td>
                                <a href="{{ route('admin.character_tags.edit', $characterTag) }}" class="btn btn-warning me-1">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                                <form action="{{ route('admin.character_tags.destroy', $characterTag) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this character tag?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
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
    <div class="alert alert-info" role="alert">
        No character tags found.
    </div>
@endif
@endsection


{{-- @push('styles') --}}
    <!-- DataTables Bootstrap 5 CSS -->
    {{-- <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> --}}
{{-- @endpush --}}

@push('scripts')
    <!-- jQuery -->
    {{-- <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script> --}}

    <script>
        $(document).ready(function() {
            $('#character-tags-table').DataTable({
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
@endpush