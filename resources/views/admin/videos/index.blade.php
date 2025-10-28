@extends('layouts.admin.master')

@section('title', 'Videos')

@push('styles')
    <style>
        /* optional custom styles */
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Videos</h2>
        @can('video.create')
            <a href="{{ route('admin.videos.create') }}" class="btn btn-primary">+ Add Video</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($videos->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="videos-table" class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Character</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Edit</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($videos as $index => $video)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $video->title }}</td>
                                    <td>{{ ucfirst($video->type) }}</td>
                                    <td>{{ $video->character->name ?? '-' }}</td>
                                    <td>{{ ucfirst($video->status) }}</td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" class="is-featured-toggle" data-id="{{ $video->id }}"
                                                {{ $video->is_featured ? 'checked' : '' }}>
                                            <span class="slider round"></span>
                                        </label>
                                    </td>


                                    <td class="btn-flex">
                                        @can('video.edit')
                                            <a href="{{ route('admin.videos.edit.seo', $video) }}"
                                                class="btn btn-outline-primary me-1">SEO</a>
                                            <a href="{{ route('admin.videos.edit.product', $video) }}"
                                                class="btn btn-outline-success me-1">Product</a>
                                            <a href="{{ route('admin.videos.character-insights.index', $video->id) }}"
                                                class="btn btn-outline-secondary me-1">Character Insights</a>
                                            <a href="{{ route('admin.similar-products.edit', $video->id) }}"
                                                class="btn btn-outline-warning me-1">Similar Products</a>
                                        @endcan
                                    </td>
                                    <td>
                                        @can('video.edit')
                                            <a href="{{ route('admin.videos.edit', $video) }}" class="btn btn-warning me-1">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <!-- Button to manage affiliate links -->
                                            <a href="{{ route('admin.videos.affiliate-links', $video->id) }}"
                                                class="btn btn-info me-1" style="pointer-events: none;">
                                                <i class="bi bi-link-45deg"></i> Manage Affiliate Links
                                            </a>
                                        @endcan
                                        @can('video.delete')
                                            <form action="{{ route('admin.videos.destroy', $video) }}" method="POST"
                                                class="d-inline delete-video-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-danger delete-btn">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                        @can('video.edit')
                                            <a href="{{ route('admin.videos.comments', $video->id) }}"
                                                class="btn btn-info show-comments-btn">
                                                <i class="bi bi-chat-left-dots"></i>
                                            </a>
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
            <strong>No videos found.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#videos-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search videos..."
                }
            });

            $('.is-featured-toggle').change(function() {
                var videoId = $(this).data('id'); // Get the video ID
                var newStatus = $(this).prop('checked') ? 1 : 0; // Get new status based on checked state

                // Send Ajax request to update the status
                $.ajax({
                    url: '{{ route('admin.videos.toggleFeatured') }}', // Define your route
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}', // CSRF token for security
                        id: videoId,
                        is_featured: newStatus
                    },
                    success: function(response) {
                        // On success, the status will already be toggled by the checkbox
                        if (response.success) {
                            // No need to update the text as the checkbox takes care of the toggle
                        } else {
                            alert('Error updating status!');
                        }
                    },
                    error: function() {
                        alert('Error communicating with the server.');
                        $(this).prop('checked', !$(this).prop('checked'));
                    }
                });
            });
        });

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
