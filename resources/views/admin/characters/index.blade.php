@extends('layouts.admin.master')

@push('styles')
    <style>
       /*  */
    </style>
@endpush

@section('title', 'Characters')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Characters</h2>
        @can('character.create')
        <a href="{{ route('admin.characters.create') }}" class="btn btn-primary">+ Add Character</a>
        @endcan
    </div>
    
    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    
    @if ($characters->count())
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table id="characters-table" class="table table-hover table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Persona</th>
                        {{-- <th>Channel</th>
                        <th>Mini Bio</th>
                        <th>Location</th>
                            <th>Age</th>
                            <th>Species</th>
                            <th>Style/Vibe</th>
                            <th>Durability Score</th>
                            <th>Durability Notes</th>
                            <th>Comfort Score</th>
                            <th>Comfort Notes</th>
                            <th>Style Score</th>
                            <th>Style Notes</th>
                            <th>Affordability Score</th>
                            <th>Affordability Notes</th>
                            <th>Tech Feature Score</th>
                            <th>Tech Feature Notes</th>
                            <th>Eco-friendliness Score</th>
                            <th>Eco-friendliness Notes</th>
                            <th>Engagement Score</th>
                            <th>Engagement Notes</th>
                            <th>Ease of Use Score</th>
                            <th>Ease of Use Notes</th>
                            <th>Performance Score</th>
                            <th>Performance Notes</th>
                            <th>Brand Reputation Score</th>
                            <th>Brand Reputation Notes</th> --}}
                            <th>Image</th>
                            <th style="width: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($characters as $index => $character)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $character->name }}</td>
                            <td>{{ Str::limit($character->persona, 50) }}</td>
                            {{-- <td>{{ Str::limit($character->details, 50) }}</td>
                            <td>{{ $character->channel->name ?? 'N/A' }}</td>
                            <td>{{ $character->location ?? 'N/A' }}</td>
                            <td>{{ $character->age ?? 'N/A' }}</td>
                                <td>{{ $character->species ?? 'N/A' }}</td>
                                <td>{{ $character->style_vibe ?? 'N/A' }}</td>
                                <td>{{ $character->durability_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->durability_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->comfort_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->comfort_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->style_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->style_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->affordability_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->affordability_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->tech_feature_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->tech_feature_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->eco_friendliness_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->eco_friendliness_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->engagement_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->engagement_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->ease_of_use_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->ease_of_use_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->performance_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->performance_notes ?? 'N/A', 50) }}</td>
                                <td>{{ $character->brand_reputation_score ?? 'N/A' }}</td>
                                <td>{{ Str::limit($character->brand_reputation_notes ?? 'N/A', 50) }}</td> --}}
                                <td><img src="{{ asset($character->image) }}" alt="Current Character Image"
                                    style="max-width: 200px; height: auto;"></td>
                                    <td>
                                        @can('character.edit')
                                        <a href="{{ route('admin.characters.edit', $character) }}"
                                        class="btn btn-warning me-1">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    @endcan
                                    @can('character.delete')
                                    <form action="{{ route('admin.characters.destroy', $character) }}" method="POST"
                                        class="d-inline" onsubmit="return confirm('Delete this character?')">
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
            <strong>No characters found.</strong> Start by adding a new one.
        </div>
    @endif
@endsection

@push('scripts')
    
    

    <script>
        $(document).ready(function() {
            $('#characters-table').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                // order: [
                //     [0, 'desc']
                // ], // Latest entries on top
                autoWidth: false,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search characters..."
                }
            });
        });
    </script>
@endpush
