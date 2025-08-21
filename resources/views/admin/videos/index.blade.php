@extends('layouts.admin.master')

@section('title', 'Videos')

@push('styles')
<style>
   /*  */
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Videos</h2>
    @can('video.create')
    <a href="{{ route('admin.videos.create') }}" class="btn btn-primary">+ Add Video</a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if($videos->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="videos-table" class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Character</th>
                        <th>Channel</th>
                        <th>Access</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($videos as $index => $video)
                    <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $video->title }}</td>
                            <td>{{ ucfirst($video->type) }}</td>
                            <td>{{ $video->character->name ?? '-' }}</td>
                            <td>{{ $video->channel->name ?? '-' }}</td>
                            <td>{{ ucfirst($video->access_level) }}</td>
                            <td>
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
@else
    <div class="alert alert-info text-center mt-4">
        <strong>No videos found.</strong> Start by adding a new one.
    </div>
@endif
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#videos-table').DataTable({
            responsive: true,
            pageLength: 10,
            ordering: true,
            //order: [[0, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search videos..."
            }
        });
    });
</script>
@endpush