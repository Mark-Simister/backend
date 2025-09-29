@extends('layouts.admin.master')

@section('title', $form->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">{{ $form->name }}</h2>
    <a href="{{ route('admin.forms.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back to Forms
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($form->video_path)
    <div class="mb-4">
        <video width="500" controls>
            <source src="{{ asset($form->video_path) }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
@endif

<form action="{{ route('admin.forms.submit', $form) }}" method="POST">
    @csrf
    @foreach($form->fields as $field)
        <div class="mb-3">
            <label class="form-label">{{ $field['label'] }}</label>
            @php $name = \Str::slug($field['label'], '_'); @endphp

            @if($field['type'] === 'text')
                <input type="text" name="{{ $name }}" class="form-control" value="{{ old($name) }}" @if(!empty($field['required'])) required @endif>
            @elseif($field['type'] === 'email')
                <input type="email" name="{{ $name }}" class="form-control" value="{{ old($name) }}" @if(!empty($field['required'])) required @endif>
            @elseif($field['type'] === 'number')
                <input type="number" name="{{ $name }}" class="form-control" value="{{ old($name) }}" @if(!empty($field['required'])) required @endif>
            @elseif($field['type'] === 'textarea')
                <textarea name="{{ $name }}" class="form-control" rows="3" @if(!empty($field['required'])) required @endif>{{ old($name) }}</textarea>
            @endif
        </div>
    @endforeach

    <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Submit</button>
</form>
@endsection
