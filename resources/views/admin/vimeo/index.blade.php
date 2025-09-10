@extends('layouts.admin.master')

@section('title', 'Vimeo')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Vimeo</h2>
        <form method="GET" action="{{ route('admin.vimeo.index') }}">
            <button class="btn btn-outline-secondary">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (!empty($assigned) || !empty($unassigned))
    <ul class="nav nav-pills mb-3" id="vimeoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="unassigned-tab" data-bs-toggle="tab" data-bs-target="#unassigned-pane" type="button" role="tab" aria-controls="unassigned-pane" aria-selected="true">
                Unassigned <span class="badge bg-secondary ms-1">{{ count($unassigned) }}</span>
            </button>
        </li>
        <li class="nav-item ms-2" role="presentation">
            <button class="nav-link" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned-pane" type="button" role="tab" aria-controls="assigned-pane" aria-selected="false">
                Assigned <span class="badge bg-success ms-1">{{ count($assigned) }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="vimeoTabsContent">
        {{-- Unassigned --}}
        <div class="tab-pane fade show active" id="unassigned-pane" role="tabpanel" aria-labelledby="unassigned-tab" tabindex="0">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="vimeo-unassigned-table" class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 80px;">Thumb</th>
                                    <th>Title</th>
                                    <th>Vimeo URL</th>
                                    <th style="width: 100px;">Duration</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 120px;">Privacy</th>
                                    <th style="width: 220px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unassigned as $v)
                                    @php
                                        $thumb = collect($v['pictures']['sizes'] ?? [])->pluck('link')->last();
                                        $dur = isset($v['duration']) ? gmdate('i:s', (int) $v['duration']) : '—';
                                        $created = $v['created_time'] ?? null;
                                        $privacy = data_get($v, 'privacy.view', '—');
                                        $link = $v['link'] ?? '';

                                        $videoId = null;
                                        if (!empty($v['uri']) && preg_match('/\/videos\/(\d+)/', $v['uri'], $m)) {
                                            $videoId = $m[1];
                                        } elseif (!empty($link) && preg_match('/vimeo\.com\/(\d+)/', $link, $m)) {
                                            $videoId = $m[1];
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if ($thumb)
                                                <img src="{{ $thumb }}" alt="thumb" class="rounded" style="width:64px;height:36px;object-fit:cover;">
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">
                                            {{ $v['name'] ?? 'Untitled' }}
                                            <div class="small text-muted text-truncate" style="max-width: 360px;">
                                                {{ $v['description'] ?? '' }}
                                            </div>
                                            <div class="small text-muted">{{ $v['uri'] ?? '' }}</div>
                                        </td>
                                        <td>
                                            @if ($link)
                                                <a href="{{ $link }}" target="_blank" rel="noopener">{{ $link }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $dur }}</td>
                                        <td>
                                            @if ($created)
                                                {{ \Carbon\Carbon::parse($created)->format('d M Y') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $privacy }}</td>
                                        <td class="text-center">
                                            @if ($videoId)
                                                <button type="button" class="btn btn-success btn-sm me-1 play-vimeo"
                                                    data-bs-toggle="modal" data-bs-target="#vimeoPlayerModal"
                                                    data-vimeo-id="{{ $videoId }}"
                                                    data-title="{{ $v['name'] ?? 'Vimeo Video' }}">
                                                    <i class="bi bi-play-circle"></i> Play
                                                </button>
                                            @endif

                                            <a href="{{ route('admin.videos.create', ['type' => 'vimeo', 'video_url' => $link]) }}"
                                                class="btn btn-primary btn-sm">
                                                <i class="bi bi-plus-lg"></i> Assign
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Showing {{ count($unassigned) }} unassigned Vimeo videos.
                </div>
            </div>
        </div>

        {{-- Assigned --}}
        {{-- <div class="tab-pane fade" id="assigned-pane" role="tabpanel" aria-labelledby="assigned-tab" tabindex="0">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="vimeo-assigned-table" class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 80px;">Thumb</th>
                                    <th>Title</th>
                                    <th>Vimeo URL</th>
                                    <th style="width: 100px;">Duration</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 120px;">Privacy</th>
                                    <th style="width: 220px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assigned as $v)
                                    @php
                                        $thumb = collect($v['pictures']['sizes'] ?? [])->pluck('link')->last();
                                        $dur = isset($v['duration']) ? gmdate('i:s', (int) $v['duration']) : '—';
                                        $created = $v['created_time'] ?? null;
                                        $privacy = data_get($v, 'privacy.view', '—');
                                        $link = $v['link'] ?? '';

                                        $videoId = null;
                                        if (!empty($v['uri']) && preg_match('/\/videos\/(\d+)/', $v['uri'], $m)) {
                                            $videoId = $m[1];
                                        } elseif (!empty($link) && preg_match('/vimeo\.com\/(\d+)/', $link, $m)) {
                                            $videoId = $m[1];
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if ($thumb)
                                                <img src="{{ $thumb }}" alt="thumb" class="rounded" style="width:64px;height:36px;object-fit:cover;">
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">
                                            {{ $v['name'] ?? 'Untitled' }}
                                            <div class="small text-muted text-truncate" style="max-width: 360px;">
                                                {{ $v['description'] ?? '' }}
                                            </div>
                                            <div class="small text-muted">{{ $v['uri'] ?? '' }}</div>
                                        </td>
                                        <td>
                                            @if ($link)
                                                <a href="{{ $link }}" target="_blank" rel="noopener">{{ $link }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $dur }}</td>
                                        <td>
                                            @if ($created)
                                                {{ \Carbon\Carbon::parse($created)->format('d M Y') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $privacy }}</td>
                                        <td class="text-center">
                                            @if ($videoId)
                                                <button type="button" class="btn btn-success btn-sm me-1 play-vimeo"
                                                    data-bs-toggle="modal" data-bs-target="#vimeoPlayerModal"
                                                    data-vimeo-id="{{ $videoId }}"
                                                    data-title="{{ $v['name'] ?? 'Vimeo Video' }}">
                                                    <i class="bi bi-play-circle"></i> Play
                                                </button>
                                            @endif

                                            <button class="btn btn-outline-secondary btn-sm" disabled>
                                                <i class="bi bi-check2"></i> Assigned
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Showing {{ count($assigned) }} assigned Vimeo videos.
                </div>
            </div>
        </div> --}}
        {{-- REPLACE your current Assigned tab pane with this block (keeps DB-style table AND adds Vimeo fields table) --}}
<div class="tab-pane fade" id="assigned-pane" role="tabpanel" aria-labelledby="assigned-tab" tabindex="0">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="vimeo-assigned-table" class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Character</th>
                            <th>Access</th>
                            <th>Edit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignedVideos as $index => $video)
                            @php
                                // Match Vimeo details by video_url
                                $vimeo = isset($vimeoByUrl[$video->video_url]) ? $vimeoByUrl[$video->video_url] : null;

                                $link    = $vimeo['link'] ?? $video->video_url;
                                $thumb   = collect(data_get($vimeo, 'pictures.sizes', []))->pluck('link')->last();
                                $dur     = isset($vimeo['duration']) ? gmdate('i:s', (int)$vimeo['duration']) : '—';
                                $created = data_get($vimeo, 'created_time');
                                $privacy = data_get($vimeo, 'privacy.view', '—');

                                // Extract Vimeo ID for Play button
                                $videoId = null;
                                if (!empty(data_get($vimeo, 'uri')) && preg_match('/\/videos\/(\d+)/', data_get($vimeo, 'uri'), $m)) {
                                    $videoId = $m[1];
                                } elseif (!empty($link) && preg_match('/vimeo\.com\/(\d+)/', $link, $m)) {
                                    $videoId = $m[1];
                                }
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="fw-semibold">
                                    {{ $video->title }}
                                    {{-- Vimeo details inline (no duplicate second table) --}}
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        @if($thumb)
                                            <img src="{{ $thumb }}" alt="thumb" class="rounded" style="width:64px;height:36px;object-fit:cover;">
                                        @endif
                                        <div class="small text-muted">
                                            @if($link)
                                                <div>Vimeo: <a href="{{ $link }}" target="_blank" rel="noopener">{{ $link }}</a></div>
                                            @endif
                                            <div>
                                                <span>{{ $dur }}</span>
                                                @if($created) • <span>{{ \Carbon\Carbon::parse($created)->format('d M Y') }}</span>@endif
                                                • <span>{{ $privacy }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ ucfirst($video->type) }}</td>
                                <td>{{ $video->character->name ?? '-' }}</td>
                                <td>{{ ucfirst($video->access_level) }}</td>
                                <td>
                                    @can('video.edit')
                                    <a href="{{ route('admin.videos.edit.seo', $video) }}" class="btn btn-outline-primary me-1">
                                        SEO Fields
                                    </a>
                                    <a href="{{ route('admin.videos.edit.product', $video) }}" class="btn btn-outline-success me-1">
                                        Product Fields
                                    </a>
                                    @endcan
                                </td>
                                <td>
                                    {{-- Optional Play button (uses Vimeo ID if available) --}}
                                    @if ($videoId)
                                        <button type="button" class="btn btn-success me-1 play-vimeo"
                                            data-bs-toggle="modal" data-bs-target="#vimeoPlayerModal"
                                            data-vimeo-id="{{ $videoId }}"
                                            data-title="{{ $video->title }}">
                                            <i class="bi bi-play-circle"></i> Play
                                        </button>
                                    @endif

                                    @can('video.edit')
                                    <a href="{{ route('admin.videos.edit', $video) }}" class="btn btn-warning me-1">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    @endcan
                                    @can('video.delete')
                                    <form action="{{ route('admin.videos.destroy', $video) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this video?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted small">
            Showing {{ count($assignedVideos) }} assigned Vimeo videos.
        </div>
    </div>
