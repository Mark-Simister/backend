@extends('layouts.admin.master')
@section('title', 'Review Workspace')

@section('content')
    @php($rid = $payload->published_review_id)
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <a href="{{ route('admin.review-editorial.index') }}" class="small">&larr; Review queue</a>
                    <h4 class="mb-0 mt-1">{{ $payload->h1 ?: $rid }}</h4>
                    <div class="small text-muted">{{ $rid }} &middot; /review/{{ $payload->review_slug }} &middot; source {{ $payload->source }}</div>
                </div>
                <div class="text-end">
                    <span class="badge bg-secondary fs-6">{{ $record->editorial_state }}</span>
                    <div class="mt-2"><a class="btn btn-outline-primary btn-sm" href="{{ route('admin.review-editorial.preview', $rid) }}">Preview</a></div>
                </div>
            </div>

            @include('admin.review-editorial.partials.banners')

            <ul class="nav nav-tabs mb-3">
                @foreach (['overview' => 'Overview', 'associations' => 'Associations', 'media' => 'Media', 'qa' => 'QA & Release', 'disposition' => 'Disposition', 'history' => 'History'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.review-editorial.show', ['review' => $rid, 'tab' => $key]) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            @if ($tab === 'overview')
                <div class="row">
                    <div class="col-md-6">
                        <h6>Pipeline summary (read-only, n8n-owned)</h6>
                        <dl class="row small">
                            <dt class="col-sm-5">Producer status</dt><dd class="col-sm-7">{{ $payload->publish_status }}</dd>
                            <dt class="col-sm-5">Pipeline QA status</dt><dd class="col-sm-7">{{ $payload->qa_status }}</dd>
                            <dt class="col-sm-5">Product UID</dt><dd class="col-sm-7">{{ $payload->product_uid }}</dd>
                            <dt class="col-sm-5">Channel / character claim</dt><dd class="col-sm-7">{{ $payload->channel }} / {{ $payload->character_id }} ({{ $payload->character_name }})</dd>
                            <dt class="col-sm-5">BeastieScore</dt><dd class="col-sm-7">{{ $payload->final_beastie_score }}</dd>
                            <dt class="col-sm-5">Last import</dt><dd class="col-sm-7">{{ $payload->updated_at }}</dd>
                            <dt class="col-sm-5">Stored content fingerprint</dt><dd class="col-sm-7"><code>{{ $payload->content_fingerprint ?? 'none' }}</code> {{ $payload->content_fingerprint_scheme }}</dd>
                            <dt class="col-sm-5">Safety feed</dt><dd class="col-sm-7">{{ data_get($payload->review_page_json, 'safety.safety_feed_status', 'n/a') }} / {{ data_get($payload->review_page_json, 'safety.safety_feed_freshness_status', 'n/a') }}</dd>
                        </dl>
                        <h6>Pipeline QA errors</h6>
                        <pre class="small bg-light p-2">{{ json_encode($payload->qa_errors_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        <h6>Pipeline QA warnings</h6>
                        <pre class="small bg-light p-2">{{ json_encode($payload->qa_warnings_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Public-release verifier (diagnostic only - not wired to public routes in Sprint A)</h6>
                        <ul class="small">
                            @foreach ($verification as $region => $ok)
                                <li>{{ $region }}: {!! $ok ? '<span class="text-success">verifies</span>' : '<span class="text-muted">not eligible</span>' !!}</li>
                            @endforeach
                        </ul>
                        @if ($isPipeline)
                            @can('review.edit')
                                <form method="POST" action="{{ route('admin.review-editorial.notes', $rid) }}">
                                    @csrf
                                    <label class="form-label small mb-0" for="internal_notes">Internal notes (never public)</label>
                                    <textarea id="internal_notes" name="internal_notes" rows="5" class="form-control form-control-sm mb-2">{{ $record->internal_notes }}</textarea>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save notes</button>
                                </form>
                            @else
                                <h6>Internal notes</h6>
                                <p class="small">{{ $record->internal_notes ?: '-' }}</p>
                            @endcan
                        @endif
                        <details class="mt-3">
                            <summary class="small">Raw stored payload (read-only)</summary>
                            <pre class="small bg-light p-2" style="max-height:480px;overflow:auto">{{ json_encode($payload->review_page_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    </div>
                </div>
            @endif

            @if ($tab === 'associations')
                <div class="row">
                    <div class="col-md-6">
                        <h6>Associations</h6>
                        <dl class="row small">
                            <dt class="col-sm-4">Channel</dt>
                            <dd class="col-sm-8">claim <code>{{ $payload->channel ?? 'none' }}</code> &rarr;
                                {!! $resolvedChannel ? e($resolvedChannel->name) . ' <span class="text-success">(pipeline_code match)</span>' : '<span class="text-danger">no exact pipeline_code match</span>' !!}</dd>
                            <dt class="col-sm-4">Character</dt>
                            <dd class="col-sm-8">claim <code>{{ $payload->character_id ?? 'none' }}</code> &rarr;
                                {!! $resolvedCharacter ? e($resolvedCharacter->name) . ' <span class="text-success">(pipeline_code match)</span>' : '<span class="text-danger">no exact pipeline_code match</span>' !!}</dd>
                            <dt class="col-sm-4">Confirmed</dt>
                            <dd class="col-sm-8">{{ $record->associationsConfirmed() ? 'yes, ' . $record->associations_confirmed_at : 'no' }}</dd>
                        </dl>
                        @if ($isPipeline)
                            @can('review.edit')
                                <form method="POST" action="{{ route('admin.review-editorial.associations', $rid) }}">
                                    @csrf
                                    <label class="form-label small mb-0" for="category_id">Category</label>
                                    <select id="category_id" name="category_id" class="form-select form-select-sm mb-2" required>
                                        <option value="">Select a category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((int) $suggestedCategoryId === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm" @disabled(! $resolvedChannel || ! $resolvedCharacter)>Confirm associations</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6>Region eligibility</h6>
                        @if ($selectedRegionIds === [])
                            <div class="alert alert-info small">No explicit region rows: eligible for the review page (all regions), not for Best For.</div>
                        @endif
                        @if ($isPipeline)
                            @can('review.release')
                                <form method="POST" action="{{ route('admin.review-editorial.regions', $rid) }}">
                                    @csrf
                                    @foreach ($regions as $region)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="region_ids[]" value="{{ $region->id }}" id="region-{{ $region->id }}" @checked(in_array($region->id, $selectedRegionIds, true))>
                                            <label class="form-check-label small" for="region-{{ $region->id }}">{{ $region->region_code }} - {{ $region->region_name }}</label>
                                        </div>
                                    @endforeach
                                    <label class="form-label small mb-0 mt-2" for="regions-reason">Reason</label>
                                    <input type="text" id="regions-reason" name="reason" class="form-control form-control-sm mb-2" required>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Save regions</button>
                                </form>
                            @else
                                <ul class="small">
                                    @foreach ($regions->whereIn('id', $selectedRegionIds) as $region)
                                        <li>{{ $region->region_code }}</li>
                                    @endforeach
                                </ul>
                            @endcan
                        @endif
                    </div>
                </div>
            @endif

            @if ($tab === 'media')
                @if ($authority->mode === 'payload')
                    <div class="alert alert-info small">Mode P (payload video authority): review media is recorded, QA-bound and captured at Release, but renders only after cutover.</div>
                @endif
                <table class="table table-sm small">
                    <thead><tr><th>Role</th><th>Proposed (bound by QA)</th><th>Active (captured at Release)</th></tr></thead>
                    <tbody>
                        @foreach ($mediaRoles as $role => $spec)
                            <tr>
                                <td>{{ $role }} <span class="text-muted">({{ implode(', ', $spec['kinds']) }})</span></td>
                                <td>@if ($p = $proposed->get($role))#{{ $p->media_asset_id }} {{ $p->asset->title ?? $p->asset->external_ref ?? $p->asset->path }} <span class="badge bg-light text-dark">{{ $p->state }}</span>@else - @endif</td>
                                <td>@if ($a = $active->get($role))#{{ $a->media_asset_id }} {{ $a->asset->title ?? $a->asset->external_ref ?? $a->asset->path }}@else - @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h6>All attachments</h6>
                <table class="table table-sm small">
                    <thead><tr><th>#</th><th>Role</th><th>Asset</th><th>State</th><th>Activated</th><th>Retired</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($attachments as $attachment)
                            <tr>
                                <td>{{ $attachment->id }}</td>
                                <td>{{ $attachment->role }}</td>
                                <td>@can('media.view')<a href="{{ route('admin.media-library.show', $attachment->media_asset_id) }}">#{{ $attachment->media_asset_id }}</a>@else #{{ $attachment->media_asset_id }} @endcan {{ $attachment->asset->kind }}</td>
                                <td>{{ $attachment->state }}</td>
                                <td>{{ $attachment->activated_at }}</td>
                                <td>{{ $attachment->retired_at }} {{ $attachment->retire_reason }}</td>
                                <td>
                                    @if ($isPipeline && $attachment->state === 'pending')
                                        @can('review.edit')
                                            <form method="POST" action="{{ route('admin.review-editorial.media.discard', [$rid, $attachment->id]) }}" class="d-flex gap-1">
                                                @csrf
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason" required>
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Discard</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No media attached.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($isPipeline)
                    @can('review.edit')
                        <form method="POST" action="{{ route('admin.review-editorial.media.stage', $rid) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="role">Role</label>
                                <select id="role" name="role" class="form-select form-select-sm" required>
                                    @foreach ($mediaRoles as $role => $spec)
                                        <option value="{{ $role }}">{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="media_asset_id">Asset (from the media library)</label>
                                <select id="media_asset_id" name="media_asset_id" class="form-select form-select-sm" required>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}">#{{ $asset->id }} {{ $asset->kind }} - {{ $asset->title ?? $asset->external_ref ?? $asset->path }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm">Stage</button>
                            </div>
                        </form>
                        <p class="small text-muted mt-2">Staging changes the proposed media set: any QA pass or release bound to the previous set becomes stale and needs a fresh QA pass and Release. Released media is replaced only by staging and releasing again.</p>
                    @endcan
                @endif
            @endif

            @if ($tab === 'qa')
                <h6>Release checklist</h6>
                @if ($blockers === [])
                    <div class="alert alert-success small">Every Release precondition is met.</div>
                @else
                    <ul class="small text-danger">
                        @foreach ($blockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                @endif
                @include('admin.review-editorial.partials.qa-release-actions')
            @endif

            @if ($tab === 'disposition')
                <dl class="row small">
                    <dt class="col-sm-3">Withdrawn</dt><dd class="col-sm-9">{{ $disposition?->withdrawn_at ?? 'no' }}</dd>
                    <dt class="col-sm-3">Class</dt><dd class="col-sm-9">{{ $disposition?->withdrawal_class ?? '-' }} {{ $disposition?->classification_confirmed ? '(confirmed)' : '' }}</dd>
                    <dt class="col-sm-3">Effective treatment</dt><dd class="col-sm-9">{{ $disposition?->effectiveWithdrawalClass() ?? '-' }}</dd>
                    <dt class="col-sm-3">Validity</dt><dd class="col-sm-9">{{ $disposition?->validity_state ?? 'valid' }}</dd>
                    <dt class="col-sm-3">Availability</dt><dd class="col-sm-9">{{ $disposition?->availability_state ?? 'available' }}</dd>
                </dl>
                @can('review.withdraw')
                    @php($classes = \App\Http\Controllers\Admin\ReviewEditorialController::withdrawalClasses())
                    <div class="row g-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('admin.review-editorial.disposition.withdraw', $rid) }}" class="border rounded p-2">
                                @csrf
                                <label class="form-label small mb-0" for="proposed_class">Withdraw - proposed class (unconfirmed)</label>
                                <select id="proposed_class" name="proposed_class" class="form-select form-select-sm mb-1">
                                    <option value="">None</option>
                                    @foreach ($classes as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Withdraw</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('admin.review-editorial.disposition.classify', $rid) }}" class="border rounded p-2">
                                @csrf
                                <label class="form-label small mb-0" for="class">Confirm withdrawal class</label>
                                <select id="class" name="class" class="form-select form-select-sm mb-1" required>
                                    @foreach ($classes as $class)<option value="{{ $class }}">{{ $class }}</option>@endforeach
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Confirm class</button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="POST" action="{{ route('admin.review-editorial.disposition.validity', $rid) }}" class="border rounded p-2">
                                @csrf
                                <label class="form-label small mb-0" for="validity_state">Validity</label>
                                <select id="validity_state" name="validity_state" class="form-select form-select-sm mb-1">
                                    <option value="valid">valid</option>
                                    <option value="materially_invalid">materially_invalid</option>
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Set validity</button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="POST" action="{{ route('admin.review-editorial.disposition.availability', $rid) }}" class="border rounded p-2">
                                @csrf
                                <label class="form-label small mb-0" for="availability_state">Availability (transient; never demotes)</label>
                                <select id="availability_state" name="availability_state" class="form-select form-select-sm mb-1">
                                    <option value="available">available</option>
                                    <option value="temporarily_unavailable">temporarily_unavailable</option>
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Set availability</button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="POST" action="{{ route('admin.review-editorial.disposition.reinstate', $rid) }}" class="border rounded p-2">
                                @csrf
                                <label class="form-label small mb-0">Reinstate (clears the withdrawal; a fresh Release is required)</label>
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Reason" required>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Reinstate</button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

            @if ($tab === 'history')
                <table class="table table-sm small">
                    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>From &rarr; To</th><th>Reason</th><th>Content fingerprint</th></tr></thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>{{ $event->occurred_at }}</td>
                                <td>{{ $event->actor?->name ?? 'system' }}</td>
                                <td>{{ $event->action }}</td>
                                <td>{{ $event->from_state }} &rarr; {{ $event->to_state }}</td>
                                <td>{{ $event->reason }}</td>
                                <td><code>{{ $event->content_fingerprint ? substr($event->content_fingerprint, 0, 12) . '...' : '' }}</code></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No audit events.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
