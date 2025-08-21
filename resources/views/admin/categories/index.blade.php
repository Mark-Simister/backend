@extends('layouts.admin.master')

@section('title', 'Categories')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Categories</h2>
     @can('category.create')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">+ Add Category</a>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($categories->count())
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table id="categories-table" class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $index => $category)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td>
                                @can('category.edit')
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-warning me-1">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                                @endcan
                                @can('category.delete')
                                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
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
        <strong>No categories found.</strong> Start by adding a new one.
    </div>
@endif
@endsection


@push('scripts')
   
    <script>
        $(document).ready(function() {
            $('#categories-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search categories..."
                }
            });
        });
    </script>
@endpush