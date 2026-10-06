@extends('layouts.admin.master')
@section('title', 'Media Library')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4 class="mb-1">Media Library</h4>
            <p class="text-muted">Videos are registered by allow-listed provider reference ({{ implode(', ', $providers) }}) and embedded from a server-built URL; images and audio are stored on the media disk. Nothing is ever deleted - assets are archived.</p>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif

            @can('media.manage')
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <form method="POST" action="{{ route('admin.media-library.external') }}" class="border rounded p-3 h-100">
                            @csrf
                            <h6>Register an external video</h6>
                            <input type="text" name="reference" value="{{ old('reference') }}" class="form-control form-control-sm mb-2" placeholder="vimeo:123456789 or an https Vimeo / YouTube URL" required>
                            <input type="text" name="title" value="{{ old('title') }}" class="form-control form-control-sm mb-2" placeholder="Title">
                            <textarea name="description" class="form-control form-control-sm mb-2" rows="2" placeholder="Description">{{ old('description') }}</textarea>
                            <div class="row g-2 mb-2">
                                <div class="col"><input type="date" name="provider_published_at" value="{{ old('provider_published_at') }}" class="form-control form-control-sm" title="Provider upload date"></div>
                                <div class="col"><input type="number" name="duration_seconds" value="{{ old('duration_seconds') }}" class="form-control form-control-sm" placeholder="Duration (s)" min="0"></div>
                            </div>
                            <input type="text" name="rights_note" value="{{ old('rights_note') }}" class="form-control form-control-sm mb-2" placeholder="Rights note">
                            <button type="submit" class="btn btn-primary btn-sm">Register</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" action="{{ route('admin.media-library.upload') }}" enctype="multipart/form-data" class="border rounded p-3 h-100">
                            @csrf
                            <h6>Upload an image or audio file</h6>
                            <select name="kind" class="form-select form-select-sm mb-2" required>
                                @foreach ($managed as $kind => $spec)
                                    <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $kind }} ({{ implode(', ', $spec['mimes']) }}, up to {{ intdiv($spec['max_kb'], 1024) }} MB)</option>
                                @endforeach
                            </select>
                            <input type="file" name="file" class="form-control form-control-sm mb-2" required>
                            <input type="text" name="title" value="{{ old('title') }}" class="form-control form-control-sm mb-2" placeholder="Title">
                            <input type="text" name="alt_text" value="{{ old('alt_text') }}" class="form-control form-control-sm mb-2" placeholder="Alt text (required before an image can go public)">
                            <input type="text" name="rights_note" value="{{ old('rights_note') }}" class="form-control form-control-sm mb-2" placeholder="Rights note">
                            <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                        </form>
                    </div>
                </div>
            @endcan

            <form method="GET" action="{{ route('admin.media-library.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Title, reference or path"></div>
                <div class="col-md-3">
                    <select name="kind" class="form-select form-select-sm">
                        <option value="">Any kind</option>
                        @foreach (['video', 'image', 'audio'] as $kind)
                            <option value="{{ $kind }}" @selected(($filters['kind'] ?? null) === $kind)>{{ $kind }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="archived" class="form-select form-select-sm">
                        <option value="0" @selected(($filters['archived'] ?? '0') === '0')>Current</option>
                        <option value="1" @selected(($filters['archived'] ?? '0') === '1')>Archived</option>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button></div>
            </form>

            <table class="table table-sm table-hover small">
                <thead><tr><th>#</th><th>Kind</th><th>Reference / file</th><th>Title</th><th>Alt text</th><th>In use</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($assets as $asset)
                        <tr>
                            <td><a href="{{ route('admin.media-library.show', $asset->id) }}">{{ $asset->id }}</a></td>
                            <td>{{ $asset->kind }}</td>
                            <td>{{ $asset->external_ref ?? $asset->path }}</td>
                            <td>{{ $asset->title }}</td>
                            <td>{{ $asset->kind === 'image' ? ($asset->alt_text ? 'yes' : 'missing') : '-' }}</td>
                            <td>{{ $asset->live_attachments_count }}</td>
                            <td>{{ $asset->created_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No media.</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{ $assets->links() }}
        </div>
    </div>
@endsection