</div>


    </div>
@else
    <div class="alert alert-info text-center mt-4">
        <strong>No Vimeo videos found.</strong>
    </div>
@endif


    {{-- Vimeo Player Modal --}}
    <div class="modal fade" id="vimeoPlayerModal" tabindex="-1" aria-labelledby="vimeoPlayerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="vimeoPlayerModalLabel" class="modal-title">Vimeo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="ratio ratio-16x9">
                        <iframe id="vimeoFrame" src="" frameborder="0"
                            allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="Vimeo player"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        // Initialize Unassigned table (Vimeo details)
        const unTbl = $('#vimeo-unassigned-table').length
            ? $('#vimeo-unassigned-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: { search: "_INPUT_", searchPlaceholder: "Search unassigned..." },
                columnDefs: [{ orderable: false, targets: [1, 7] }] // Thumb + Actions
            })
            : null;

        // Initialize Assigned table (DB-style like admin.videos.index)
        const asnTbl = $('#vimeo-assigned-table').length
            ? $('#vimeo-assigned-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: { search: "_INPUT_", searchPlaceholder: "Search assigned..." },
                columnDefs: [{ orderable: false, targets: [5, 6] }] // Edit + Actions
            })
            : null;

        // Initialize Assigned (Vimeo details) table
        const asnVimeoTbl = $('#vimeo-assigned-vimeo-table').length
            ? $('#vimeo-assigned-vimeo-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: { search: "_INPUT_", searchPlaceholder: "Search assigned (Vimeo)..." },
                columnDefs: [{ orderable: false, targets: [1, 7] }] // Thumb + Actions
            })
            : null;

        // Recalculate columns when switching tabs (Bootstrap tab shown event)
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () {
                // small delay ensures tab pane is visible before recalculating
                setTimeout(function () {
                    if (unTbl) unTbl.columns.adjust().responsive.recalc();
                    if (asnTbl) asnTbl.columns.adjust().responsive.recalc();
                    if (asnVimeoTbl) asnVimeoTbl.columns.adjust().responsive.recalc();
                }, 50);
            });
        });

        // Vimeo modal logic
        const modalEl = document.getElementById('vimeoPlayerModal');
        if (modalEl) {
            const iframe = document.getElementById('vimeoFrame');
            const titleEl = document.getElementById('vimeoPlayerModalLabel');

            modalEl.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                const id = btn?.getAttribute('data-vimeo-id');
                const title = btn?.getAttribute('data-title') || 'Vimeo Video';
                if (titleEl) titleEl.textContent = title;
                if (id && iframe) {
                    iframe.src = `https://player.vimeo.com/video/${id}?autoplay=1&title=0&byline=0&portrait=0`;
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                if (iframe) iframe.src = '';
            });
        }
    });
</script>

@endpush
