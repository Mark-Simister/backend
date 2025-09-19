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
                        @foreach ($characters as $character)
                            <option value="{{ $character->id }}">{{ $character->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Dropdown to select a video based on character selection -->
                <div class="mb-3">
                    <label for="video" class="form-label">Select Video</label>
                    <select name="video_id" id="video" class="form-select" required>
                        <option value="" disabled selected>Select a video</option>
                        <!-- Videos will be populated based on character selection -->
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

@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // When the character is selected, fetch the related videos
            $('#character').change(function() {
                var character_id = $(this).val();

                if (character_id) {
                    $.ajax({
                        url: '/admin/videos/fetch/' + character_id, // Call the fetch route
                        type: 'GET',
                        success: function(data) {
                            // Clear the video dropdown
                            $('#video').empty();
                            $('#video').append(
                                '<option value="" disabled selected>Select a video</option>'
                                ); // Add a default option

                            // Populate the video dropdown with the fetched videos
                            $.each(data, function(index, video) {
                                $('#video').append('<option value="' + video.id + '">' +
                                    video.title + '</option>');
                            });
                        }
                    });
                }
            });
        });
    </script>
@endpush
