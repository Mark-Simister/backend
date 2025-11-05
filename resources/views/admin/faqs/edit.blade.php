@extends('layouts.admin.master')
@section('title', 'Edit FAQ')

@push('styles')
<style>
/* Editor height */
.ck-editor__editable_inline {
    min-height: 300px;
}
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
                        <!-- remove native `required` to avoid focus error with hidden textarea -->
                        <textarea id="answer" name="answer" class="form-control" rows="5">{{ old('answer', $faq->answer) }}</textarea>
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
<!-- CKEditor 5 Classic CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.3.1/classic/ckeditor.js"></script>
<script>
    let editorInstance;

    ClassicEditor
        .create(document.querySelector('#answer'), {
            toolbar: [
                'undo','redo','|','heading','|','bold','italic','link',
                'bulletedList','numberedList','blockQuote','insertTable','mediaEmbed'
            ],
        })
        .then(editor => {
            editorInstance = editor;
            // ensure height
            editor.ui.view.editable.element.style.minHeight = '300px';
        })
        .catch(error => console.error(error));

    // keep textarea in sync for submit (helps with old() + validation)
    document.querySelector('form').addEventListener('submit', function () {
        if (editorInstance) {
            document.querySelector('#answer').value = editorInstance.getData();
        }
    });
</script>
@endpush
