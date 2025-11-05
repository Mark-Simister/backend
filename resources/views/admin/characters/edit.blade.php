@extends('layouts.admin.master')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .ck-editor__editable_inline {
            min-height: 300px;
        }
    </style>
@endpush
@section('title', 'Edit Character')

@section('content')
    <div class="card">

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Whoops! There were some problems with your input:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="card-body">
            <h4>Edit Character</h4>
            @can('character.edit')

                <form action="{{ route('admin.characters.update', $character) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $character->name) }}"
                            required>
                        @error('name')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group region-flex">
                        <label>Select Regions:</label><br>
                        @foreach ($regions as $region)
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="regions[]" value="{{ $region->id }}" class="form-check-input"
                                    {{ in_array($region->id, $selectedRegions ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label">
                                    {{ $region->region_name }}
                                    @if ($region->is_active != 1)
                                        <span class="badge bg-danger ms-1">Inactive</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-group mt-3">
                        <label>Persona</label>
                        <textarea name="persona" class="form-control" rows="3" required>{{ old('persona', $character->persona) }}</textarea>
                        {{-- <textarea id="persona" name="persona" class="form-control" rows="3">{{ old('persona', $character->persona) }}</textarea> --}}

                        @error('persona')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Mini Bio</label>
                        <textarea name="details" class="form-control" rows="3" required>{{ old('details', $character->details) }}</textarea>
                        {{-- <textarea id="details" name="details" class="form-control" rows="3">{{ old('details', $character->details) }}</textarea> --}}

                        @error('details')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Character Video</label>
                        <input type="file" name="video" accept="video/*" class="form-control">

                        @if ($character->video)
                            <div class="mt-2">
                                <video width="320" height="240" controls>
                                    <source src="{{ asset($character->video) }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        @endif

                        @error('video')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>


                    <div class="form-group mt-3">
                        <label>Character Image</label>
                        <input type="file" name="image" class="form-control">
                        @error('image')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror

                        {{-- Display current image if it exists --}}
                        @if ($character->image)
                            <div class="mt-2">
                                <label>Current Image:</label><br>
                                <img src="{{ asset($character->image) }}" alt="Current Character Image"
                                    style="max-width: 200px; height: auto;">
                            </div>
                            {{-- <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1"
                                id="removeImageCheckbox">
                            <label class="form-check-label" for="removeImageCheckbox">
                                Remove current image
                            </label>
                        </div> --}}
                        @endif
                    </div>

                    {{-- Thumbnail Image --}}
                    <div class="form-group mt-3">
                        <label>Character Thumbnail Image</label>
                        <input type="file" name="thumbnail_image" class="form-control">
                        @error('thumbnail_image')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror

                        {{-- Display current thumbnail if it exists --}}
                        @if ($character->thumbnail_image)
                            <div class="mt-2">
                                <label>Current Thumbnail:</label><br>
                                <img src="{{ asset($character->thumbnail_image) }}" alt="Current Thumbnail"
                                    style="max-width: 150px; height: auto;">
                            </div>
                        @endif
                    </div>

                    {{-- <div class="form-group mt-3">
                        <label>Category</label>
                        <select name="category_id" class="form-control" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('category_id', $character->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div> --}}
                    <div class="form-group mt-3">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id"
                            class="form-control @error('category_id') is-invalid @enderror" required>
                            <option></option> {{-- empty for Select2 placeholder --}}
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('category_id', $character->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- New Fields Start Here --}}

                    <div class="form-group mt-3">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control"
                            value="{{ old('location', $character->location) }}">
                        @error('location')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Age</label>
                        <input type="number" name="age" class="form-control" value="{{ old('age', $character->age) }}"
                            min="0">
                        @error('age')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Species</label>
                        <input type="text" name="species" class="form-control"
                            value="{{ old('species', $character->species) }}">
                        @error('species')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Style/Vibe</label>
                        <input type="text" name="style_vibe" class="form-control"
                            value="{{ old('style_vibe', $character->style_vibe) }}">
                        @error('style_vibe')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Score Fields --}}
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durability Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="durability_score" class="form-control"
                                    value="{{ old('durability_score', $character->durability_score) }}" min="0"
                                    max="5">
                                @error('durability_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durability Notes</label>
                                <textarea name="durability_notes" class="form-control" rows="2">{{ old('durability_notes', $character->durability_notes) }}</textarea>
                                @error('durability_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Comfort Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="comfort_score" class="form-control"
                                    value="{{ old('comfort_score', $character->comfort_score) }}" min="0"
                                    max="5">
                                @error('comfort_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Comfort Notes</label>
                                <textarea name="comfort_notes" class="form-control" rows="2">{{ old('comfort_notes', $character->comfort_notes) }}</textarea>
                                @error('comfort_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Style Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="style_score" class="form-control"
                                    value="{{ old('style_score', $character->style_score) }}" min="0" max="5">
                                @error('style_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Style Notes</label>
                                <textarea name="style_notes" class="form-control" rows="2">{{ old('style_notes', $character->style_notes) }}</textarea>
                                @error('style_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Affordability Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="affordability_score" class="form-control"
                                    value="{{ old('affordability_score', $character->affordability_score) }}" min="0"
                                    max="5">
                                @error('affordability_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Affordability Notes</label>
                                <textarea name="affordability_notes" class="form-control" rows="2">{{ old('affordability_notes', $character->affordability_notes) }}</textarea>
                                @error('affordability_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tech Feature Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="tech_feature_score" class="form-control"
                                    value="{{ old('tech_feature_score', $character->tech_feature_score) }}" min="0"
                                    max="5">
                                @error('tech_feature_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tech Feature Notes</label>
                                <textarea name="tech_feature_notes" class="form-control" rows="2">{{ old('tech_feature_notes', $character->tech_feature_notes) }}</textarea>
                                @error('tech_feature_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Eco-friendliness Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="eco_friendliness_score" class="form-control"
                                    value="{{ old('eco_friendliness_score', $character->eco_friendliness_score) }}"
                                    min="0" max="5">
                                @error('eco_friendliness_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Eco-friendliness Notes</label>
                                <textarea name="eco_friendliness_notes" class="form-control" rows="2">{{ old('eco_friendliness_notes', $character->eco_friendliness_notes) }}</textarea>
                                @error('eco_friendliness_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Engagement Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="engagement_score" class="form-control"
                                    value="{{ old('engagement_score', $character->engagement_score) }}" min="0"
                                    max="5">
                                @error('engagement_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Engagement Notes</label>
                                <textarea name="engagement_notes" class="form-control" rows="2">{{ old('engagement_notes', $character->engagement_notes) }}</textarea>
                                @error('engagement_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ease of Use Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="ease_of_use_score" class="form-control"
                                    value="{{ old('ease_of_use_score', $character->ease_of_use_score) }}" min="0"
                                    max="100">
                                @error('ease_of_use_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ease of Use Notes</label>
                                <textarea name="ease_of_use_notes" class="form-control" rows="2">{{ old('ease_of_use_notes', $character->ease_of_use_notes) }}</textarea>
                                @error('ease_of_use_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Performance Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="performance_score" class="form-control"
                                    value="{{ old('performance_score', $character->performance_score) }}" min="0"
                                    max="5">
                                @error('performance_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Performance Notes</label>
                                <textarea name="performance_notes" class="form-control" rows="2">{{ old('performance_notes', $character->performance_notes) }}</textarea>
                                @error('performance_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Brand Reputation Score <span class="text-info">(Score 1- 5)</span></label>
                                <input type="number" name="brand_reputation_score" class="form-control"
                                    value="{{ old('brand_reputation_score', $character->brand_reputation_score) }}"
                                    min="0" max="5">
                                @error('brand_reputation_score')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Brand Reputation Notes</label>
                                <textarea name="brand_reputation_notes" class="form-control" rows="2">{{ old('brand_reputation_notes', $character->brand_reputation_notes) }}</textarea>
                                @error('brand_reputation_notes')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <label for="public_private_toggle">Public/Private</label>
                        <div class="form-check">
                            <input type="radio" name="public_private_toggle" id="public_toggle" value="0"
                                class="form-check-input"
                                {{ old('public_private_toggle', $character->public_private_toggle ?? 0) == '0' ? 'checked' : '' }}>
                            <label class="form-check-label" for="public_toggle">Public</label>
                        </div>
                        <div class="form-check">
                            <input type="radio" name="public_private_toggle" id="private_toggle" value="1"
                                class="form-check-input"
                                {{ old('public_private_toggle', $character->public_private_toggle ?? 0) == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="private_toggle">Private</label>
                        </div>
                        @error('public_private_toggle')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label for="character_launch_date">Character Launch Date</label>
                        <input type="date" name="character_launch_date" id="character_launch_date" class="form-control"
                            value="{{ old('character_launch_date', isset($character->character_launch_date) ? \Carbon\Carbon::parse($character->character_launch_date)->format('Y-m-d') : '') }}">
                        @error('character_launch_date')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label for="character_popularity_score">Character Popularity Score <span class="text-info">(Score 1-
                                5)</span></label>
                        <input type="number" name="character_popularity_score" id="character_popularity_score"
                            class="form-control"
                            value="{{ old('character_popularity_score', $character->character_popularity_score ?? '') }}"
                            min="0" max="5">
                        @error('character_popularity_score')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label for="editor_notes_content_guidelines">Editor Notes/Content Guidelines</label>
                        {{-- <textarea name="editor_notes_content_guidelines" id="editor_notes_content_guidelines" class="form-control">{{ old('editor_notes_content_guidelines', $character->editor_notes_content_guidelines ?? '') }}</textarea> --}}
                        <textarea name="editor_notes_content_guidelines" id="editor_notes_content_guidelines" class="form-control">{{ old('editor_notes_content_guidelines', $character->editor_notes_content_guidelines ?? '') }}</textarea>

                        @error('editor_notes_content_guidelines')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    {{-- {{dd($character, $character_tag, $character->character_tag, $character_role,$character->character_role)}} --}}

                    <div class="form-group mt-3">
                        <label for="character_tag">Character Tag <span class="text-info">( Multi Select )</span></label>
                        <select name="character_tag[]" id="character_tag" class="form-control" multiple>
                            @foreach ($character_tag as $tag_option)
                                <option value="{{ $tag_option->name }}"
                                    {{ in_array($tag_option->name, old('character_tag', $currentTagNames ?? [])) ? 'selected' : '' }}>
                                    {{ $tag_option->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('character_tag')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                        @error('character_tag.*')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label for="character_role">Character Roles <span class="text-info">( Multi Select )</span></label>
                        <select name="character_role[]" id="character_role" class="form-control" multiple>
                            @foreach ($character_role as $role_option)
                                <option value="{{ $role_option->name }}"
                                    {{ in_array($role_option->name, old('character_role', $currentRoleNames ?? [])) ? 'selected' : '' }}>
                                    {{ $role_option->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('character_role')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                        @error('character_role.*')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <a href="{{ route('admin.characters.index') }}" class="btn btn-secondary">Back</a>
                    </div>

                </form>
            @endcan


        </div>
    </div>
@endsection

@push('scripts')
    {{-- <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script> --}}


    <!-- Include jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    {{-- <script src="https://cdn.ckeditor.com/ckeditor5/41.3.1/classic/ckeditor.js"></script> --}}
    <script>
        // const editors = {};

        // function makeCk(selector) {
        //     const el = document.querySelector(selector);
        //     if (!el) return;
        //     ClassicEditor.create(el, {
        //             toolbar: [
        //                 'undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link',
        //                 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'mediaEmbed'
        //             ],
        //         })
        //         .then(ed => {
        //             editors[selector] = ed;
        //             ed.ui.view.editable.element.style.minHeight = '300px';
        //         })
        //         .catch(console.error);
        // }

        // document.addEventListener('DOMContentLoaded', function() {
        //     makeCk('#persona');
        //     makeCk('#details');
        //     makeCk('#editor_notes_content_guidelines');

        //     // sync data back to textareas before submit (for Laravel validation + saving)
        //     const form = document.querySelector('form');
        //     if (form) {
        //         form.addEventListener('submit', function() {
        //             if (editors['#persona']) document.querySelector('#persona').value = editors['#persona']
        //                 .getData();
        //             if (editors['#details']) document.querySelector('#details').value = editors['#details']
        //                 .getData();
        //             if (editors['#editor_notes_content_guidelines']) document.querySelector(
        //                 '#editor_notes_content_guidelines').value = editors[
        //                 '#editor_notes_content_guidelines'].getData();
        //         });
        //     }
        // });

        $(function() {
            $('#category_id').select2({
                width: '100%',
                placeholder: 'Select Category',
                allowClear: true
            });

            $('#character_tag').select2({
                width: '100%',
                placeholder: 'Select tags',
                allowClear: true,
                closeOnSelect: false
            });

            $('#character_role').select2({
                width: '100%',
                placeholder: 'Select roles',
                allowClear: true,
                closeOnSelect: false
            });
        });
    </script>
@endpush
