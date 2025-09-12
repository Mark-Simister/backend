@extends('layouts.admin.master')
@section('title', 'Add Subscription')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Add Subscription</h4>
        @can('subscription_list.create')
        <form action="{{ route('admin.subscription_listing.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="subscription_name">Subscription Name <span class="text-danger">*</span></label>
                <input type="text" name="subscription_name" class="form-control" value="{{ old('subscription_name') }}" required>
                @error('subscription_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="sub_description">Description</label>
                <textarea name="sub_description" class="form-control" rows="4">{{ old('sub_description') }}</textarea>
                @error('sub_description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="price">Price <span class="text-danger">*</span></label>
                <input type="number" name="price" class="form-control" value="{{ old('price') }}" required>
                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="duration">Duration <span class="text-danger">*</span></label>
                <input type="number" name="duration" class="form-control" value="{{ old('duration') }}" required>
                @error('duration') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="duration_unit">Duration Unit <span class="text-danger">*</span></label>
                <select name="duration_unit" class="form-select" required>
                    <option value="month" {{ old('duration_unit') == 'month' ? 'selected' : '' }}>Month</option>
                    <option value="year" {{ old('duration_unit') == 'year' ? 'selected' : '' }}>Year</option>
                </select>
                @error('duration_unit') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="type">Subscription Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                    <option value="one_time" {{ old('type') == 'one_time' ? 'selected' : '' }}>One-time</option>
                    <option value="recurring" {{ old('type') == 'recurring' ? 'selected' : '' }}>Recurring</option>
                </select>
                @error('type') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group region-flex mt-3">
                <label>Regions</label><br>
                @foreach($regions as $region)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" 
                               name="regions[]" 
                               value="{{ $region->id }}" 
                               {{ (is_array(old('regions')) && in_array($region->id, old('regions'))) ? 'checked' : '' }}>
                        <label class="form-check-label">{{ $region->name }}</label>
                    </div>
                @endforeach
                @error('regions') <span class="text-danger d-block">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success mt-3">Save</button>
        </form>
        @endcan
    </div>
</div>
@endsection
