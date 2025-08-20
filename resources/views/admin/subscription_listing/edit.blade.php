@extends('layouts.admin.master')
@section('title', 'Edit Subscription')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Subscription</h4>
        <form action="{{ route('admin.subscription_listing.update', $subscriptionListing->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="subscription_name">Subscription Name <span class="text-danger">*</span></label>
                <input type="text" name="subscription_name" class="form-control" value="{{ old('subscription_name', $subscriptionListing->subscription_name) }}" required>
                @error('subscription_name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="sub_description">Description</label>
                <textarea name="sub_description" class="form-control" rows="4">{{ old('sub_description', $subscriptionListing->sub_description) }}</textarea>
                @error('sub_description') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="price">Price <span class="text-danger">*</span></label>
                <input type="number" name="price" class="form-control" value="{{ old('price', $subscriptionListing->price) }}" required>
                @error('price') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="duration">Duration <span class="text-danger">*</span></label>
                <input type="number" name="duration" class="form-control" value="{{ old('duration', $subscriptionListing->duration) }}" required>
                @error('duration') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="duration_unit">Duration Unit <span class="text-danger">*</span></label>
                <select name="duration_unit" class="form-select" required>
                    <option value="month" {{ old('duration_unit', $subscriptionListing->duration_unit) == 'month' ? 'selected' : '' }}>Month</option>
                    <option value="year" {{ old('duration_unit', $subscriptionListing->duration_unit) == 'year' ? 'selected' : '' }}>Year</option>
                </select>
                @error('duration_unit') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="type">Subscription Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                    <option value="one_time" {{ old('type', $subscriptionListing->type) == 'one_time' ? 'selected' : '' }}>One-time</option>
                    <option value="recurring" {{ old('type', $subscriptionListing->type) == 'recurring' ? 'selected' : '' }}>Recurring</option>
                </select>
                @error('type') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-success mt-3">Update</button>
        </form>
    </div>
</div>
@endsection
