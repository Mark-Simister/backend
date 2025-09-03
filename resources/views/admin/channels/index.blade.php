@extends('layouts.admin.master')

@push('styles')
<style>
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 8px;
        padding: 6px 10px;
        border: 1px solid #ddd;
    }
    .channel-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
</style>
@endpush

@section('title', 'Channels')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Channels</h2>
    @can('channel.create')
        <a href="{{ route('admin.channels.create') }}" class="btn btn-primary">
            + Add Channel
        </a>
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
        <table id="channels-table" class="table table-hover table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Regions</th>
                    <th>Created</th>
                    <th style="width: 160px;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($channels as $channel)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        @if($channel->image && file_exists(public_path($channel->image)))
                            <img src="{{ asset($channel->image) }}" 
                                alt="{{ $channel->name }}" 
                                class="channel-img" 
                                width="80">
                        @else
                            <span class="text-muted">No Image</span>
                        @endif
                    </td>
                    <td>{{ $channel->name }}</td>
                   <td>
                        @foreach($channel->regions as $region)
                            <span class="badge bg-info">{{ $region->region_name }}</span>
                        @endforeach
                    </td>
                    <td>{{ $channel->created_at->format('d M, Y') }}</td>
                    <td class="text-center">
                        @can('channel.edit')
                        <a href="{{ route('admin.channels.edit', $channel) }}" 
                           class="btn btn-sm btn-warning me-1">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        @endcan
                        @can('channel.delete')
                        <form action="{{ route('admin.channels.destroy', $channel) }}" 
                              method="POST" 
                              class="d-inline" 
                              onsubmit="return confirm('Are you sure you want to delete this channel?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
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
           // order: [[ 4, 'desc' ]], // Order by Created date DESC
            autoWidth: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search channels..."
            }
        });
    });
</script>
@endpush