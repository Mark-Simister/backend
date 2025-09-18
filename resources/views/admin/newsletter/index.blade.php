@extends('layouts.admin.master')

@section('title', 'Newsletter Subscriptions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Newsletter Subscriptions</h2>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($emails->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table id="newsletter-subscriptions-table" class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Email</th>
                        <th>Subscribed At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($emails as $email)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $email->email }}</td>
                        <td>{{ $email->created_at }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<div class="alert alert-info text-center mt-4">
    <strong>No newsletter subscriptions found.</strong> Start by having users subscribe.
</div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#newsletter-subscriptions-table').DataTable({
        responsive: true,
        pageLength: 10,
        ordering: true,
        autoWidth: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search emails..."
        }
    });
});
</script>
@endpush
