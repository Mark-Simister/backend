@extends('layouts.admin.master')
@section('title', 'Media Asset')

@section('content')
    <div class="card">
        <div class="card-body">
            <a href="{{ route('admin.media-library.index') }}" class="small">&larr; Media library</a>
            <h4 class="mb-1 mt-1">Media asset #{{ $asset->id }} <span class="badge bg-secondary">{{ $asset->kind }}</span> @if ($asset->isArchived())<span class="badge bg-dark">archived</span>@endif</h4>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <dl class="row small">
                        @if ($asset->isExternal())
                            <dt class="col-sm-4">Reference</dt><dd class="col-sm-8"><code>{{ $asset->external_ref }}</code></dd>
                            <dt class="col-sm-4">Embed URL</dt><dd class="col-sm-8"><code>{{ $asset->embedUrl() ?? 'unavailable' }}</code></dd>
                        @else
                            <dt class="col-sm-4">File</dt><dd class="col-sm-8"><code>{{ $asset->disk }}:{{ $asset->path }}</code></dd>
                            <dt class="col-sm-4">SHA-256</dt><dd class="col-sm-8"><code>{{ $asset->sha256 }}</code></dd>
                            <dt class="col-sm-4">Type / size</dt><dd class="col-sm-8">{{ $asset->mime_type }} / {{ number_format((int) $asset->bytes) }} bytes</dd>
                            @if ($asset->width)<dt class="col-sm-4">Dimensions</dt><dd class="col-sm-8">{{ $asset->width }} x {{ $asset->height }}</dd>@endif
                            <dt class="col-sm-4">Public URL</dt><dd class="col-sm-8">{{ $asset->publicUrl() ?? 'not configured (MEDIA_URL unset)' }}</dd>
                        @endif
                        <dt class="col-sm-4">Created</dt><dd class="col-sm-8">{{ $asset->created_at }}</dd>
                        @if ($asset->isArchived())
                            <dt class="col-sm-4">Archived</dt><dd class="col-sm-8">{{ $asset->archived_at }} - {{ $asset->archive_reason }}</dd>
                        @endif
                    </dl>

                    <h6>Used by</h6>
                    <table class="table table-sm small">
                        <thead><tr><th>#</th><th>Owner</th><th>Role</th><th>State</th></tr></thead>
                        <tbody>
                            @forelse ($attachments as $attachment)
                                <tr>
                                    <td>{{ $attachment->id }}</td>
                                    <td>
                                        @if ($attachment->owner_type === 'review')
                                            @can('review.view')<a href="{{ route('admin.review-editorial.show', ['review' => $attachment->owner_key, 'tab' => 'media']) }}">review {{ $attachment->owner_key }}</a>@else review {{ $attachment->owner_key }} @endcan
                                        @else
                                            {{ $attachment->owner_type }} {{ $attachment->owner_key }}
                                        @endif
                                    </td>
                                    <td>{{ $attachment->role }}</td>
                                    <td>{{ $attachment->state }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Not attached anywhere.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    @can('media.manage')
                        @unless ($asset->isArchived())
                            <form method="POST" action="{{ route('admin.media-library.metadata', $asset->id) }}" class="border rounded p-3 mb-3">
                                @csrf
                                <h6>Descriptive metadata</h6>
                                <label class="form-label small mb-0" for="title">Title</label>
                                <input type="text" id="title" name="title" value="{{ old('title', $asset->title) }}" class="form-control form-control-sm mb-2">
                                <label class="form-label small mb-0" for="description">Description</label>
                                <textarea id="description" name="description" rows="3" class="form-control form-control-sm mb-2">{{ old('description', $asset->description) }}</textarea>
                                @if ($asset->kind === 'image')
                                    <label class="form-label small mb-0" for="alt_text">Alt text</label>
                                    <input type="text" id="alt_text" name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" class="form-control form-control-sm mb-2">
                                @endif
                                @if ($asset->kind === 'video')
                                    <label class="form-label small mb-0" for="provider_published_at">Provider upload date</label>
                                    <input type="date" id="provider_published_at" name="provider_published_at" value="{{ old('provider_published_at', $asset->provider_published_at?->format('Y-m-d')) }}" class="form-control form-control-sm mb-2">
                                    <label class="form-label small mb-0" for="duration_seconds">Duration (seconds)</label>
                                    <input type="number" id="duration_seconds" name="duration_seconds" value="{{ old('duration_seconds', $asset->duration_seconds) }}" class="form-control form-control-sm mb-2" min="0">
                                    <label class="form-label small mb-0" for="chapters">Chapters (JSON list of {"t": seconds, "label": text})</label>
                                    <textarea id="chapters" name="chapters" rows="3" class="form-control form-control-sm mb-2">{{ old('chapters', $asset->chapters ? json_encode($asset->chapters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                                @endif
                                <label class="form-label small mb-0" for="rights_note">Rights note</label>
                                <input type="text" id="rights_note" name="rights_note" value="{{ old('rights_note', $asset->rights_note) }}" class="form-control form-control-sm mb-2">
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                <p class="small text-muted mt-2 mb-0">Rendered fields of an asset captured by a released review cannot change; register a corrected asset and release again.</p>
                            </form>

                            <form method="POST" action="{{ route('admin.media-library.archive', $asset->id) }}" class="border rounded p-3">
                                @csrf
                                <h6>Archive</h6>
                                <p class="small text-muted">Refused while the asset is attached, or referenced by a Best For edition or published selection. The file and record are kept.</p>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Archive</button>
                            </form>
                        @endunless
                    @endcan

                    <h6 class="mt-3">History</h6>
                    <ul class="small">
                        @forelse ($events as $event)
                            <li>{{ $event->occurred_at }} - {{ $event->action }} - {{ $event->actor?->name ?? 'system' }} {{ $event->reason }}</li>
                        @empty
                            <li class="text-muted">No audit events.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
