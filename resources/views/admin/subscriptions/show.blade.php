@extends('layouts.admin.master')

@section('title', 'Subscription Details')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Subscription Details</h2>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-outline-secondary">
            <i class="ti-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="row">
        <!-- Subscription Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border rounded-3 bg-light">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Subscription Information</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">User Email</dt>
                        <dd class="col-sm-7">{{ $subscription->user_email ?? '—' }}</dd>

                        <dt class="col-sm-5">Order ID</dt>
                        <dd class="col-sm-7">{{ $subscription->order_id ?? '—' }}</dd>

                        <dt class="col-sm-5">Plan</dt>
                        <dd class="col-sm-7">{{ $subscription->subscription_name ?? '—' }}</dd>

                        <dt class="col-sm-5">Period</dt>
                        <dd class="col-sm-7">{{ $subscription->subscription_period ?? '—' }}</dd>

                        <dt class="col-sm-5">Billing Cycle</dt>
                        <dd class="col-sm-7">{{ $subscription->billing_cycle ? ucfirst($subscription->billing_cycle) : '—' }}</dd>

                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">
                            @if($subscription->subscription_status === 'active')
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">{{ ucfirst($subscription->subscription_status ?? '—') }}</span>
                            @endif
                        </dd>

                        <dt class="col-sm-5">Start Date</dt>
                        <dd class="col-sm-7">
                            {{ $subscription->subscription_start_date ? \Carbon\Carbon::parse($subscription->subscription_start_date)->format('d M, Y') : '—' }}
                        </dd>

                        <dt class="col-sm-5">End Date</dt>
                        <dd class="col-sm-7">
                            {{ $subscription->subscription_end_date ? \Carbon\Carbon::parse($subscription->subscription_end_date)->format('d M, Y') : '—' }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border rounded-3 bg-light">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Payment Information</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Total Amount</dt>
                        <dd class="col-sm-7">
                            {{ $subscription->total_amount ? $subscription->total_amount . ' ' . strtoupper($subscription->currency) : '—' }}
                        </dd>

                        <dt class="col-sm-5">Payment Method</dt>
                        <dd class="col-sm-7">{{ $subscription->payment_method ?? '—' }}</dd>

                        <dt class="col-sm-5">Transaction ID</dt>
                        <dd class="col-sm-7">{{ $subscription->transaction_id ?? '—' }}</dd>

                        <dt class="col-sm-5">Payment Status</dt>
                        <dd class="col-sm-7">
                            @if($subscription->payment_status === 'succeeded')
                                <span class="badge bg-success">Succeeded</span>
                            @elseif($subscription->payment_status === 'pending')
                                <span class="badge bg-warning">Pending</span>
                            @else
                                <span class="badge bg-danger">{{ ucfirst($subscription->payment_status ?? '—') }}</span>
                            @endif
                        </dd>

                        <dt class="col-sm-5">Last Payment Status</dt>
                        <dd class="col-sm-7">{{ $subscription->last_payment_status ?? '—' }}</dd>

                        <dt class="col-sm-5">Last Payment At</dt>
                        <dd class="col-sm-7">
                            {{ $subscription->last_payment_at ? \Carbon\Carbon::parse($subscription->last_payment_at)->format('d M, Y H:i A') : '—' }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
