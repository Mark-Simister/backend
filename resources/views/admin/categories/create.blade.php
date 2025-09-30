@extends('layouts.admin.master')
@section('title', 'Add Category')

@push('styles')
<style>
/*  */
</style>
    
@endpush
@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Add Category</h4>
            @can('category.create')
                <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input name="name" class="form-control" value="{{ old('name') }}" required>
                        @error('name')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Channel <span class="text-danger">*</span></label>
                        {{-- <select name="channel_id" class="form-control" required>
                    <option value="">-- Select Channel --</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->id }}" {{ old('channel_id') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name }}
                        </option>
                    @endforeach
                </select> --}}
                        <select name="channel_id" id="channel_id" class="form-control" required>
  <option></option>
  @foreach ($channels as $channel)
    <option value="{{ $channel->id }}" {{ old('channel_id') == $channel->id ? 'selected' : '' }}>
      {{ $channel->name }}
    </option>
  @endforeach
</select>


                        @error('channel_id')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                        @error('channel_ids')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    {{-- <div class="form-group mt-3">
                        <label>Channels <span class="text-danger">*</span></label>
                        <select name="channel_ids[]" class="form-control @error('channel_ids') is-invalid @enderror" required> 
                            <option value="" disabled {{ old('channel_ids') ? '' : 'selected' }} class="text-danger">
                                -- Select Channel --
                            </option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}"
                                    {{ collect(old('channel_ids'))->contains($channel->id) ? 'selected' : '' }}>
                                    {{ $channel->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('channel_ids')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div> --}}


                    <div class="form-group region-flex">
                        <label>Select Regions</label><br>
                        @foreach ($regions as $region)
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="regions[]" value="{{ $region->id }}"
                                    id="region_{{ $region->id }}" class="form-check-input">
                                <label class="form-check-label" for="region_{{ $region->id }}">
                                    {{ $region->region_name }}
                                </label>
                            </div>
                        @endforeach
                    </div>


                    <div class="form-group mt-3">
                        <label>Category Image</label>
                        <input type="file" name="image" class="form-control">
                        @error('image')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <button class="btn btn-success mt-3">Save</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
<script>
  $(function () {
    $('#channel_id').select2({
      width: '100%',
      placeholder: '-- Select Channel --',
      allowClear: true
    });
  });
</script>
@endpush
