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

<div class="">
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

    <form action="{{ isset($video) ? route('admin.videos.update', $video) : route('admin.videos.store') }}" method="POST"
        enctype="multipart/form-data" id="stepForm">
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

            <div class="mb-3">
                <label for="type">Video Platform Type <span class="text-danger">*</span></label>
                <select name="type" id="type" class="form-select" required>
                    <option value="">-- Select Platform --</option>
                    <option value="youtube" {{ old('type', $video->type ?? '') == 'youtube' ? 'selected' : '' }}>YouTube</option>
                    <option value="vimeo" {{ old('type', $video->type ?? '') == 'vimeo' ? 'selected' : '' }}>Vimeo</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="video_url">Video URL <span class="text-danger">*</span></label>
                <input type="url" name="video_url" id="video_url" class="form-control" required
                    value="{{ old('video_url', $video->video_url ?? '') }}">
            </div>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary next-step">Next</button>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="form-step">
            <div class="mb-3">
                <label for="character_id">Character</label>
                <select name="character_id" id="character_id" class="form-select">
                    <option value="">-- Select Character --</option>
                    @foreach ($characters as $character)
                        <option value="{{ $character->id }}" {{ old('character_id', $video->character_id ?? '') == $character->id ? 'selected' : '' }}>
                            {{ $character->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="channel_id">Channel</label>
                <select name="channel_id" id="channel_id" class="form-select">
                    <option value="">-- Select Channel --</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" {{ old('channel_id', $video->channel_id ?? '') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" class="form-select">
                    <option value="">-- Select Category --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $video->category_id ?? '') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="access_level">Access Level</label>
                <select name="access_level" id="access_level" class="form-select">
                    <option value="public" {{ old('access_level', $video->access_level ?? '') == 'public' ? 'selected' : '' }}>Public</option>
                    <option value="premium" {{ old('access_level', $video->access_level ?? '') == 'premium' ? 'selected' : '' }}>Premium</option>
                    <option value="early_access" {{ old('access_level', $video->access_level ?? '') == 'early_access' ? 'selected' : '' }}>Early Access</option>
                </select>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="button" class="btn btn-primary next-step">Next</button>
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
                $thumbSource = old('thumbnail_option', !empty($video?->thumbnail_image) ? 'image' : 'url');
            @endphp

            <div class="mb-3">
                <label class="form-label">Thumbnail</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="thumbnail_option" id="thumb_url_option"
                        value="url" {{ $thumbSource == 'url' ? 'checked' : '' }}>
                    <label class="form-check-label" for="thumb_url_option">Use Thumbnail URL</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="thumbnail_option" id="thumb_image_option"
                        value="image" {{ $thumbSource == 'image' ? 'checked' : '' }}>
                    <label class="form-check-label" for="thumb_image_option">Upload Thumbnail Image</label>
                </div>
            </div>

            <div class="mb-3" id="thumb_url_input" style="{{ $thumbSource == 'url' ? '' : 'display:none;' }}">
                <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                <input type="url" name="thumbnail_url" id="thumbnail_url"
                    class="form-control @error('thumbnail_url') is-invalid @enderror"
                    placeholder="https://example.com/image.jpg"
                    value="{{ old('thumbnail_url', $video->thumbnail_url ?? '') }}">
                @error('thumbnail_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3" id="thumb_image_input" style="{{ $thumbSource == 'image' ? '' : 'display:none;' }}">
                <label for="thumbnail_image" class="form-label">Thumbnail Image</label>
                <input type="file" name="thumbnail_image" id="thumbnail_image"
                    class="form-control @error('thumbnail_image') is-invalid @enderror"
                    accept="image/*">
                @error('thumbnail_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="button" class="btn btn-primary next-step">Next</button>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="form-step">
            {{-- <div class="mb-3">
                <label for="meta_title">Meta Title</label>
                <input type="text" name="meta_title" id="meta_title" class="form-control" maxlength="70"
                    value="{{ old('meta_title', $video->meta_title ?? '') }}">
            </div>

            <div class="mb-3">
                <label for="meta_description">Meta Description</label>
                <textarea name="meta_description" id="meta_description" class="form-control" maxlength="160">{{ old('meta_description', $video->meta_description ?? '') }}</textarea>
            </div> --}}

            {{-- <div class="mb-3">
                <label for="keywords">Keywords</label>
                <input type="text" name="keywords" id="keywords" class="form-control"
                    value="{{ old('keywords', $video->keywords ?? '') }}">
            </div> --}}

            
  @php
    function cleanTags($value) {
        if (empty($value)) return '';

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

<div class="mb-3">
    <label for="tags">Tags</label>
    <input type="text" name="tags" id="tags" class="form-control"
    value="{{ old('tags', isset($video->tags) ? cleanTags($video->tags) : '') }}">
</div>

    <div class="mb-3">
        <label for="rating_type">Rating Type</label>
        <select name="rating_type" id="rating_type" class="form-select">
            <option value="">-- Select Rating Type --</option>
            <option value="rating" {{ old('rating_type', $video->rating_type ?? '') == 'rating' ? 'selected' : '' }}>Rating</option>
            <option value="review" {{ old('rating_type', $video->rating_type ?? '') == 'review' ? 'selected' : '' }}>Review</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="sponsorship_type">Sponsorship Type</label>
        <select name="sponsorship_type" id="sponsorship_type" class="form-select">
            <option value="">-- Select Sponsorship Type --</option>
            <option value="sponsored" {{ old('sponsorship_type', $video->sponsorship_type ?? '') == 'sponsored' ? 'selected' : '' }}>Sponsored</option>
            <option value="unsponsored" {{ old('sponsorship_type', $video->sponsorship_type ?? '') == 'unsponsored' ? 'selected' : '' }}>Unsponsored</option>
        </select>
    </div>

    {{-- <div class="mb-3">
        <label for="highlight_tags">Highlight Tags (comma separated)</label>
        <input type="text" name="highlight_tags" id="highlight_tags" class="form-control"
            value="{{ old('highlight_tags', isset($video->highlight_tags) ? implode(',', (array) $video->highlight_tags) : '') }}">
    </div> --}}
    <div class="mb-3">
        <label for="highlight_tags">Highlight Tags (comma separated)</label>
        <input type="text" name="highlight_tags" id="highlight_tags" class="form-control"
    value="{{ old('highlight_tags', isset($video->highlight_tags) ? cleanTags($video->highlight_tags) : '') }}">
    </div>

    {{-- <div class="mb-3">
        <label for="auto_tags">Auto Tags (comma separated)</label>
        <input type="text" name="auto_tags" id="auto_tags" class="form-control"
            value="{{ old('auto_tags', isset($video->auto_tags) ? implode(',', (array) $video->auto_tags) : '') }}">
    </div> --}}
    <div class="mb-3">
        <label for="auto_tags">Auto Tags (comma separated)</label>
        <input type="text" name="auto_tags" id="auto_tags" class="form-control"
    value="{{ old('auto_tags', isset($video->auto_tags) ? cleanTags($video->auto_tags) : '') }}">
    </div>

    <div class="mb-3">
        <label for="video_type">Video Type</label>
        <select name="video_type" id="video_type" class="form-select">
            <option value="">-- Select Video Type --</option>
            <option value="short" {{ old('video_type', $video->video_type ?? '') == 'short' ? 'selected' : '' }}>Short</option>
            <option value="full_review" {{ old('video_type', $video->video_type ?? '') == 'full_review' ? 'selected' : '' }}>Full Review</option>
            <option value="reel" {{ old('video_type', $video->video_type ?? '') == 'reel' ? 'selected' : '' }}>Reel</option>
            <option value="live" {{ old('video_type', $video->video_type ?? '') == 'live' ? 'selected' : '' }}>Live</option>
            <option value="compilation" {{ old('video_type', $video->video_type ?? '') == 'compilation' ? 'selected' : '' }}>Compilation</option>
        </select>
    </div>

    @php
        $selectedPlatforms = old('video_platforms', $video->video_platforms ?? []);
        if (is_string($selectedPlatforms)) {
            $selectedPlatforms = explode(',', $selectedPlatforms); // Convert comma-separated string to array
        }
    @endphp

    <div class="mb-3">
        <label for="video_platforms">Video Platform(s)</label>
        <select name="video_platforms[]" id="video_platforms" class="form-select" multiple>
            <option value="YouTube" {{ in_array('YouTube', $selectedPlatforms) ? 'selected' : '' }}>YouTube</option>
            <option value="TikTok" {{ in_array('TikTok', $selectedPlatforms) ? 'selected' : '' }}>TikTok</option>
            <option value="Instagram" {{ in_array('Instagram', $selectedPlatforms) ? 'selected' : '' }}>Instagram</option>
            <option value="Facebook" {{ in_array('Facebook', $selectedPlatforms) ? 'selected' : '' }}>Facebook</option>
        </select>
        <small class="form-text text-muted">Hold CTRL (Windows) or CMD (Mac) to select multiple platforms</small>
    </div>

    <div class="mb-3">
        <label for="raw_video_file">Raw Video File</label>
        <input type="file" name="raw_video_file" id="raw_video_file" class="form-control" accept="video/*">
    </div>

    <div class="mb-3">
        <label for="caption_file">Caption File</label>
        <input type="file" name="caption_file" id="caption_file" class="form-control" accept=".srt,.vtt">
    </div>

    <div class="mb-3">
        <label for="status">Status</label>
        <select name="status" id="status" class="form-select">
            <option value="draft" {{ old('status', $video->status ?? '') == 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="published" {{ old('status', $video->status ?? '') == 'published' ? 'selected' : '' }}>Published</option>
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
        <input type="checkbox" class="form-check-input" id="is_qa_passed" name="is_qa_passed"
            value="1" {{ old('is_qa_passed', $video->is_qa_passed ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_qa_passed">QA Passed</label>
    </div>

    <div class="mb-3">
        <label for="post_schedule_at">Post Schedule At</label>
        <input type="datetime-local" name="post_schedule_at" id="post_schedule_at" class="form-control"
            value="{{ old('post_schedule_at', isset($video->post_schedule_at) ? \Carbon\Carbon::parse($video->post_schedule_at)->format('Y-m-d\TH:i') : '') }}">
    </div>

    <div class="mb-3">
        <label for="seo_title">SEO Title</label>
        <input type="text" name="seo_title" id="seo_title" class="form-control"
            value="{{ old('seo_title', $video->seo_title ?? '') }}">
    </div>

    <div class="mb-3">
        <label for="seo_description">SEO Description</label>
        <textarea name="seo_description" id="seo_description" class="form-control">{{ old('seo_description', $video->seo_description ?? '') }}</textarea>
    </div>

    {{-- <div class="mb-3">
        <label for="hashtags">Hashtags (comma separated)</label>
        <input type="text" name="hashtags" id="hashtags" class="form-control"
            value="{{ old('hashtags', isset($video->hashtags) ? implode(',', (array) $video->hashtags) : '') }}">
    </div> --}}
    <div class="mb-3">
        <label for="hashtags">Hashtags (comma separated)</label>
        <input type="text" name="hashtags" id="hashtags" class="form-control"
            value="{{ old('hashtags', isset($video->hashtags) ? (is_array($video->hashtags) ? implode(', ', $video->hashtags) : $video->hashtags) : '') }}">
    </div>

    <div class="mb-3">
        <label for="cta_text">CTA Text</label>
        <input type="text" name="cta_text" id="cta_text" class="form-control"
            value="{{ old('cta_text', $video->cta_text ?? '') }}">
    </div>

    <div class="mb-3">
        <label for="og_image_url">OG Image URL</label>
        <input type="url" name="og_image_url" id="og_image_url" class="form-control"
            value="{{ old('og_image_url', $video->og_image_url ?? '') }}">
    </div>

    <div class="mb-3">
        <label for="twitter_title">Twitter Title</label>
        <input type="text" name="twitter_title" id="twitter_title" class="form-control"
            value="{{ old('twitter_title', $video->twitter_title ?? '') }}">
    </div>

    <div class="mb-3">
        <label for="twitter_description">Twitter Description</label>
        <textarea name="twitter_description" id="twitter_description" class="form-control">{{ old('twitter_description', $video->twitter_description ?? '') }}</textarea>
    </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary prev-step">Back</button>
                <button type="submit" class="btn btn-success">Submit</button>
            </div>
        </div>
    </form>
</div>

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

        function toggleThumbnailInputs() {
            if (thumbUrlOption.checked) {
                thumbUrlInput.style.display = "";
                thumbImageInput.style.display = "none";
            } else if (thumbImageOption.checked) {
                thumbUrlInput.style.display = "none";
                thumbImageInput.style.display = "";
            }
        }

        thumbUrlOption.addEventListener("change", toggleThumbnailInputs);
        thumbImageOption.addEventListener("change", toggleThumbnailInputs);
        toggleThumbnailInputs(); // init on page load

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
                        if (input.dataset.wasRequired === "true" || input.hasAttribute("required")) {
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

            if (stepIndex === 2) {
                const thumbOption = document.querySelector('input[name="thumbnail_option"]:checked');
                const urlInput = document.getElementById("thumbnail_url");
                const imageInput = document.getElementById("thumbnail_image");

                if (!thumbOption) return valid;

                const hasUrl = urlInput && urlInput.value.trim();
                const hasImage = imageInput && imageInput.files.length > 0;

                if (thumbOption.value === "url" && !hasUrl) {
                    alert("Please enter a valid Thumbnail URL.");
                    urlInput.classList.add("is-invalid");
                    valid = false;
                } else if (thumbOption.value === "image" && !hasImage) {
                    alert("Please upload a Thumbnail Image.");
                    imageInput.classList.add("is-invalid");
                    valid = false;
                }
            }
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