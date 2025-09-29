@extends('layouts.admin.master')

@section('title', 'Form Submissions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Form Submissions</h2>
        <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Forms
        </a>
    </div>

    <!-- Form selection -->
    <div class="mb-4">
        <label class="form-label">Select Form</label>
        <select id="select-form" class="form-select">
            <option value="">-- Select a Form --</option>
            @foreach ($forms as $f)
                <option value="{{ $f->id }}" {{ isset($form) && $form->id == $f->id ? 'selected' : '' }}>
                    {{ $f->name }}
                </option>
            @endforeach
        </select>
    </div>

    @if (isset($form))
        <h4 class="mb-3">Submissions for "{{ $form->name }}"</h4>

        {{-- {{ dd($submissions) }} --}}
        @if ($submissions->count())
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="submissions-table" class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>User ID</th>
                                    @foreach ($form->fields as $field)
                                        <th>{{ $field['label'] }}</th>
                                    @endforeach
                                    <th>Submitted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submissions as $submission)
    @php
        $data = $submission->data; // already an array
    @endphp
    <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $submission->user_id ?? 'Guest' }}</td>
        @foreach($form->fields as $field)
            @php
                $fieldKey = $field['key'] ?? \Str::slug($field['label'], '_');
            @endphp
            <td>{{ $data[$fieldKey] ?? '—' }}</td>
        @endforeach
        <td>{{ $submission->created_at->format('d M Y, H:i') }}</td>
    </tr>
@endforeach


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info text-center mt-4">
                <strong>No submissions found for this form.</strong>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Redirect to selected form's submissions
            $('#select-form').change(function() {
                const formId = $(this).val();
                if (formId) {
                    window.location.href = '{{ url('admin/forms') }}/' + formId + '/submissions';
                }
            });

            // Initialize DataTable only if table exists
            if ($('#submissions-table').length) {
                $('#submissions-table').DataTable({
                    responsive: true,
                    pageLength: 10,
                    ordering: true,
                    autoWidth: false,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search submissions..."
                    }
                });
            }
        });
    </script>
@endpush
