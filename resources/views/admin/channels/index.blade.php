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
                                 class="channel-img">
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
                           class="btn btn-sm btn-warning me-1" title="Edit">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        @endcan

                        @can('channel.delete')
                        <form action="{{ route('admin.channels.destroy', $channel) }}" 
                              method="POST" 
                              class="d-inline delete-channel-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete">
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
<!-- DataTables Script -->
<script>
    $(document).ready(function() {
        $('#channels-table').DataTable({
            responsive: true,
            pageLength: 10,
            ordering: true,
            autoWidth: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search channels..."
            }
        });
    });
</script>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SweetAlert Delete Confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-btn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
