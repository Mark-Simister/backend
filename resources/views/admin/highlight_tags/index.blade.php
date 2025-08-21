@extends('layouts.admin.master')

@section('title', 'Highlight Tags')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Highlight Tags</h2>
    @can('highlight_tag.create')
    <a href="{{ route('admin.highlight_tags.create') }}" class="btn btn-primary">+ Add Highlight Tag</a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($tags->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="highlight-tags-table" class="table table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Emoji</th>
                    <th>Label</th>
                    {{-- <th>Automated</th> --}}
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tags as $highlightTag)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $highlightTag->emoji }}</td>
                            <td>{{ $highlightTag->label }}</td>
                            {{-- <td>{{ $highlightTag->automated ? 'Yes' : 'No' }}</td> --}}
                            <td>
                                @can('highlight_tag.edit')
                                <a href="{{ route('admin.highlight_tags.edit', $highlightTag) }}" class="btn btn-warning me-1">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                                @endcan
                                @can('highlight_tag.delete')
                                <form action="{{ route('admin.highlight_tags.destroy', $highlightTag) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this highlight tag?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
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
        No highlight tags found.
    </div>
@endif
@endsection

@push('scripts')
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
@endpush