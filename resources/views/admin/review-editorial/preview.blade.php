@extends('layouts.admin.master')
@section('title', 'Review Preview')

@section('content')
    @php($rid = $payload->published_review_id)
    <div class="card">
        <div class="card-body">
            <a href="{{ route('admin.review-editorial.show', ['review' => $rid, 'tab' => 'qa']) }}" class="small">&larr; Workspace</a>
            <h4 class="mb-1 mt-1">Preview: {{ $payload->h1 ?: $rid }}</h4>
            <p class="small text-muted">Authenticated Admin preview of the stored payload through the public review template. Not public, not indexed, never cached. This is not a statement of public eligibility.</p>

            @include('admin.review-editorial.partials.banners')

            <div class="row">
                <div class="col-lg-9">
                    <iframe title="Review preview" src="{{ route('admin.review-editorial.preview.frame', ['review' => $rid, 'fp' => $fingerprints['content']]) }}" style="width:100%;height:80vh;border:1px solid #ddd;border-radius:6px;"></iframe>
                </div>
                <div class="col-lg-3">
                    <h6>Proposed media</h6>
                    <p class="small text-muted">Pending media is shown here; it is not part of the rendered page{{ $authority->mode === 'payload' ? ' (Mode P: renders only after cutover)' : '' }}.</p>
                    <ul class="small">
                        @foreach ($mediaRoles as $role => $spec)
                            @php($p = $proposed->get($role))
                            <li>
                                <strong>{{ $role }}</strong>:
                                @if ($p)
                                    #{{ $p->media_asset_id }} {{ $p->asset->title ?? $p->asset->external_ref ?? $p->asset->path }} ({{ $p->state }})
                                    @if ($p->asset->embedUrl())<div><code>{{ $p->asset->embedUrl() }}</code></div>@endif
                                @else
                                    none
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <h6>QA &amp; Release</h6>
                    @include('admin.review-editorial.partials.qa-release-actions')
                </div>
            </div>
        </div>
    </div>
@endsection
