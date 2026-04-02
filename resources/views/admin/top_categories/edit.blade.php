@extends('layouts.admin.master')
@section('title', 'Edit Top Category')
@section('content')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Edit Top Category</h2>
    <a href="{{ route('admin.top-categories.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.top-categories.update', $topCategory->id) }}">
            @csrf
            @method('PUT')

            {{-- Category Type --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Category Type <span class="text-danger">*</span></label>
                <select name="category_type" id="category_type" class="form-select" required>
                    <option value="">Select</option>
                    <option value="pet" {{ old('category_type', $topCategory->category_type) == 'pet' ? 'selected' : '' }}>Pet</option>
                    <option value="people" {{ old('category_type', $topCategory->category_type) == 'people' ? 'selected' : '' }}>People</option>
                </select>
                @error('category_type')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            {{-- Multi Comment Type --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Select Comment Type(s)</label>
                <select id="comment_type_filter" class="form-select" multiple>
                    <option value="pet">Pet</option>
                    <option value="people">People</option>
                    <option value="global">Global</option>
                </select>
                <small class="text-muted">You can select one or more comment types — comments will auto-load below.</small>
            </div>

            {{-- Title --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $topCategory->title) }}" required>
                @error('title')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            {{-- Videos & Comments --}}
            <div class="mb-3 row">
                {{-- Videos --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Top 5 Videos</label>
                    <div id="video-list" class="row g-2 top-videos"></div>
                    @error('videos')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                {{-- Auto Comments Preview --}}
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Auto Fetched Comments</label>
                    <div id="comment-preview" class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                        <p class="text-muted mb-0">Select comment types to view comments here...</p>
                    </div>
                </div>
            </div>

            {{-- Hidden field for comment IDs --}}
            <input type="hidden" name="comment_types" id="selected_comment_types" value="{{ json_encode($topCategory->comment_types ?? []) }}" />

            {{-- Explain Video Upload --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Explain Video (Upload)</label>
                
                <input type="file" name="explain_video" id="explain_video"
                    class="form-control" accept="video/*">
                
                @if($topCategory->explain_video)
                    <p class="mt-2">
                        Current:
                        <a href="{{ asset($topCategory->explain_video) }}" target="_blank" class="text-decoration-none">
                            <i class="bi bi-play-circle text-success"></i> View Uploaded Video
                        </a>
                    </p>
                @endif

                <div class="mt-2 small text-muted">
                    <span>Video Duration:</span>
                    <strong id="videoDurationText">
                        @if($topCategory->explain_video)
                            Loading...
                        @else
                            Not loaded
                        @endif
                    </strong>
                </div>

                @error('explain_video')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>

            <input type="hidden" id="video_duration" value="{{ $topCategory->video_duration ?? 0 }}">

            {{-- Timestamp Repeater --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-semibold mb-0">
                        Video Timestamps
                    </label>
                    <button type="button"
                        id="addTimestamp"
                        class="btn btn-sm btn-primary"
                        {{ $topCategory->explain_video ? '' : 'disabled' }}>
                        + Add Timestamp
                    </button>
                </div>

                <div class="alert alert-light border small mb-2">
                    Add timestamps within the video duration.
                </div>

                <div id="timestampRepeater"></div>
            </div>

            {{-- Status --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="1" {{ old('status', $topCategory->status) == 1 ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('status', $topCategory->status) == 0 ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    let allVideos = @json($videos);
    let allComments = @json($comments);
    let savedTimestamps = @json($topCategory->video_timestamps ?? []);
    let selectedVideos = @json($topCategory->video_ids ?? []);
    let selectedTypes = @json($topCategory->comment_types ?? []);
    let selectedCategory = "{{ $topCategory->category_type }}";

    const $videoList = $('#video-list');
    const $commentTypeFilter = $('#comment_type_filter');
    const $commentPreview = $('#comment-preview');
    const $categorySelect = $('#category_type');
    const $selectedCommentIds = $('#selected_comment_ids');
    const $selectedCommentTypes = $('#selected_comment_types');

    // Initialize Select2
    $commentTypeFilter.select2({
        placeholder: "Select comment types...",
        allowClear: true,
        width: '100%'
    });

    // Preselect saved comment types
    $commentTypeFilter.val(selectedTypes).trigger('change');

    // Render Videos
    function renderVideos(cat) {
        $videoList.empty();

        if (!cat) {
            $videoList.append(`<p class="text-muted">Select a category to see videos.</p>`);
            return;
        }

        // Get old selected videos if form failed validation
        const oldSelected = @json(old('videos', []));
        const selectedIds = oldSelected.length ? oldSelected.map(id => String(id)) : (selectedVideos || []).map(id => String(id));

        const filtered = allVideos.filter(v => v.video_cat === cat);
        if (!filtered.length) {
            $videoList.append(`<p class="text-danger">No videos found for "${cat}".</p>`);
            return;
        }

        filtered.forEach(v => {
            const isChecked = selectedIds.includes(String(v.id));
            const thumbnail = v.thumbnail_url;

            $videoList.append(`
                <div class="col-md-6">
                    <label class="video-card ${isChecked ? 'active' : ''}" for="video_${v.id}">
                        
                        <input 
                            type="checkbox" 
                            name="videos[]" 
                            id="video_${v.id}" 
                            value="${v.id}" 
                            ${isChecked ? 'checked' : ''}
                            hidden
                        >

                        <div class="d-flex align-items-center gap-2">
                            
                            <div class="thumb-wrapper">
                                <img src="${thumbnail}" 
                                    alt="${v.title}" 
                                    class="video-thumb">

                                <span class="tick-icon">
                                    <i class="bi bi-check"></i>
                                </span>
                            </div>

                            <div class="video-info">
                                <div class="video-title">${v.title}</div>
                            </div>

                        </div>
                    </label>
                </div>
            `);
        });
    }

    // Render comments based on selected types
    function renderComments(selectedTypes) {
        $commentPreview.empty();

        if (!selectedTypes.length) {
            $commentPreview.html(`<p class="text-muted mb-0">Select comment types to view comments here...</p>`);
            return;
        }

        const filtered = allComments.filter(c => selectedTypes.includes(c.type));
        if (!filtered.length) {
            $commentPreview.html(`<p class="text-danger mb-0">No comments found for selected type(s).</p>`);
            return;
        }

        filtered.forEach(c => {
            const userName = c.user ? c.user.name : 'Anonymous';
            $commentPreview.append(`
                <!--
                <div class="border-bottom py-1">
                    <strong>[${c.type.toUpperCase()}]</strong> ${c.comment} 
                    <small class="text-muted">(${userName})</small>
                </div>
                 -->

                <div class="border-bottom py-1">
                    ${c.comment} 
                    <small class="text-muted">(${userName})</small>
                </div>
            `);
        });
    }

    // Event: category change
    $categorySelect.on('change', function () {
        renderVideos($(this).val());
    });

    // Event: comment type multi-select change
    $commentTypeFilter.on('change', function () {
        const selectedTypes = $(this).val() || [];
        $('#selected_comment_types').val(JSON.stringify(selectedTypes)); 
        renderComments(selectedTypes);
    });

    // Initial render
    renderVideos(selectedCategory);
    renderComments(selectedTypes);

    // Timestamp script start
    let videoDuration = {{ $topCategory->video_duration ?? 0 }};
    let timestampIndex = 0;

    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return minutes.toString().padStart(2, '0') + ":" + secs.toString().padStart(2, '0');
    }

    function timeToSeconds(time) {
        const parts = time.split(':').map(Number);
        const hrs = parts[0] || 0;
        const mins = parts[1] || 0;
        const secs = parts[2] || 0;
        return (hrs * 3600) + (mins * 60) + secs;
    }

    function durationToSeconds(time) {
        const parts = time.split(':').map(Number);
        if (parts.length !== 2) return 0;
        const minutes = parts[0];
        const seconds = parts[1];
        return (minutes * 60) + seconds;
    }

    // Load saved timestamps
    if (savedTimestamps && savedTimestamps.length) {
        savedTimestamps.forEach(ts => {
            const index = timestampIndex++;
            const row = `
            <div class="row g-2 align-items-center mb-2 timestamp-row border rounded p-2">
                <div class="col-md-2">
                    <label class="small text-muted">Start</label>
                    <input type="text"
                        name="timestamps[${index}][start]"
                        class="form-control start-time time-input"
                        placeholder="00:00"
                        maxlength="5"
                        value="${ts.start}"
                        required>
                </div>
                <div class="col-md-2">
                    <label class="small text-muted">End</label>
                    <input type="text"
                        name="timestamps[${index}][end]"
                        class="form-control end-time time-input"
                        placeholder="00:00"
                        maxlength="5"
                        value="${ts.end}"
                        required>
                </div>
                <div class="col-md-7">
                    <label class="small text-muted">Message</label>
                    <input type="text"
                        name="timestamps[${index}][message]"
                        class="form-control"
                        placeholder="Enter message"
                        value="${ts.message}"
                        required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm removeRow">✕</button>
                </div>
            </div>`;
            $('#timestampRepeater').append(row);
        });
    }

    // If there's an existing video, try to get its duration
    @if($topCategory->explain_video)
        // Create a video element to get duration of existing video
        const videoUrl = "{{ asset('storage/'.$topCategory->explain_video) }}";
        const tempVideo = document.createElement('video');
        tempVideo.preload = 'metadata';
        tempVideo.onloadedmetadata = function() {
            window.URL.revokeObjectURL(tempVideo.src);
            videoDuration = Math.floor(tempVideo.duration);
            $('#video_duration').val(videoDuration);
            $('#videoDurationText').text(formatTime(videoDuration));
            $('#addTimestamp').prop('disabled', false);
        };
        tempVideo.src = videoUrl;
    @endif

    $('#explain_video').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const video = document.createElement('video');
        video.preload = 'metadata';

        video.onloadedmetadata = function() {
            window.URL.revokeObjectURL(video.src);
            videoDuration = Math.floor(video.duration);
            $('#video_duration').val(videoDuration);
            $('#videoDurationText').text(formatTime(videoDuration));
            $('#addTimestamp').prop('disabled', false);
        };

        video.src = URL.createObjectURL(file);
    });

    $('#addTimestamp').click(function() {
        if (!videoDuration) {
            Swal.fire("Please upload a video first");
            return;
        }

        // Get last row
        const lastRow = $('.timestamp-row').last();
        let autoStart = '';

        if (lastRow.length) {
            const lastEnd = lastRow.find('.end-time').val();
            if (lastEnd) {
                const endSec = durationToSeconds(lastEnd);
                if (endSec >= videoDuration) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Video Finished',
                        text: 'Timestamp exceeds video duration'
                    });
                    return;
                }
                // auto fill start
                autoStart = lastEnd;
            }
        }

        const index = timestampIndex++;

        const row = `
        <div class="row g-2 align-items-center mb-2 timestamp-row border rounded p-2">
            <div class="col-md-2">
                <label class="small text-muted">Start</label>
                <input type="text"
                    name="timestamps[${index}][start]"
                    class="form-control start-time time-input"
                    placeholder="00:00"
                    maxlength="5"
                    value="${autoStart}"
                    required>
            </div>
            <div class="col-md-2">
                <label class="small text-muted">End</label>
                <input type="text"
                    name="timestamps[${index}][end]"
                    class="form-control end-time time-input"
                    placeholder="00:00"
                    maxlength="5"
                    required>
            </div>
            <div class="col-md-7">
                <label class="small text-muted">Message</label>
                <input type="text"
                    name="timestamps[${index}][message]"
                    class="form-control"
                    placeholder="Enter message"
                    required>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm removeRow">✕</button>
            </div>
        </div>`;

        $('#timestampRepeater').append(row);
    });

    $(document).on('click', '.removeRow', function() {
        $(this).closest('.timestamp-row').remove();
    });

    $(document).on('change', '.start-time, .end-time', function() {
        const row = $(this).closest('.timestamp-row');
        const start = row.find('.start-time').val();
        const end = row.find('.end-time').val();

        if (!start || !end) return;

        const startSec = durationToSeconds(start);
        const endSec = durationToSeconds(end);

        if (startSec > videoDuration || endSec > videoDuration) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Timestamp',
                text: 'Timestamp exceeds video duration'
            });
            $(this).val('');
            return;
        }

        if (endSec <= startSec) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Range',
                text: 'End must be greater than start'
            });
            row.find('.end-time').val('');
        }
    });

    $(document).on('input', '.time-input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 3) {
            value = value.substring(0, 2) + ':' + value.substring(2, 4);
        }
        $(this).val(value);
    });

    $(document).on('blur', '.time-input', function() {
        let val = $(this).val();
        if (!val) return;

        const parts = val.split(':');
        if (parts.length !== 2) {
            Swal.fire("Invalid time format. Use mm:ss");
            $(this).val('');
            return;
        }

        let minutes = parseInt(parts[0], 10);
        let seconds = parseInt(parts[1], 10);

        if (isNaN(minutes) || isNaN(seconds) || seconds > 59) {
            Swal.fire("Invalid time format. Seconds must be between 00 and 59.");
            $(this).val('');
            return;
        }

        // Normalize format (example: 1:5 -> 01:05)
        const formatted =
            String(minutes).padStart(2, '0') + ":" +
            String(seconds).padStart(2, '0');

        $(this).val(formatted);
    });

    $(document).on('change', 'input[name="videos[]"]', function () {

        const checkedCount = $('input[name="videos[]"]:checked').length;
        if (checkedCount > 5) {
            this.checked = false;
            alert('You can select only 5 videos');
            return;
        }

        if (checkedCount === 0) {
            this.checked = true;
            alert('At least 1 video must be selected');
            return;
        }

        const label = $(this).closest('.video-card');
        label.toggleClass('active', this.checked);
    });

    $('form').on('submit', function (e) {
        const checkedCount = $('input[name="videos[]"]:checked').length;

        if (checkedCount === 0) {
            e.preventDefault();
            alert('Please select at least 1 video');
            return false;
        }
    });
});
</script>
@endpush