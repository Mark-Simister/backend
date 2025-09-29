@extends('layouts.admin.master')

@section('title', 'Character Insights')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Character Insights for Video #{{ $video->title }}</h2>
        @can('character_insights.manage')
            <a href="{{ route('admin.videos.character-insights.create', $videoId) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Insight
            </a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($insights->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="insights-table" class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th style="width: 140px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($insights as $insight)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($insight->character_insight_image)
                                            <img src="{{ asset($insight->character_insight_image) }}"
                                                 alt="{{ $insight->title }}"
                                                 width="150" class="img-thumbnail">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $insight->title ?? '—' }}</td>
                                    <td>{{ Str::limit($insight->short_description, 80) ?? '—' }}</td>
                                    <td class="text-center">
                                        @can('character_insights.edit')
                                            <a href="{{ route('admin.videos.character-insights.edit', [$videoId, $insight->id]) }}"
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        @can('character_insights.delete')
                                            <form action="{{ route('admin.videos.character-insights.destroy', [$videoId, $insight->id]) }}"
                                                  method="POST"
                                                  class="d-inline delete-insight-form">
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
            <strong>No character insights found for this video.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    <!-- DataTables Script -->
    <script>
        $(document).ready(function() {
            $('#insights-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search insights..."
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
