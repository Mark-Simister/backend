@extends('layouts.admin.master')

@section('title', 'Character Roles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Character Roles</h2> 
    @can('character_role.create')
    <a href="{{ route('admin.character_roles.create') }}" class="btn btn-primary">+ Add Character Role</a> 
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($characterRoles->count())
<div class="card shadow-sm border-0">
    <div class="card-body">
        <table id="character-roles-table" class="table table-hover table-bordered align-middle"> 
            <thead class="table-light">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Name</th>
                    <th style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($characterRoles as $index => $characterRole) 
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $characterRole->name }}</td> 
                    <td>
                                @can('character_role.edit')
                                <a href="{{ route('admin.character_roles.edit', $characterRole) }}" class="btn btn-warning me-1"> 
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                                @endcan
                                @can('character_role.delete')
                                <form action="{{ route('admin.character_roles.destroy', $characterRole) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this character role?')"> 
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
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
    <div class="alert alert-info" role="alert">
        No character roles found. 
    </div>
@endif
@endsection

@push('scripts')

    <script>
        $(document).ready(function() {
            $('#character-roles-table').DataTable({
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