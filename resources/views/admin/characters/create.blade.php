@extends('layouts.admin.master')

@section('title', 'Add Character')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Add Character</h4>
            <form action="{{ route('admin.characters.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Persona <span class="text-danger">*</span></label>
                    <textarea name="persona" class="form-control" rows="3" required>{{ old('persona') }}</textarea>
                    @error('persona')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Mini Bio <span class="text-danger">*</span></label>
                    <textarea name="details" class="form-control" rows="3" required>{{ old('details') }}</textarea>
                    @error('details')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Character Image</label>
                    <input type="file" name="image" class="form-control">
                    @error('image')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror

                    {{-- Display current image in edit mode --}}
                    @if(isset($character) && $character->image)
                        <div class="mt-2">
                            <label>Current Image:</label><br>
                            <img src="{{ asset($character->image) }}" alt="Character Image" style="max-width: 200px; height: auto;">
                        </div>
                    @endif
                </div>

                <div class="form-group mt-3">
                    <label>Channel <span class="text-danger">*</span></label>
                    <select name="channel_id" class="form-control" required>
                        <option value="">Select Channel</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->id }}" {{ old('channel_id') == $channel->id ? 'selected' : '' }}>
                                {{ $channel->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('channel_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location') }}">
                    @error('location')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" value="{{ old('age') }}" min="0">
                    @error('age')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Species</label>
                    <input type="text" name="species" class="form-control" value="{{ old('species') }}">
                    @error('species')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Style/Vibe</label>
                    <input type="text" name="style_vibe" class="form-control" value="{{ old('style_vibe') }}">
                    @error('style_vibe')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Score Fields --}}
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Durability Score</label>
                            <input type="number" name="durability_score" class="form-control"
                                value="{{ old('durability_score') }}" min="0" max="100">
                            @error('durability_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Durability Notes</label>
                            <textarea name="durability_notes" class="form-control" rows="2">{{ old('durability_notes') }}</textarea>
                            @error('durability_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Comfort Score</label>
                            <input type="number" name="comfort_score" class="form-control"
                                value="{{ old('comfort_score') }}" min="0" max="100">
                            @error('comfort_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Comfort Notes</label>
                            <textarea name="comfort_notes" class="form-control" rows="2">{{ old('comfort_notes') }}</textarea>
                            @error('comfort_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Style Score</label>
                            <input type="number" name="style_score" class="form-control" value="{{ old('style_score') }}"
                                min="0" max="100">
                            @error('style_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Style Notes</label>
                            <textarea name="style_notes" class="form-control" rows="2">{{ old('style_notes') }}</textarea>
                            @error('style_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Affordability Score</label>
                            <input type="number" name="affordability_score" class="form-control"
                                value="{{ old('affordability_score') }}" min="0" max="100">
                            @error('affordability_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Affordability Notes</label>
                            <textarea name="affordability_notes" class="form-control" rows="2">{{ old('affordability_notes') }}</textarea>
                            @error('affordability_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tech Feature Score</label>
                            <input type="number" name="tech_feature_score" class="form-control"
                                value="{{ old('tech_feature_score') }}" min="0" max="100">
                            @error('tech_feature_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tech Feature Notes</label>
                            <textarea name="tech_feature_notes" class="form-control" rows="2">{{ old('tech_feature_notes') }}</textarea>
                            @error('tech_feature_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Eco-friendliness Score</label>
                            <input type="number" name="eco_friendliness_score" class="form-control"
                                value="{{ old('eco_friendliness_score') }}" min="0" max="100">
                            @error('eco_friendliness_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Eco-friendliness Notes</label>
                            <textarea name="eco_friendliness_notes" class="form-control" rows="2">{{ old('eco_friendliness_notes') }}</textarea>
                            @error('eco_friendliness_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Engagement Score</label>
                            <input type="number" name="engagement_score" class="form-control"
                                value="{{ old('engagement_score') }}" min="0" max="100">
                            @error('engagement_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Engagement Notes</label>
                            <textarea name="engagement_notes" class="form-control" rows="2">{{ old('engagement_notes') }}</textarea>
                            @error('engagement_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Ease of Use Score</label>
                            <input type="number" name="ease_of_use_score" class="form-control"
                                value="{{ old('ease_of_use_score') }}" min="0" max="100">
                            @error('ease_of_use_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Ease of Use Notes</label>
                            <textarea name="ease_of_use_notes" class="form-control" rows="2">{{ old('ease_of_use_notes') }}</textarea>
                            @error('ease_of_use_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Performance Score</label>
                            <input type="number" name="performance_score" class="form-control"
                                value="{{ old('performance_score') }}" min="0" max="100">
                            @error('performance_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Performance Notes</label>
                            <textarea name="performance_notes" class="form-control" rows="2">{{ old('performance_notes') }}</textarea>
                            @error('performance_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Brand Reputation Score</label>
                            <input type="number" name="brand_reputation_score" class="form-control"
                                value="{{ old('brand_reputation_score') }}" min="0" max="100">
                            @error('brand_reputation_score')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Brand Reputation Notes</label>
                            <textarea name="brand_reputation_notes" class="form-control" rows="2">{{ old('brand_reputation_notes') }}</textarea>
                            @error('brand_reputation_notes')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success">Save</button>
                    <a href="{{ route('admin.characters.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection
