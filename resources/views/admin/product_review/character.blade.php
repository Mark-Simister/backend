@extends('layouts.admin.master')

@section('title', 'Product Review to Character')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Product Review for Category: {{ $category->name }} Assign to character</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('admin.product_review.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Dropdown to select a character -->
                <div class="mb-3">
                    <label for="character" class="form-label">Select Character</label>
                    <select name="character_id" id="character" class="form-select" required>
                        <option value="" disabled selected>Select a character</option>
                        @forelse ($characters as $character)
                            <option value="{{ $character->id }}">{{ $character->name }}</option>
                        @empty
                            <option value="" disabled>No character available</option>
                        @endforelse
                    </select>
                </div>

                <!-- Dropdown to select a video based on character selection -->
                <div class="mb-3">
                    <label for="video" class="form-label">Select Video</label>
                    <select name="video_id" id="video" class="form-select" required>
                        <option value="" disabled selected>Select a video</option>
                    </select>
                </div>

                <!-- Option to select the type of product review -->
                <div class="mb-3">
                    <label for="review_type" class="form-label">Select Review Type</label>
                    <select name="review_type" id="review_type" class="form-select" required>
                        <option value="" disabled selected>Select a review type</option>
                        <option value="full_video">Full-length Vimeo Video</option>
                        <option value="mp3">MP3 File</option>
                        <option value="short_video">Short Vimeo Video</option>
                    </select>
                </div>

                <!-- File Upload Section -->
                <div id="fileUploadSection" class="mb-3">
                    <label for="file" class="form-label">Upload File</label>
                    <input type="file" name="file" id="file" class="form-control"
                        accept="video/mp4,video/mov,audio/mp3" required />
                    <small class="form-text text-muted">Upload either an MP3 or video file depending on the review
                        type.</small>
                </div>

                <!-- Submit Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reviews List --}}
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Reviews for “{{ $category->name }}”</h5>
        </div>
        <div class="card-body">
            <div id="featuredToast" class="alert d-none mt-3" role="alert"></div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Character</th>
                            <th>Video</th>
                            <th>Featured</th> 
                            <th>Type</th>
                            <th>Review URL</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reviews as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td>{{ $r->character->name ?? '-' }}</td>
                                <td>{{ $r->video->title ?? '#' . ($r->video->id ?? '-') }}</td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input js-toggle-featured" type="checkbox"
                                                role="switch" data-id="{{ $r->id }}"
                                                {{ (int) ($r->is_featured ?? 0) === 1 ? 'checked' : '' }}>
                                        </div>
                                        <span
                                            class="ms-2 badge js-featured-badge-{{ $r->id }} {{ (int) ($r->is_featured ?? 0) === 1 ? 'bg-success' : 'bg-secondary' }}">
                                            {{ (int) ($r->is_featured ?? 0) === 1 ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="text-capitalize">{{ $r->type ?? '-' }}</td>
                                <td>
                                    @if (!empty($r->review_url))
                                        <a href="{{ $r->review_url }}" target="_blank" rel="noopener noreferrer">
                                            {{ $r->review_url }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No reviews found for this category.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#character').change(function() {
                var character_id = $(this).val();

                if (character_id) {
                    $.ajax({
                        url: '/admin/videos/fetch/' + character_id,
                        type: 'GET',
                        success: function(data) {
                            $('#video').empty();
                            $('#video').append(
                                '<option value="" disabled selected>Select a video</option>'
                                );
                            if (data.length > 0) {
                                $.each(data, function(index, video) {
                                    $('#video').append('<option value="' + video.id +
                                        '">' + video.title + '</option>');
                                });
                            } else {
                                $('#video').append(
                                    '<option value="" disabled>No video available</option>');
                            }
                        },
                        error: function() {
                            $('#video').empty();
                            $('#video').append(
                                '<option value="" disabled>No video available</option>');
                        }
                    });
                } else {
                    $('#video').empty();
                    $('#video').append('<option value="" disabled>No video available</option>');
                }
            });

            // CSRF for AJAX
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Toggle Featured
            $('.js-toggle-featured').on('change', function() {
                const $checkbox = $(this);
                const id = $checkbox.data('id');
                const isFeatured = $checkbox.is(':checked') ? 1 : 0;

                $checkbox.prop('disabled', true);

                $.ajax({
                    url: '{{ url('/admin/product-reviews') }}/' + id + '/featured',
                    type: 'PATCH',
                    data: {
                        is_featured: isFeatured
                    },
                    success: function(res) {
                        // Update badge UI
                        const badge = $('.js-featured-badge-' + id);
                        if (res.is_featured) {
                            badge.removeClass('bg-secondary').addClass('bg-success').text(
                            'Yes');
                        } else {
                            badge.removeClass('bg-success').addClass('bg-secondary').text('No');
                        }

                        showToast(res.message || 'Updated successfully.', 'success');
                    },
                    error: function(xhr) {
                        $checkbox.prop('checked', !isFeatured);
                        showToast('Failed to update. Please try again.', 'danger');
                    },
                    complete: function() {
                        $checkbox.prop('disabled', false);
                    }
                });
            });

            function showToast(message, type) {
                const $t = $('#featuredToast');
                $t.removeClass('d-none alert-success alert-danger alert-warning alert-info')
                    .addClass('alert-' + type)
                    .text(message);
                setTimeout(() => {
                    $t.addClass('d-none');
                }, 2000);
            }
        });
    </script>
@endpush
