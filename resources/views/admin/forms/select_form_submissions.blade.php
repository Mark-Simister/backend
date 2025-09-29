@extends('layouts.admin.master')

@section('title', 'Form Submissions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Select Form to View Submissions</h2>
    <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Forms
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Select a Form</label>
            <select id="form-select" class="form-select">
                <option value="">-- Choose Form --</option>
                @foreach($forms as $form)
                    <option value="{{ route('admin.forms.submissions', $form->id) }}">{{ $form->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<script>
document.getElementById('form-select').addEventListener('change', function() {
    if (this.value) {
        window.location.href = this.value;
    }
});
</script>
@endsection
