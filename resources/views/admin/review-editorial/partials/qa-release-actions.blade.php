{{--
    QA & Release actions. The content and media fingerprints rendered here are the values
    submitted with T1 (QA pass) and T3 (Release); the server refuses either if the current
    values differ (concurrency guard). Every action requires a reason, except an optional
    note on QA pass.
--}}
@php($rid = $payload->published_review_id)
<dl class="row small mb-3">
    <dt class="col-sm-3">Active scheme</dt><dd class="col-sm-9"><code>{{ $fingerprints['scheme'] ?? 'INVALID' }}</code></dd>
    <dt class="col-sm-3">Content fingerprint</dt><dd class="col-sm-9"><code>{{ $fingerprints['content'] ?? 'unavailable' }}</code></dd>
    <dt class="col-sm-3">Proposed media fingerprint</dt><dd class="col-sm-9"><code>{{ $fingerprints['media'] ?? 'unavailable' }}</code></dd>
    <dt class="col-sm-3">Bound (current state)</dt>
    <dd class="col-sm-9">
        @if ($record->hasBinding())
            <code>{{ $record->bound_fingerprint_scheme }}</code> / <code>{{ $record->bound_content_fingerprint }}</code> / <code>{{ $record->bound_media_fingerprint }}</code>
        @else
            none
        @endif
    </dd>
</dl>

@if ($isPipeline)
    <div class="row g-3">
        @can('review.qa')
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.review-editorial.qa-pass', $rid) }}" class="border rounded p-2">
                    @csrf
                    <input type="hidden" name="content_fingerprint" value="{{ $fingerprints['content'] }}">
                    <input type="hidden" name="media_fingerprint" value="{{ $fingerprints['media'] }}">
                    <label class="form-label small mb-0" for="qa-reason">QA pass note (optional)</label>
                    <input type="text" id="qa-reason" name="reason" class="form-control form-control-sm mb-2">
                    <button type="submit" class="btn btn-success btn-sm">Record QA pass</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.review-editorial.return', $rid) }}" class="border rounded p-2">
                    @csrf
                    <label class="form-label small mb-0" for="return-reason">Return for correction - reason</label>
                    <input type="text" id="return-reason" name="reason" class="form-control form-control-sm mb-2" required>
                    <button type="submit" class="btn btn-outline-warning btn-sm">Return for correction</button>
                </form>
            </div>
        @endcan
        @can('review.release')
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.review-editorial.release', $rid) }}" class="border rounded p-2">
                    @csrf
                    <input type="hidden" name="content_fingerprint" value="{{ $fingerprints['content'] }}">
                    <input type="hidden" name="media_fingerprint" value="{{ $fingerprints['media'] }}">
                    <label class="form-label small mb-0" for="release-reason">Release - reason</label>
                    <input type="text" id="release-reason" name="reason" class="form-control form-control-sm mb-2" required>
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($blockers !== [])>Release</button>
                </form>
            </div>
            <div class="col-md-6">
                <form method="POST" action="{{ route('admin.review-editorial.unrelease', $rid) }}" class="border rounded p-2">
                    @csrf
                    <label class="form-label small mb-0" for="unrelease-reason">Unrelease - reason</label>
                    <input type="text" id="unrelease-reason" name="reason" class="form-control form-control-sm mb-2" required>
                    <button type="submit" class="btn btn-outline-danger btn-sm" @disabled(! $record->isReleased())>Unrelease</button>
                </form>
            </div>
        @endcan
        @can('review.hold')
            <div class="col-md-6">
                @if ($record->isHeld())
                    <form method="POST" action="{{ route('admin.review-editorial.lift-hold', $rid) }}" class="border rounded p-2">
                        @csrf
                        <label class="form-label small mb-0" for="lift-reason">Lift hold - reason</label>
                        <input type="text" id="lift-reason" name="reason" class="form-control form-control-sm mb-2" required>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Lift hold</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.review-editorial.hold', $rid) }}" class="border rounded p-2">
                        @csrf
                        <label class="form-label small mb-0" for="hold-reason">Hold - reason</label>
                        <input type="text" id="hold-reason" name="reason" class="form-control form-control-sm mb-2" required>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Apply hold</button>
                    </form>
                @endif
            </div>
        @endcan
    </div>
@endif
