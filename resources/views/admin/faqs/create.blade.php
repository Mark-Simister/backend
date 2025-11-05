@extends('layouts.admin.master')
@section('title', 'Add FAQ')

@push('styles')
<style>
.ck-editor__editable_inline {
    min-height: 300px;
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
                        <textarea id="answer" name="answer" class="form-control" rows="5">{{ old('answer') }}</textarea>
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
<!--  CKEditor 5 Classic CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.3.1/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#answer'), {
            toolbar: [
                'undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 
                'blockQuote', 'insertTable', 'mediaEmbed'
            ],
        })
        .then(editor => {
            // set height
            editor.ui.view.editable.element.style.height = '300px';
        })
        .catch(error => {
            console.error(error);
        });
</script>
@endpush
