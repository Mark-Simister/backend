@extends('layouts.admin.master')
@section('title', 'Edit FAQ')

@push('styles')
<style>
/* Add any FAQ-specific styles here */
</style>
@endpush

@section('content')
    <div class="card">
        <div class="card-body">
            <h4>Edit FAQ</h4>
            @can('faq.edit')
                <form action="{{ route('admin.faqs.update', $faq->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label>Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" class="form-control"
                            value="{{ old('question', $faq->question) }}" required>
                        @error('question')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group mt-3">
                        <label>Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" class="form-control" rows="5" required>{{ old('answer', $faq->answer) }}</textarea>
                        @error('answer')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <button class="btn btn-primary mt-3">Update</button>
                    <a href="{{ route('admin.faqs.index') }}" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            @endcan
        </div>
    </div>
@endsection

@push('scripts')
<!-- Add custom JS here if needed -->
@endpush
