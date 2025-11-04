@extends('layouts.admin.master')

@section('title', 'Subscriptions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Subscriptions</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($subscriptions->count())
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="subscriptions-table" class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>User Email</th>
                                <th>Order ID</th>
                                <th>Plan</th>
                                <th>Period</th>
                                <th>Total</th>
                                <th>Payment Status</th>
                                <th>Subscription Status</th>
                                <th>Start</th>
                                <th>End</th>
                                {{-- <th>Action</th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscriptions as $subscription)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $subscription->user_email }}</td>
                                    <td>{{ $subscription->order_id }}</td>
                                    <td>{{ $subscription->subscription_name }}</td>
                                    <td>{{ $subscription->subscription_period }}</td>
                                    <td>{{ $subscription->total_amount }} {{ strtoupper($subscription->currency) }}</td>
                                    <td>
                                        @if ($subscription->payment_status === 'succeeded')
                                            <span class="badge bg-success">Succeeded</span>
                                        @elseif($subscription->payment_status === 'pending')
                                            <span class="badge bg-warning">Pending</span>
                                        @else
                                            <span
                                                class="badge bg-danger">{{ ucfirst($subscription->payment_status) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($subscription->subscription_status === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span
                                                class="badge bg-danger">{{ ucfirst($subscription->subscription_status) }}</span>
                                        @endif
                                    </td>
                                    {{-- <td>{{ \Carbon\Carbon::parse($subscription->subscription_start_date)->format('Y-m-d') }}
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($subscription->subscription_end_date)->format('Y-m-d') }}
                                    </td> --}}
                                    <td>{{ \Carbon\Carbon::parse($subscription->subscription_start_date)->format('d M Y') }}
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($subscription->subscription_end_date)->format('d M Y') }}
                                    </td>

                                    {{-- <td>
                                    <a href="{{ route('admin.subscriptions.show', $subscription->id) }}"
                                        class="btn btn-info btn-sm">
                                        <i class="ti-eye"></i> View
                                    </a>
                                </td> --}}

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info text-center mt-4">
            <strong>No subscriptions found.</strong>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#subscriptions-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search subscriptions..."
                }
            });
        });
    </script>
@endpush
