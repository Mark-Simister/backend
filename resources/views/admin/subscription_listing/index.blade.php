@extends('layouts.admin.master')

@section('title', 'Subscription Listings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Subscription Listings</h2>
    @can('subscription_list.create')
    <a href="{{ route('admin.subscription_listing.create') }}" class="btn btn-primary">+ Add Subscription</a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    
    @if($subscriptionListings->count())
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table id="subscription-table" class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Subscription Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Duration Unit</th>
                        <th>Type</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subscriptionListings as $index => $subscription)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                            <td>{{ $subscription->subscription_name }}</td>
                            <td>{{ $subscription->sub_description }}</td>
                            <td>${{ number_format($subscription->price, 2) }}</td>
                            <td>{{ $subscription->duration }}</td>
                            <td>{{ ucfirst($subscription->duration_unit) }}</td>
                            <td>{{ ucfirst($subscription->type) }}</td>
                            <td>
                                @can('subscription_list.edit')
                                <a href="{{ route('admin.subscription_listing.edit', $subscription) }}" class="btn btn-warning me-1">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                                @endcan
                                @can('subscription_list.create')
                                <form action="{{ route('admin.subscription_listing.destroy', $subscription) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this subscription?')">
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
@else
    <div class="alert alert-info text-center mt-4">
        <strong>No subscriptions found.</strong> Start by adding a new one.
    </div>
@endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#subscription-table').DataTable({
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
