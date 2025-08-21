@extends('layouts.admin.master')

@push('styles')
<style>
 /*  */
</style>
@endpush

@section('title', 'Channels')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Channels</h2>
    @can('channel.create')
    <a href="{{ route('admin.channels.create') }}" class="btn btn-primary">+ Add Channel</a>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($channels->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="channels-table" class="table table-hover table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Created</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($channels as $channel)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $channel->name }}</td>
                        <td>{{ $channel->category->name ?? 'N/A' }}</td>
                        <td>{{ $channel->created_at->diffForHumans() }}</td>
                        <td>
                            @can('channel.edit')
                            <a href="{{ route('admin.channels.edit', $channel) }}" class="btn btn-warning me-1">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                            @endcan
                            @can('channel.delete')
                                <form action="{{ route('admin.channels.destroy', $channel) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this channel?')">
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
        <strong>No channels found.</strong> Start by adding a new one.
    </div>
@endif
@endsection


@push('scripts')
    

    <script>
        $(document).ready(function() {
            $('#channels-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
               // order: [[ 0, 'desc' ]], // Latest on top based on row index
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search channels..."
                }
            });
        });
    </script>
@endpush