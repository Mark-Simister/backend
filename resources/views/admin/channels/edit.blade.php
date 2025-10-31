@extends('layouts.admin.master')
@section('title', 'Edit Channel')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Edit Channel</h4>
            @can('channel.edit')
                <form action="{{ route('admin.channels.update', $channel) }}" method="POST" enctype="multipart/form-data">
                    @csrf @method('PUT')

                    <div class="form-group mb-3">
                        <label>Channel Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $channel->name) }}" class="form-control"
                            required>
                        @error('name')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label>Channel Category <span class="text-danger">*</span></label>
                        <select name="channel_category" class="form-control" required>
                            <option value="people"
                                {{ old('channel_category', $channel->channel_category) == 'people' ? 'selected' : '' }}>People
                            </option>
                            <option value="pet"
                                {{ old('channel_category', $channel->channel_category) == 'pet' ? 'selected' : '' }}>Pet
                            </option>
                        </select>
                        @error('channel_category')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>


                    <div class="form-group region-flex">
                        <label for="regions">Select Regions:</label><br>
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
                    <div class="form-group mb-3">
                        <label>Channel Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        @error('image')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror

                        @if ($channel->image)
                            <div class="mt-2">
                                <p>Current Image:</p>
                                <img src="{{ asset($channel->image) }}" alt="Channel Image" width="120"
                                    class="img-thumbnail">
                            </div>
                        @endif
                    </div>

                    <div class="form-group mb-3">
                        <label>Channel Video</label>
                        <input type="file" name="video" class="form-control" accept="video/*">
                        @error('video')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror

                        @if (!empty($channel->video))
                            <div class="mt-2">
                                <p>Current Video:</p>
                                <video width="320" controls>
                                    <source src="{{ asset($channel->video) }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                <div class="small text-muted">{{ $channel->video }}</div>
                            </div>
                        @endif
                    </div>


                    <div class="form-group mb-3">
                        <label>Primary Color</label>
                        <input type="color" name="primary_color" class="form-control form-control-color"
                            value="{{ old('primary_color', $channel->primary_color ?? '#000000') }}">
                        @error('primary_color')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label>Secondary Color</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color"
                            value="{{ old('secondary_color', $channel->secondary_color ?? '#000000') }}">
                        @error('secondary_color')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label>Accent Color</label>
                        <input type="color" name="accent_color" class="form-control form-control-color"
                            value="{{ old('accent_color', $channel->accent_color ?? '#000000') }}">
                        @error('accent_color')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label>Background Color</label>
                        <input type="color" name="background_color" class="form-control form-control-color"
                            value="{{ old('background_color', $channel->background_color ?? '#ffffff') }}">
                        @error('background_color')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <button class="btn btn-primary mt-3">Update</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
