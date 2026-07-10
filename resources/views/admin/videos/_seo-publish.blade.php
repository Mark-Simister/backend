@php
    /** @var \App\Models\Video $video */
    $payload    = $video->seoPayload;
    $gateErrors = app(\App\Services\ReviewPayloadPublisher::class)->gateErrors($video);
    $state      = $video->seo_publish_status ?: 'unpublished';
    $isLive     = $payload && $payload->publish_status === 'published';
    $everPublished = filled($video->seo_published_at);

    $badge = match ($state) {
        'published' => 'success',
        'withdrawn' => 'secondary',
        'error'     => 'danger',
        default     => 'light text-dark',
    };
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y H:i') : '—';
@endphp

<div class="card mt-4">
    <div class="card-body">
        <h5 class="mb-1">Public SEO review page</h5>
        <p class="text-muted small mb-3">
            Publishing is an explicit sign-off. A video reaching <em>published</em> status does
            <strong>not</strong> create a public page on its own. Once published, edits to the review
            content, scores or regions refresh the page automatically.
        </p>

        {{-- Flash + action errors --}}
        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @error('seo')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        {{-- Current state --}}
        <div class="d-flex flex-wrap align-items-center mb-3" style="gap:.5rem">
            <span class="badge bg-{{ $badge }}">{{ str_replace('_', ' ', $state) }}</span>
            @if ($payload?->content_tier)
                <span class="badge bg-info text-dark" title="How much genuine review content backs this page">
                    {{ $payload->content_tier }} content
                </span>
            @endif
            @if ($isLive && $video->review_slug)
                <a href="{{ url('/review/' . $video->review_slug) }}" target="_blank" rel="noopener" class="small">
                    View live page ↗
                </a>
            @endif
        </div>

        <dl class="row small mb-3">
            <dt class="col-sm-3">First published</dt>
            <dd class="col-sm-9">{{ $fmt($video->seo_published_at) }}</dd>

            <dt class="col-sm-3">Last refreshed</dt>
            <dd class="col-sm-9">{{ $fmt($video->seo_last_published_at) }}</dd>

            <dt class="col-sm-3">Public URL</dt>
            <dd class="col-sm-9">
                @if ($video->review_slug)
                    <code>/review/{{ $video->review_slug }}</code>
                    <span class="text-muted">(fixed once published — never changes)</span>
                @else
                    <span class="text-muted">assigned on first publish</span>
                @endif
            </dd>

            <dt class="col-sm-3">Regions</dt>
            <dd class="col-sm-9">
                @php $codes = $video->regions->pluck('region_code')->all(); @endphp
                {{ $codes ? implode(', ', $codes) : '—' }}
                <span class="text-muted">(the page only resolves on these regional hosts)</span>
            </dd>
        </dl>

        {{-- Last failure, if any --}}
        @if ($state === 'error' && $video->seo_publish_error)
            <div class="alert alert-danger py-2 small">
                <strong>Last publish failed:</strong> {{ $video->seo_publish_error }}
                @if ($isLive)
                    <div class="mt-1 text-muted">The previously published page is still live and unchanged.</div>
                @endif
            </div>
        @endif

        {{-- Why it can't be published yet --}}
        @if ($gateErrors)
            <div class="alert alert-warning py-2 small mb-3">
                <strong>Not ready to publish:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($gateErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Actions --}}
        <div class="d-flex" style="gap:.5rem">
            <form method="POST" action="{{ route('admin.videos.seo-publish', $video) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" @disabled($gateErrors)>
                    {{ $everPublished ? 'Re-publish to SEO' : 'Publish to SEO' }}
                </button>
            </form>

            @if ($isLive)
                <form method="POST" action="{{ route('admin.videos.seo-withdraw', $video) }}"
                      onsubmit="return confirm('Withdraw this review from the public site? The page will return 404.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Withdraw from SEO</button>
                </form>
            @endif
        </div>
    </div>
</div>
