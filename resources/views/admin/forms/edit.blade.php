@extends('layouts.admin.master')

@section('title', 'Edit Form')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Edit Form</h2>
    <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Forms
    </a>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('admin.forms.update', $form) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <!-- Form Name -->
    <div class="mb-3">
        <label class="form-label">Form Name</label>
        <input type="text" name="name" class="form-control" required value="{{ old('name', $form->name) }}">
    </div>

    <!-- CTA Type -->
    <div class="mb-3">
        <label class="form-label">Form Type (CTA)</label>
        <select name="cta_type" class="form-select" required>
            <option value="apply_now" {{ old('cta_type', $form->cta_type) == 'apply_now' ? 'selected' : '' }}>Apply Now</option>
            <option value="reachout" {{ old('cta_type', $form->cta_type) == 'reachout' ? 'selected' : '' }}>Reachout</option>
        </select>
    </div>

    <!-- Upload Video -->
    <div class="mb-3">
        <label class="form-label">Upload Video</label>
        <input type="file" name="video" class="form-control" accept="video/*">
        @if($form->video_path)
            <small class="text-muted d-block mt-1">Current video:</small>
            <video width="250" controls>
                <source src="{{ asset($form->video_path) }}" type="video/mp4">
            </video>
        @endif
    </div>

    <!-- Dynamic Fields -->
    <div class="mb-4">
        <label class="form-label">Form Fields</label>
        <div id="fields-container"></div>
        <button type="button" class="btn btn-success mt-2" id="add-field-btn">
            <i class="bi bi-plus-lg"></i> Add Field
        </button>
        <small class="text-muted d-block mt-1">Click "Add Field" to dynamically add inputs. Each field only has Label, Type, and Required.</small>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Form</button>
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    let fieldIndex = 0;

    // Preload existing fields
    @if($form->fields)
        const existingFields = @json($form->fields);
        existingFields.forEach(field => {
            const fieldHtml = `
            <div class="card mb-2 p-3 field-item">
                <div class="row align-items-center">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <input type="text" class="form-control" name="fields[${fieldIndex}][label]" placeholder="Field Label" required value="${field.label}">
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <select class="form-select" name="fields[${fieldIndex}][type]" required>
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text</option>
                            <option value="email" ${field.type === 'email' ? 'selected' : ''}>Email</option>
                            <option value="number" ${field.type === 'number' ? 'selected' : ''}>Number</option>
                            <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Textarea</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 mb-md-0 text-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[${fieldIndex}][required]" value="1" ${field.required ? 'checked' : ''}>
                            <label class="form-check-label">Required</label>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-danger remove-field-btn"><i class="bi bi-trash"></i> Remove</button>
                    </div>
                </div>
            </div>
            `;
            $('#fields-container').append(fieldHtml);
            fieldIndex++;
        });
    @endif

    // Add new dynamic field
    $('#add-field-btn').click(function() {
        const fieldHtml = `
        <div class="card mb-2 p-3 field-item">
            <div class="row align-items-center">
                <div class="col-md-4 mb-2 mb-md-0">
                    <input type="text" class="form-control" name="fields[${fieldIndex}][label]" placeholder="Field Label" required>
                </div>
                <div class="col-md-4 mb-2 mb-md-0">
                    <select class="form-select" name="fields[${fieldIndex}][type]" required>
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="number">Number</option>
                        <option value="textarea">Textarea</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2 mb-md-0 text-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="fields[${fieldIndex}][required]" value="1">
                        <label class="form-check-label">Required</label>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger remove-field-btn"><i class="bi bi-trash"></i> Remove</button>
                </div>
            </div>
        </div>
        `;
        $('#fields-container').append(fieldHtml);
        fieldIndex++;
    });

    // Remove dynamic field
    $(document).on('click', '.remove-field-btn', function() {
        $(this).closest('.field-item').remove();
    });
});
</script>
@endpush
