@extends('layouts.admin.master')
@section('title', 'Edit Highlight Tag')

@section('content')
<div class="card">
    <div class="card-body">
        <h4>Edit Highlight Tag</h4>

        <form action="{{ route('admin.highlight_tags.update', $highlightTag) }}" method="POST">
            @csrf
            @method('PUT')

            @php
                $controlledTags = [
                    ['emoji' => '🆕', 'label' => 'New Review'],
                    ['emoji' => '🔥', 'label' => 'Top Deal'],
                    ['emoji' => '💥', 'label' => 'Trending Now'],
                    ['emoji' => '🧪', 'label' => 'First Look'],
                    ['emoji' => '🎯', 'label' => 'Editor\'s Pick'],
                    ['emoji' => '😍', 'label' => 'Fan Favorite'],
                    ['emoji' => '🕒', 'label' => 'Time-Sensitive'],
                    ['emoji' => '🛍️', 'label' => 'Amazon Choice'],
                    ['emoji' => '🧠', 'label' => 'Smart Pick'],
                ];
            @endphp

            <div class="form-group mt-3">
                <label for="emoji">Select Highlight Tag <span class="text-danger">*</span></label>
                <select name="emoji" id="emoji" class="form-control" required onchange="updateLabel(this)">
                    <option value="">-- Select Tag --</option>
                    @foreach($controlledTags as $tag)
                        <option value="{{ $tag['emoji'] }}"
                            data-label="{{ $tag['label'] }}"
                            {{ old('emoji', $highlightTag->emoji) == $tag['emoji'] ? 'selected' : '' }}>
                            {{ $tag['emoji'] }} {{ $tag['label'] }}
                        </option>
                    @endforeach
                </select>
                @error('emoji')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group mt-3">
                <label for="label">Label <span class="text-danger">*</span></label>
                <input type="text" name="label" id="label" class="form-control" 
                    value="{{ old('label', $highlightTag->label) }}" required readonly>
                @error('label')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group mt-3">
                <label>
                    <input type="checkbox" name="automated" value="1" {{ old('automated', $highlightTag->automated) ? 'checked' : '' }}>
                    Automated Tag
                </label>
                @error('automated')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary mt-3">Update Highlight Tag</button>
        </form>
    </div>
</div>

<script>
    function updateLabel(select) {
        const selected = select.options[select.selectedIndex];
        const label = selected.getAttribute('data-label') || '';
        document.getElementById('label').value = label;
    }
</script>
@endsection
