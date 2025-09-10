<style>
    .form-step {
        display: none;
    }

    .form-step.active {
        display: block;
    }

    .step-buttons {
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
    }

    .form-container {
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        max-width: 800px;
        margin: auto;
    }

    .form-group label {
        font-weight: bold;
    }

    .is-invalid {
        border: 1px solid red;
    }

    /* Progress bar */
    .progress {
        height: 25px;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .progress-bar {
        background-color: #0d6efd;
        line-height: 25px;
        color: #fff;
        font-weight: 600;
        text-align: center;
    }

    /* Step indicators */
    .step-indicators {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .step-indicator {
        flex: 1;
        text-align: center;
        padding: 6px;
        border-bottom: 3px solid #dee2e6;
        color: #6c757d;
        font-weight: 500;
        cursor: pointer;
    }

    .step-indicator.active {
        border-bottom: 3px solid #0d6efd;
        color: #0d6efd;
        font-weight: 600;
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />


<div class="">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-3 ">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <!-- Progress Bar -->
    <div class="progress">
        <div id="formProgress" class="progress-bar" role="progressbar" style="width: 25%;">
            Step <span id="currentStep">1</span> of 4
        </div>
    </div>

    <!-- Step Indicators -->
    <div class="step-indicators">
        <div id="indicator-0" class="step-indicator active">Step 1</div>
        <div id="indicator-1" class="step-indicator">Step 2</div>
        <div id="indicator-2" class="step-indicator">Step 3</div>
        <div id="indicator-3" class="step-indicator">Step 4</div>
    </div>

    <form action="{{ isset($video) ? route('admin.videos.update', $video) : route('admin.videos.store') }}"
        method="POST" enctype="multipart/form-data" id="stepForm">
        @csrf
        @if (isset($video))
            @method('PUT')
        @endif

        <!-- Step 1 -->
        <div class="form-step active">
            <div class="mb-3">
                <label for="title">Video Title <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" class="form-control" required
                    value="{{ old('title', $video->title ?? '') }}">
            </div>

            <div class="mb-3">
                <label for="description">Video Description <span class="text-danger">*</span></label>
                <textarea name="description" id="description" class="form-control" required>{{ old('description', $video->description ?? '') }}</textarea>
            </div>
            @php
                $defaultType = old('type', request('type', $video->type ?? ''));
            @endphp

            <div class="mb-3">
                <label for="type">Video Platform Type <span class="text-danger">*</span></label>
                {{-- <select name="type" id="type" class="form-select" required>
                    <option value="" disabled selected>-- Select Platform --</option>
                    <option value="youtube" {{ old('type', $video->type ?? '') == 'youtube' ? 'selected' : '' }}>YouTube
                    </option>
                    <option value="vimeo" {{ old('type', $video->type ?? '') == 'vimeo' ? 'selected' : '' }}>Vimeo
                    </option>
                </select> --}}
                <select name="type" id="type" class="form-select" required>
                    <option value="" disabled {{ $defaultType ? '' : 'selected' }}>-- Select Platform --</option>
                    <option value="youtube" {{ $defaultType === 'youtube' ? 'selected' : '' }}>YouTube</option>
                    <option value="vimeo" {{ $defaultType === 'vimeo' ? 'selected' : '' }}>Vimeo</option>
                </select>

            </div>

            <div class="mb-3">
                <label for="video_url">Video URL <span class="text-danger">*</span></label>
                {{-- <input type="url" name="video_url" id="video_url" class="form-control" required
                    value="{{ old('video_url', $video->video_url ?? '') }}"> --}}
                <input type="url" name="video_url" id="video_url" class="form-control" required
                    value="{{ old('video_url', request('video_url', $video->video_url ?? '')) }}">

            </div>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary next-step">Next</button>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="form-step">
            <div class="mb-3">
                <label for="character_id">Character</label>
                <select name="character_id" id="character_id" class="form-select" required>
                    <option value="" disabled selected>-- Select Character --</option>
                    @foreach ($characters as $character)
                        <option value="{{ $character->id }}"
                            {{ old('character_id', $video->character_id ?? '') == $character->id ? 'selected' : '' }}>
                            {{ $character->name }}
                        </option>
                    @endforeach
                </select>
                <div id="character_error" class="invalid-feedback" style="display:none;">Please select a character.
                </div>
            </div>
            <div class="form-group">
                <label for="regions">Select Regions:</label><br>
                @foreach ($regions as $region)
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="regions[]" value="{{ $region->id }}" class="form-check-input"
                            {{ in_array($region->id, $selectedRegions ?? []) ? 'checked' : '' }}>
                        <label class="form-check-label">
                            {{ $region->region_name }}
                            @if (!$region->is_active)
                                <small class="text-danger">(Inactive)</small>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="mb-3">
                <label for="channel_id">Channel</label>
                <select name="channel_id" id="channel_id" class="form-select" required>
                    <option value="" disabled selected>-- Select Channel --</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}"
                            {{ old('channel_id', $video->channel_id ?? '') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select>
                <div id="channel_error" class="invalid-feedback" style="display:none;">Please select a channel.</div>
            </div>

            <div class="mb-3">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" class="form-select" required>
                    <option value="" disabled selected>-- Select Category --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                            {{ old('category_id', $video->category_id ?? '') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <div id="category_error" class="invalid-feedback" style="display:none;">Please select a category.</div>
            </div>

            <div class="mb-3">
                <label for="access_level">Access Level</label>
                <select name="access_level" id="access_level" class="form-select" required>
                    <option value="public"
                        {{ old('access_level', $video->access_level ?? '') == 'public' ? 'selected' : '' }}>Public
                    </option>
                    <option value="premium"
                        {{ old('access_level', $video->access_level ?? '') == 'premium' ? 'selected' : '' }}>Premium
                    </option>
                    <option value="early_access"
                        {{ old('access_level', $video->access_level ?? '') == 'early_access' ? 'selected' : '' }}>Early
                        Access</option>
                </select>
                <div id="access_level_error" class="invalid-feedback" style="display:none;">Please select an access
                    level.</div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="button" class="btn btn-primary next-step" id="nextStepBtn">Next</button>
            </div>
        </div>




        <!-- Step 3 -->
        <div class="form-step">
            <div class="mb-3">
                <label for="affiliate_link">Affiliate Link</label>
                <input type="url" name="affiliate_link" id="affiliate_link" class="form-control"
                    placeholder="https://example.com/affiliate" data-alwaysOptional="true"
                    value="{{ old('affiliate_link', $video->affiliate_link ?? '') }}">
            </div>

            @php
                $thumbSource = old('thumbnail_option', !empty($video->thumbnail_image) ? 'image' : 'url');
            @endphp

            <!-- Thumbnail Image Input (Visible if 'image' option is selected) -->
            {{-- <div class="mb-3" id="thumb_image_input" style="{{ $thumbSource == 'image' ? '' : 'display:none;' }}"> --}}
            <div class="mb-3" id="thumb_image_input">
                <label for="thumbnail_image" class="form-label">Thumbnail Image</label>
                <input type="file" name="thumbnail_image" id="thumbnail_image"
                    class="form-control @error('thumbnail_image') is-invalid @enderror" accept="image/*"
                    {{ !empty($video->thumbnail_image) ? '' : 'required' }}>

                @if (!empty($video->thumbnail_image))
                    <div class="mt-2">
                        <strong>Existing Thumbnail Image:</strong>
                        <img src="{{ asset($video->thumbnail_image) }}" alt="Thumbnail" class="img-thumbnail"
                            style="max-width: 200px;">
                    </div>
                @endif

                @error('thumbnail_image')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="button" class="btn btn-primary next-step">Next</button>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="form-step">

            @php
                function cleanTags($value)
                {
                    if (empty($value)) {
                        return '';
                    }

                    // If already array
                    if (is_array($value)) {
                        return implode(', ', $value);
                    }

                    // If JSON encoded array (["tag1","tag2"])
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return implode(', ', $decoded);
                    }

                    // If JSON encoded string ("tag1, tag2, tag3")
                    $decodedString = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_string($decodedString)) {
                        return $decodedString;
                    }

                    // Fallback → remove extra quotes/slashes
                    return trim($value, "\"'[]");
                }
            @endphp

            {{-- <div class="mb-3">
                <label for="tags">Tags (comma separated)</label>
                <input type="text" name="tags" id="tags" class="form-control"
                    value="{{ old('tags', isset($video->tags) ? cleanTags($video->tags) : '') }}">
            </div> --}}
            <div class="mb-3">
                <label for="tags_select">Tags</label>
                <select id="tags_select" class="form-select" multiple></select>
                <input type="hidden" name="tag_ids" id="tag_ids"
                    value="{{ old('tag_ids', $video->tag_ids ?? '') }}">
                <button type="button" id="createTagBtn" class="btn btn-sm btn-outline-primary mt-2">Create new
                    tag</button>
            </div>

            <div class="mb-3">
                <label for="rating_type">Rating Type</label>
                <select name="rating_type" id="rating_type" class="form-select">
                    <option value="" disabled selected>-- Select Rating Type --</option>
                    <option value="rating"
                        {{ old('rating_type', $video->rating_type ?? '') == 'rating' ? 'selected' : '' }}>Rating
                    </option>
                    <option value="review"
                        {{ old('rating_type', $video->rating_type ?? '') == 'review' ? 'selected' : '' }}>Review
                    </option>
                </select>
            </div>

            <!-- Rating Input Fields (will be displayed when "rating" is selected) -->
            <div id="rating_fields" class="form-group"
                style="{{ old('rating_type', $video->rating_type ?? '') === 'rating' ? '' : 'display:none;' }}">
                <div class="mb-3">
                    <label for="public_rating">Public Rating</label>
                    <input type="number" name="public_rating" id="public_rating" class="form-control"
                        placeholder="Enter public rating (1-5)" min="1" max="5" step="0.1"
                        value="{{ old('public_rating', $video->public_rating ?? '') }}">
                </div>
            </div>

            <!-- Review Input Fields (will be displayed when "review" is selected) -->
            <div id="review_fields" class="form-group"
                style="{{ old('rating_type', $video->rating_type ?? '') === 'review' ? '' : 'display:none;' }}">
                <div class="mb-3">
                    <label for="review_details">Review Details</label>
                    <textarea name="review_details" id="review_details" class="form-control" placeholder="Enter review details">{{ old('review_details', $video->review_details ?? '') }}</textarea>
                </div>
            </div>


            <div class="mb-3">
                <label for="sponsorship_type">Sponsorship Type</label>
                <select name="sponsorship_type" id="sponsorship_type" class="form-select">
                    <option value="" disabled selected>-- Select Sponsorship Type --</option>
                    <option value="sponsored"
                        {{ old('sponsorship_type', $video->sponsorship_type ?? '') == 'sponsored' ? 'selected' : '' }}>
                        Sponsored</option>
                    <option value="unsponsored"
                        {{ old('sponsorship_type', $video->sponsorship_type ?? '') == 'unsponsored' ? 'selected' : '' }}>
                        Unsponsored</option>
                </select>
            </div>

            @php
                $hlSelected = old('highlight_tags', $videoHighlightTags ?? []);

                if (is_string($hlSelected)) {
                    $maybeJson = json_decode($hlSelected, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $hlSelected = is_array($maybeJson) ? $maybeJson : [$maybeJson];
                    } else {
                        $hlSelected = array_filter(array_map('trim', explode(',', $hlSelected)));
                    }
                }
            @endphp

            <div class="mb-3">
                <label for="highlight_tags">Highlight Tags</label>
                <select name="highlight_tags[]" id="highlight_tags" class="form-select" multiple>
                    <option value="" disabled selected>-- Select Highlight Tags --</option>
                    @foreach ($highlight_tags as $highlight_tag)
                        <option value="{{ $highlight_tag->id }}"
                            {{ in_array((string) $highlight_tag->id, array_map('strval', $hlSelected)) ? 'selected' : '' }}>
                            {{ $highlight_tag->label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="video_type">Video Type</label>
                <select name="video_type" id="video_type" class="form-select">
                    <option value="" disabled selected>-- Select Video Type --</option>
                    <option value="short"
                        {{ old('video_type', $video->video_type ?? '') == 'short' ? 'selected' : '' }}>Short</option>
                    <option value="full_review"
                        {{ old('video_type', $video->video_type ?? '') == 'full_review' ? 'selected' : '' }}>Full
                        Review</option>
                    <option value="reel"
                        {{ old('video_type', $video->video_type ?? '') == 'reel' ? 'selected' : '' }}>Reel</option>
                    <option value="live"
                        {{ old('video_type', $video->video_type ?? '') == 'live' ? 'selected' : '' }}>Live</option>
                    <option value="compilation"
                        {{ old('video_type', $video->video_type ?? '') == 'compilation' ? 'selected' : '' }}>
                        Compilation</option>
                </select>
            </div>

            @php
                // Prefer old() (after validation error) else controller-provided $selectedPlatforms
                $platformsSelected = old('video_platforms', $selectedPlatforms ?? []);

                // Normalize to array (handles: array, JSON string, CSV string)
                if (is_string($platformsSelected)) {
                    $maybeJson = json_decode($platformsSelected, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $platformsSelected = is_array($maybeJson) ? $maybeJson : [$maybeJson];
                    } else {
                        $platformsSelected = array_filter(array_map('trim', explode(',', $platformsSelected)));
                    }
                }
            @endphp

            <div class="mb-3">
                <label for="video_platforms">Video Platform(s)</label>
                <select name="video_platforms[]" id="video_platforms" class="form-select" multiple>
                    <option value="YouTube" {{ in_array('YouTube', $platformsSelected) ? 'selected' : '' }}>YouTube
                    </option>
                    <option value="TikTok" {{ in_array('TikTok', $platformsSelected) ? 'selected' : '' }}>TikTok
                    </option>
                    <option value="Instagram"{{ in_array('Instagram', $platformsSelected) ? 'selected' : '' }}>
                        Instagram</option>
                    <option value="Facebook" {{ in_array('Facebook', $platformsSelected) ? 'selected' : '' }}>
                        Facebook</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="raw_video_file">Raw Video File</label>
                <input type="file" name="raw_video_file" id="raw_video_file" class="form-control"
                    accept="video/*">
            </div>

            <div class="mb-3">
                <label for="caption_file">Caption File</label>
                <input type="file" name="caption_file" id="caption_file" class="form-control"
                    accept=".srt,.vtt">
            </div>

            <div class="mb-3">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="draft" {{ old('status', $video->status ?? '') == 'draft' ? 'selected' : '' }}>
                        Draft</option>
                    <option value="published"
                        {{ old('status', $video->status ?? '') == 'published' ? 'selected' : '' }}>Published</option>
                </select>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_ai_generated" name="is_ai_generated"
                    value="1" {{ old('is_ai_generated', $video->is_ai_generated ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_ai_generated">AI Generated</label>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_finalized" name="is_finalized"
                    value="1" {{ old('is_finalized', $video->is_finalized ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_finalized">Finalized</label>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="qa_passed" name="qa_passed" value="1"
                    {{ old('qa_passed', $video->qa_passed ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="qa_passed">QA Passed</label>
            </div>

            <div class="mb-3">
                <label for="post_schedule_at">Post Schedule At</label>
                <input type="datetime-local" name="post_schedule_at" id="post_schedule_at" class="form-control"
                    value="{{ old('post_schedule_at', isset($video->post_schedule_at) ? \Carbon\Carbon::parse($video->post_schedule_at)->format('Y-m-d\TH:i') : '') }}">
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="submit" class="btn btn-success">Submit</button>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
<script>
    (function() {
        const $select = $('#tags_select');
        const $hidden = $('#tag_ids');

        // Preload existing selection from hidden CSV (for edit)
        const preloadIdsCsv = ($hidden.val() || '').trim();
        const preloadIds = preloadIdsCsv ? preloadIdsCsv.split(',').map(s => s.trim()).filter(Boolean) : [];

        $select.select2({
            placeholder: 'Search & select tags…',
            allowClear: true,
            multiple: true,
            ajax: {
                delay: 200,
                url: '{{ route('admin.tags.index') }}',
                dataType: 'json',
                data: params => ({
                    q: params.term || '',
                    page: params.page || 1
                }),
                processResults: (data) => data
            },
            // So we can type arbitrary text then click "Create new tag" button
            tags: false, // creation is handled by button
            width: '100%'
        });

        // If editing: fetch tag objects for IDs and set them selected
        if (preloadIds.length) {
            $.get('{{ route('admin.tags.byIds') }}', {
                ids: preloadIds
            }, function(resp) {
                (resp.results || []).forEach(function(t) {
                    const opt = new Option(t.text, t.id, true, true);
                    $select.append(opt);
                });
                $select.trigger('change');
            });
        }

        // keep hidden CSV synced
        function syncHidden() {
            const ids = ($select.val() || []);
            $hidden.val(ids.join(','));
        }
        $select.on('change', syncHidden);
        syncHidden();

        // Create new tag flow
        $('#createTagBtn').on('click', function() {
            const typed = $select.data('select2')?.dropdown?._search?.$search.val().trim() || '';
            const name = typed || prompt('New tag name');
            if (!name) return;

            $.ajax({
                method: 'POST',
                url: '{{ route('admin.tags.store') }}',
                data: {
                    name: name,
                    _token: '{{ csrf_token() }}'
                },
                success: function(tag) {
                    // add & select
                    let opt = $select.find('option[value="' + tag.id + '"]');
                    if (!opt.length) {
                        opt = new Option(tag.text, tag.id, true, true);
                        $select.append(opt);
                    } else {
                        opt.prop('selected', true);
                    }
                    $select.trigger('change');
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || 'Failed to create tag');
                }
            });
        });
    })();
</script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for the Highlight Tags dropdown
        $('#highlight_tags').select2({
            placeholder: '-- Select Highlight Tags --',
            allowClear: true
        });

        // Initialize Select2 for the Video Platforms dropdown
        $('#video_platforms').select2({
            placeholder: '-- Select Video Platforms --',
            allowClear: true
        });
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const steps = document.querySelectorAll(".form-step");
        const nextBtns = document.querySelectorAll(".next-step");
        const prevBtns = document.querySelectorAll(".prev-step");
        const indicators = document.querySelectorAll(".step-indicator");
        const progressBar = document.getElementById("formProgress");
        const currentStepEl = document.getElementById("currentStep");
        const form = document.getElementById("stepForm");

        // thumbnail toggle
        const thumbUrlOption = document.getElementById("thumb_url_option");
        const thumbImageOption = document.getElementById("thumb_image_option");
        const thumbUrlInput = document.getElementById("thumb_url_input");
        const thumbImageInput = document.getElementById("thumb_image_input");

        // Store old image and URL for validation
        const oldThumbnailImage =
            '{{ $video->thumbnail_image ?? '' }}'; // Get the old thumbnail image if exists
        const oldThumbnailUrl = '{{ $video->thumbnail_url ?? '' }}'; // Get the old thumbnail URL if exists

        const ratingTypeSelect = document.getElementById('rating_type');
        const ratingFields = document.getElementById('rating_fields');
        const reviewFields = document.getElementById('review_fields');

        // Function to toggle the visibility of input fields based on the rating type selection
        function toggleRatingReviewFields() {
            const selectedType = ratingTypeSelect.value;

            // Reset fields visibility
            ratingFields.style.display = 'none';
            reviewFields.style.display = 'none';

            // Show relevant fields based on selection
            if (selectedType === 'rating') {
                ratingFields.style.display = 'block';
            } else if (selectedType === 'review') {
                reviewFields.style.display = 'block';
            }
        }

        // Listen for changes on the rating type select
        ratingTypeSelect.addEventListener('change', toggleRatingReviewFields);

        // Initialize visibility based on the current selection
        toggleRatingReviewFields();

        let currentStep = 0;

        function showStep(index) {
            steps.forEach((step, i) => {
                step.classList.toggle("active", i === index);
            });

            // update required fields
            updateRequiredAttributes(index);

            // update progress bar
            const progress = ((index + 1) / steps.length) * 100;
            progressBar.style.width = progress + "%";
            progressBar.textContent = `Step ${index + 1} of ${steps.length}`;
            currentStepEl.textContent = index + 1;

            // update indicators
            indicators.forEach((el, i) => {
                el.classList.toggle("active", i === index);
            });

            currentStep = index;
        }

        function updateRequiredAttributes(stepIndex) {
            steps.forEach((step, i) => {
                const inputs = step.querySelectorAll("input, select, textarea");
                inputs.forEach((input) => {
                    if (input.dataset.alwaysOptional === "true") {
                        input.removeAttribute("required");
                        return;
                    }

                    if (i === stepIndex) {
                        if (input.dataset.wasRequired === "true" || input.hasAttribute(
                                "required")) {
                            input.setAttribute("required", "required");
                        }
                    } else {
                        if (input.hasAttribute("required")) {
                            input.dataset.wasRequired = "true";
                            input.removeAttribute("required");
                        }
                    }
                });
            });
        }

        function validateStep(stepIndex) {
            const step = steps[stepIndex];
            const inputs = step.querySelectorAll("input, select, textarea");
            let valid = true;

            inputs.forEach((input) => {
                if (input.hasAttribute("required") && !input.value.trim()) {
                    input.classList.add("is-invalid");
                    valid = false;
                } else {
                    input.classList.remove("is-invalid");
                }
            });

            return valid;
        }

        nextBtns.forEach((btn) => {
            btn.addEventListener("click", function() {
                if (!validateStep(currentStep)) return;
                if (currentStep < steps.length - 1) {
                    showStep(currentStep + 1);
                }
            });
        });

        prevBtns.forEach((btn) => {
            btn.addEventListener("click", function() {
                if (currentStep > 0) {
                    showStep(currentStep - 1);
                }
            });
        });

        indicators.forEach((indicator, index) => {
            indicator.addEventListener("click", function() {
                if (index <= currentStep || validateStep(currentStep)) {
                    showStep(index);
                }
            });
        });

        form.addEventListener("submit", function(e) {
            if (!validateStep(currentStep)) {
                e.preventDefault();
            }
        });

        showStep(currentStep);
    });
</script>


<!-- JavaScript for Validation -->
<script>
    document.getElementById('nextStepBtn').addEventListener('click', function() {
        let isValid = true;

        // Reset all error messages
        document.querySelectorAll('.invalid-feedback').forEach(function(error) {
            error.style.display = 'none';
        });

        // Check if Character is selected
        let characterSelect = document.getElementById('character_id');
        if (!characterSelect.value) {
            isValid = false;
            document.getElementById('character_error').style.display = 'block';
            characterSelect.classList.add('is-invalid');
        } else {
            characterSelect.classList.remove('is-invalid');
        }

        // Check if Channel is selected
        let channelSelect = document.getElementById('channel_id');
        if (!channelSelect.value) {
            isValid = false;
            document.getElementById('channel_error').style.display = 'block';
            channelSelect.classList.add('is-invalid');
        } else {
            channelSelect.classList.remove('is-invalid');
        }

        // Check if Category is selected
        let categorySelect = document.getElementById('category_id');
        if (!categorySelect.value) {
            isValid = false;
            document.getElementById('category_error').style.display = 'block';
            categorySelect.classList.add('is-invalid');
        } else {
            categorySelect.classList.remove('is-invalid');
        }

        // Check if Access Level is selected
        let accessLevelSelect = document.getElementById('access_level');
        if (!accessLevelSelect.value) {
            isValid = false;
            document.getElementById('access_level_error').style.display = 'block';
            accessLevelSelect.classList.add('is-invalid');
        } else {
            accessLevelSelect.classList.remove('is-invalid');
        }

    });

    // Event listeners to remove error messages once the user selects a valid option
    document.getElementById('character_id').addEventListener('change', function() {
        let characterSelect = this;
        let characterError = document.getElementById('character_error');
        if (characterSelect.value) {
            characterSelect.classList.remove('is-invalid');
            characterError.style.display = 'none';
        }
    });

    document.getElementById('channel_id').addEventListener('change', function() {
        let channelSelect = this;
        let channelError = document.getElementById('channel_error');
        if (channelSelect.value) {
            channelSelect.classList.remove('is-invalid');
            channelError.style.display = 'none';
        }
    });

    document.getElementById('category_id').addEventListener('change', function() {
        let categorySelect = this;
        let categoryError = document.getElementById('category_error');
        if (categorySelect.value) {
            categorySelect.classList.remove('is-invalid');
            categoryError.style.display = 'none';
        }
    });

    document.getElementById('access_level').addEventListener('change', function() {
        let accessLevelSelect = this;
        let accessLevelError = document.getElementById('access_level_error');
        if (accessLevelSelect.value) {
            accessLevelSelect.classList.remove('is-invalid');
            accessLevelError.style.display = 'none';
        }
    });
</script>
