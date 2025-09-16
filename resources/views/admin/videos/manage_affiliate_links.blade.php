@extends('layouts.admin.master')

@section('title', 'Manage Affiliate Links')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Affiliate Links for {{ $video->title }}</h2>
        <a href="{{ route('admin.videos.index') }}" class="btn btn-secondary">Back to Videos</a>
    </div>

    @if (session('success'))
    <div id="success-message" class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div id="error-message" class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<script>
    setTimeout(function() {
        let successMessage = document.getElementById('success-message');
        let errorMessage = document.getElementById('error-message');
        
        if (successMessage) {
            successMessage.style.display = 'none'; 
        }
        
        if (errorMessage) {
            errorMessage.style.display = 'none'; 
        }
    }, 5000); 
</script>


    <h4>Add/Edit Affiliate Link</h4>
    <form id="affiliateLinkForm" 
          action="{{ isset($affiliateLink) ? route('admin.videos.update-affiliate-link', [$video->id, $affiliateLink->id]) : route('admin.videos.store-affiliate-link', $video->id) }}" 
          method="POST">

        @csrf

        @if (isset($affiliateLink))
            <input type="hidden" name="_method" value="PATCH">
        @endif

        <!-- Hidden video ID -->
        <input type="hidden" name="video_id" value="{{ $video->id }}">

        <input type="hidden" id="affiliateLinkId" name="affiliateLinkId" value="{{ $affiliateLink->id ?? '' }}"> 

        <div class="mb-3">
            <label for="region_id" class="form-label">Region</label>
            <select name="region_id" id="region_id" class="form-select" required>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" {{ isset($affiliateLink) && $affiliateLink->region_id == $region->id ? 'selected' : '' }}>
                        {{ $region->region_name }} ({{ $region->region_code }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="retailer" class="form-label">Retailer</label>
            <input type="text" name="retailer" id="retailer" class="form-control" value="{{ $affiliateLink->retailer ?? '' }}" required>
        </div>

        <div class="mb-3">
            <label for="url" class="form-label">Affiliate URL</label>
            <input type="url" name="url" id="url" class="form-control" value="{{ $affiliateLink->url ?? '' }}" required>
        </div>

        <button type="submit" class="btn btn-primary" id="submitButton">{{ isset($affiliateLink) ? 'Save Changes' : 'Add Affiliate Link' }}</button>
    </form>

    <h4 class="mt-5">Existing Affiliate Links</h4>
    <table class="table table-hover table-bordered">
        <thead>
            <tr>
                <th>Region</th>
                <th>Retailer</th>
                <th>Affiliate Link</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($affiliateLinks as $link)
                <tr>
                    <td>{{ $link->region->region_name }}</td>
                    <td>{{ $link->retailer }}</td>
                    <td><a href="{{ $link->url }}" target="_blank">{{ $link->url }}</a></td>
                    <td>
                        <button class="btn btn-warning edit-btn" data-id="{{ $link->id }}" data-region="{{ $link->region->id }}" data-retailer="{{ $link->retailer }}" data-url="{{ $link->url }}">
                            <i class="bi bi-pencil"></i> Edit
                        </button>

                        <form action="{{ route('admin.videos.affiliateLinks.destroy', ['video' => $video->id, 'affiliateLink' => $link->id]) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger delete-btn">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        document.querySelectorAll('.edit-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                const affiliateLinkId = this.getAttribute('data-id');
                const regionId = this.getAttribute('data-region');
                const retailer = this.getAttribute('data-retailer');
                const url = this.getAttribute('data-url');

                const form = document.getElementById('affiliateLinkForm');
                form.action = "{{ route('admin.videos.update-affiliate-link', [$video->id, ':affiliateLinkId']) }}".replace(':affiliateLinkId', affiliateLinkId);

                document.getElementById('affiliateLinkId').value = affiliateLinkId;

                document.getElementById('region_id').value = regionId;
                document.getElementById('retailer').value = retailer;
                document.getElementById('url').value = url;

                document.getElementById('submitButton').textContent = 'Save Changes';
            });
        });
    </script>
@endsection
