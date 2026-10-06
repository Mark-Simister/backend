@extends('layouts.admin.master')
@section('title', 'Review Editorial')

@section('content')
    <div class="card">
        <div class="card-body">
            <h4 class="mb-1">Review Editorial</h4>
            <p class="text-muted">The Admin key of two-key publication. Content is owned by the pipeline (n8n) and is not edited here; QA pass and Release bind to the exact content and media reviewed.</p>

            @if (! $authority->isValid())
                <div class="alert alert-danger">The video-authority configuration is invalid. Nothing can be QA-passed or released and every review fails closed until it is corrected.</div>
            @endif
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.review-editorial.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="q">Search</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="H1, product UID or review id">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="state">Editorial state</label>
                    <select id="state" name="state" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}" @selected(($filters['state'] ?? null) === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="producer_status">Producer status</label>
                    <select id="producer_status" name="producer_status" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($producerStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['producer_status'] ?? null) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="channel">Channel claim</label>
                    <select id="channel" name="channel" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel }}" @selected(($filters['channel'] ?? null) === $channel)>{{ $channel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="character">Character claim</label>
                    <select id="character" name="character" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character }}" @selected(($filters['character'] ?? null) === $character)>{{ $character }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
                <div class="col-12">
                    @foreach ($flagLabels as $key => $label)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="flags[]" value="{{ $key }}" id="flag-{{ $key }}" @checked(in_array($key, $filters['flags'], true))>
                            <label class="form-check-label small" for="flag-{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Review</th>
                            <th>Product UID</th>
                            <th>Channel / character claim</th>
                            <th>BeastieScore</th>
                            <th>Producer</th>
                            <th>Pipeline QA</th>
                            <th>Editorial state</th>
                            <th>Binding age</th>
                            <th>Flags</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php($p = $row['payload'])
                            @php($r = $row['record'])
                            <tr>
                                <td>
                                    <a href="{{ route('admin.review-editorial.show', $p->published_review_id) }}">{{ \Illuminate\Support\Str::limit($p->h1 ?: $p->published_review_id, 70) }}</a>
                                    <div class="small text-muted">{{ $p->published_review_id }}</div>
                                </td>
                                <td class="small">{{ $p->product_uid }}</td>
                                <td class="small">{{ $p->channel }} / {{ $p->character_id }}</td>
                                <td>{{ $p->final_beastie_score }}</td>
                                <td class="small">{{ $p->publish_status }}</td>
                                <td class="small">{{ $p->qa_status }}</td>
                                <td><span class="badge bg-secondary">{{ $r->editorial_state }}</span></td>
                                <td class="small">{{ $r->hasBinding() && $r->state_changed_at ? $r->state_changed_at->diffForHumans() : '-' }}</td>
                                <td class="small">
                                    @if ($p->source !== 'pipeline')<span class="badge bg-dark">legacy</span>@endif
                                    @if ($row['stale'])<span class="badge bg-warning text-dark">stale</span>@endif
                                    @if ($r->isHeld())<span class="badge bg-warning text-dark">held</span>@endif
                                    @if ($row['withdrawn'])<span class="badge bg-danger">withdrawn</span>@endif
                                    @if ($row['d3'])<span class="badge bg-danger">D3</span>@endif
                                    @if ($row['d1'])<span class="badge bg-danger">D1</span>@endif
                                    @if (! $r->associationsConfirmed())<span class="badge bg-light text-dark">no associations</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-muted">No reviews match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $payloads->links() }}
        </div>
    </div>
@endsection
