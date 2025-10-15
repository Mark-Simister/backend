@extends('layouts.admin.master')
@section('title', 'Add FAQ')

@push('styles')
<style>
/* Add any FAQ-specific styles here */
</style>
@endpush

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Add FAQ</h4>
            @can('faq.create')
                <form action="{{ route('admin.faqs.store') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label>Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" class="form-control" value="{{ old('question') }}" required>
                        @error('question')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" class="form-control" rows="5" required>{{ old('answer') }}</textarea>
                        @error('answer')
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
<!-- Add custom JS here if needed -->
@endpush
