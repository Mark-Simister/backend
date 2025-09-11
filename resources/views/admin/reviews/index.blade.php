@extends('layouts.admin.master')

@push('styles')
    <style>
       /* custom styles (optional) */
       .badge-status { font-size: 0.85rem; }
       .nav-tabs .nav-link { font-weight: 600; }
       .tab-pane .table { margin-top: 12px; }
    </style>
@endpush

@section('title', 'Reviews')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Reviews</h2>
        @can('rating_review.create')
        <a href="{{ route('admin.reviews.create') }}" class="btn btn-primary">+ Add Review</a>
        @endcan
    </div>
    
    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @php
        $allReviews    = collect($reviews instanceof \Illuminate\Contracts\Pagination\Paginator ? $reviews->items() : $reviews);
        $pending       = $allReviews->where('status','pending');
        $approved      = $allReviews->where('status','approved');
        $rejected      = $allReviews->where('status','rejected');

        $statusColor = [
            'pending'  => 'warning',
            'approved' => 'success',
            'rejected' => 'secondary',
        ];
    @endphp

    @if ($allReviews->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                {{-- Tabs --}}
                <ul class="nav nav-tabs" id="reviewsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-pane" type="button" role="tab" aria-controls="all-pane" aria-selected="true">
                            All <span class="badge bg-dark ms-2">{{ $allReviews->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab" aria-controls="pending-pane" aria-selected="false">
                            Pending <span class="badge bg-warning text-dark ms-2">{{ $pending->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-pane" type="button" role="tab" aria-controls="approved-pane" aria-selected="false">
                            Approved <span class="badge bg-success ms-2">{{ $approved->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected-pane" type="button" role="tab" aria-controls="rejected-pane" aria-selected="false">
                            Rejected <span class="badge bg-secondary ms-2">{{ $rejected->count() }}</span>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="reviewsTabsContent">
                    {{-- ALL --}}
                    <div class="tab-pane fade show active" id="all-pane" role="tabpanel" aria-labelledby="all-tab" tabindex="0">
                        <table id="reviews-all" class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Video</th>
                                    <th>User</th>
                                    <th style="width: 90px;">Rating</th>
                                    <th>Review</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($allReviews as $index => $review)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        {{ $review->video->title ?? 'Video #'.$review->video_id }}
                                        <div class="text-muted small">#{{ $review->video_id }}</div>
                                    </td>
                                    <td>
                                        {{ $review->user->name ?? 'User #'.$review->user_id }}
                                        @if(optional($review->user)->email)
                                        <div class="text-muted small">{{ $review->user->email }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $review->rating }}</strong>
                                        <div class="text-muted small">/ 5</div>
                                    </td>
                                    <td>{{ Str::limit($review->review, 120) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusColor[$review->status] ?? 'light' }} badge-status">
                                            {{ ucfirst($review->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $review->created_at?->format('Y-m-d H:i') }}
                                        <div class="text-muted small">{{ $review->created_at?->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        @can('rating_review.edit')
                                        <a href="{{ route('admin.reviews.edit', $review) }}" class="btn btn-warning btn-sm me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        @endcan
                                        @if ($review->status !== 'approved')
                                        @can('rating_review.approve')
                                        <form action="{{ route('admin.reviews.approve', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-success btn-sm me-1">
                                                <i class="bi bi-check2-circle"></i> Approve
                                            </button>
                                        </form>
                                        @endcan
                                        @endif
                                        @if ($review->status !== 'rejected')
                                        @can('rating_review.reject')
                                        <form action="{{ route('admin.reviews.reject', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-secondary btn-sm me-1">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                        @endcan
                                        @endif
                                        @can('rating_review.delete')
                                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">
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

                    {{-- PENDING --}}
                    <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                        <table id="reviews-pending" class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Video</th>
                                    <th>User</th>
                                    <th style="width: 90px;">Rating</th>
                                    <th>Review</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pending as $index => $review)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            {{ $review->video->title ?? 'Video #'.$review->video_id }}
                                            <div class="text-muted small">#{{ $review->video_id }}</div>
                                        </td>
                                        <td>
                                            {{ $review->user->name ?? 'User #'.$review->user_id }}
                                            @if(optional($review->user)->email)
                                            <div class="text-muted small">{{ $review->user->email }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ $review->rating }}</strong>
                                            <div class="text-muted small">/ 5</div>
                                        </td>
                                        <td>{{ Str::limit($review->review, 120) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $statusColor[$review->status] ?? 'light' }} badge-status">
                                                {{ ucfirst($review->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $review->created_at?->format('Y-m-d H:i') }}
                                            <div class="text-muted small">{{ $review->created_at?->diffForHumans() }}</div>
                                        </td>
                                        <td>
                                            @can('rating_review.edit')
                                            <a href="{{ route('admin.reviews.edit', $review) }}" class="btn btn-warning btn-sm me-1">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            @endcan
                                            @can('rating_review.approve')
                                            <form action="{{ route('admin.reviews.approve', $review) }}" method="POST" class="d-inline review-action">
                                                @csrf @method('PATCH')
                                                <button class="btn btn-success btn-sm me-1">
                                                    <i class="bi bi-check2-circle"></i> Approve
                                                </button>
                                            </form>
                                            @endcan
                                            @can('rating_review.reject')
                                            <form action="{{ route('admin.reviews.reject', $review) }}" method="POST" class="d-inline review-action">
                                                @csrf @method('PATCH')
                                                <button class="btn btn-secondary btn-sm me-1">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                            @endcan
                                            @can('rating_review.delete')
                                            <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="d-inline review-action">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-danger btn-sm">
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

                    {{-- APPROVED --}}
                    <div class="tab-pane fade" id="approved-pane" role="tabpanel" aria-labelledby="approved-tab" tabindex="0">
                        <table id="reviews-approved" class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Video</th>
                                    <th>User</th>
                                    <th style="width: 90px;">Rating</th>
                                    <th>Review</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($approved as $index => $review)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        {{ $review->video->title ?? 'Video #'.$review->video_id }}
                                        <div class="text-muted small">#{{ $review->video_id }}</div>
                                    </td>
                                    <td>
                                        {{ $review->user->name ?? 'User #'.$review->user_id }}
                                        @if(optional($review->user)->email)
                                            <div class="text-muted small">{{ $review->user->email }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $review->rating }}</strong>
                                        <div class="text-muted small">/ 5</div>
                                    </td>
                                    <td>{{ Str::limit($review->review, 120) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusColor[$review->status] ?? 'light' }} badge-status">
                                            {{ ucfirst($review->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $review->created_at?->format('Y-m-d H:i') }}
                                        <div class="text-muted small">{{ $review->created_at?->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        @can('rating_review.edit')
                                        <a href="{{ route('admin.reviews.edit', $review) }}" class="btn btn-warning btn-sm me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        @endcan
                                        @can('rating_review.reject')
                                        <form action="{{ route('admin.reviews.reject', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-secondary btn-sm me-1">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                        @endcan
                                        @can('rating_review.delete')
                                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">
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

                    {{-- REJECTED --}}
                    <div class="tab-pane fade" id="rejected-pane" role="tabpanel" aria-labelledby="rejected-tab" tabindex="0">
                        <table id="reviews-rejected" class="table table-hover table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Video</th>
                                    <th>User</th>
                                    <th style="width: 90px;">Rating</th>
                                    <th>Review</th>
                                    <th style="width: 120px;">Status</th>
                                    <th style="width: 140px;">Created</th>
                                    <th style="width: 260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rejected as $index => $review)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        {{ $review->video->title ?? 'Video #'.$review->video_id }}
                                        <div class="text-muted small">#{{ $review->video_id }}</div>
                                    </td>
                                    <td>
                                        {{ $review->user->name ?? 'User #'.$review->user_id }}
                                        @if(optional($review->user)->email)
                                        <div class="text-muted small">{{ $review->user->email }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $review->rating }}</strong>
                                        <div class="text-muted small">/ 5</div>
                                    </td>
                                    <td>{{ Str::limit($review->review, 120) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusColor[$review->status] ?? 'light' }} badge-status">
                                            {{ ucfirst($review->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $review->created_at?->format('Y-m-d H:i') }}
                                        <div class="text-muted small">{{ $review->created_at?->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        @can('rating_review.edit')
                                        <a href="{{ route('admin.reviews.edit', $review) }}" class="btn btn-warning btn-sm me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        @endcan
                                        @can('rating_review.approve')
                                        <form action="{{ route('admin.reviews.approve', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-success btn-sm me-1">
                                                <i class="bi bi-check2-circle"></i> Approve
                                            </button>
                                        </form>
                                        @endcan
                                        @can('rating_review.delete')
                                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" class="d-inline review-action">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">
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

                </div> {{-- /.tab-content --}}
            </div>
        </div>
    @else
        <div class="alert alert-info text-center mt-4">
            <strong>No reviews found.</strong>
        </div>
    @endif
@endsection

@push('scripts')
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    const dtOptions = {
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search reviews..."
        },
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    };

    $('#reviews-all').DataTable(dtOptions);
    $('#reviews-pending').DataTable(dtOptions);
    $('#reviews-approved').DataTable(dtOptions);
    $('#reviews-rejected').DataTable(dtOptions);

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // SweetAlert for actions
    $('.review-action').on('submit', function(e){
        e.preventDefault();
        const form = this;
        let actionText = $(form).find('button').text().trim();

        Swal.fire({
            title: `Are you sure you want to ${actionText.toLowerCase()} this review?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if(result.isConfirmed){
                form.submit();
            }
        });
    });
});
</script>
@endpush
