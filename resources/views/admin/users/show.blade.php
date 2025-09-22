@extends('layouts.admin.master')

@section('title', 'User Details')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">User Details</h2>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                <i class="ti-arrow-left"></i> Back to List
            </a>
        </div>

        {{-- User Info --}}
        <div class="card shadow-sm border rounded-3 bg-light mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">User Information</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-3">ID</dt>
                    <dd class="col-sm-9">{{ $user->id }}</dd>

                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">{{ $user->name ?? '—' }}</dd>

                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9">{{ $user->email ?? '—' }}</dd>

                    <dt class="col-sm-3">Phone</dt>
                    <dd class="col-sm-9">{{ $user->phone ?? '—' }}</dd>

                    <dt class="col-sm-3">Verified</dt>
                    <dd class="col-sm-9">
                        @if ($user->is_verified)
                            <span class="badge bg-success">Yes</span>
                        @else
                            <span class="badge bg-danger">No</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Roles</dt>
                    <dd class="col-sm-9">
                        @forelse($user->getRoleNames() as $r)
                            <span class="badge bg-secondary">{{ $r }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>

                    <dt class="col-sm-3">Created At</dt>
                    <dd class="col-sm-9">{{ $user->created_at?->format('d M, Y H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="row">
            {{-- Subscriptions --}}
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm border rounded-3 bg-light h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Subscriptions</h5>

                        @if ($user->subscriptions_api->isEmpty())
                            <p class="text-muted mb-0">No subscriptions found.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Plan</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Start</th>
                                            <th>End</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($user->subscriptions_api as $s)
                                            @php($plan = $s->listing)
                                            <tr>
                                                <td>{{ $s->order_id ?? '—' }}</td>

                                                {{-- From subscription_listing --}}
                                                <td>{{ $plan->subscription_name ?? '—' }}</td>
                                                <td>
                                                    @if (isset($plan->duration, $plan->duration_unit))
                                                        {{ $plan->duration }}
                                                        {{ \Illuminate\Support\Str::plural($plan->duration_unit, (int) $plan->duration) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>

                                                {{-- Existing subscription fields --}}
                                                <td>
                                                    @if ($s->subscription_status === 'active')
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span
                                                            class="badge bg-danger">{{ ucfirst($s->subscription_status ?? '—') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $s->subscription_start_date?->format('d M, Y') ?? '—' }}</td>
                                                <td>{{ $s->subscription_end_date?->format('d M, Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        @endif
                    </div>
                </div>
            </div>

            {{-- Reviews --}}
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm border rounded-3 bg-light h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Reviews</h5>

                        @if ($user->reviews_api->isEmpty())
                            <p class="text-muted mb-0">No reviews found.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Video</th>
                                            <th>Rating</th>
                                            <th>Review</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($user->reviews_api as $r)
                                            <tr>
                                                <td>{{ $r->video->title ?? '—' }}</td>
                                                <td>{{ $r->rating ?? '—' }}</td>
                                                <td>{{ $r->review ?? '—' }}</td>
                                                <td>{{ ucfirst($r->status ?? '—') }}</td>
                                                <td>{{ $r->created_at?->format('d M, Y H:i') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
