@extends('layouts.admin.master')

@section('title', 'Edit Similar Products')

@section('content')
    <div class="row">
        <div class="col-12">

            {{-- Region selection + Add button --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="w-50">
                    <label for="region_id" class="form-label">Select Region</label>
                    <select name="region_id" id="region_id" class="form-control">
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}"
                                {{ ($selectedRegionId ?? 0) == $region->id ? 'selected' : '' }}>
                                {{ $region->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary mt-4" id="addSimilarProduct">+ Add New Product</button>
                </div>
            </div>

            {{-- Existing products table --}}
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Existing Similar Products</h5>
                    <table class="table table-bordered" id="existingProductsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Short Description</th>
                                <th>URL</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- JS will populate --}}
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Edit/Add Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="editProductForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="edit_product_id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Similar Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" id="edit_product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Short Description</label>
                            <input type="text" id="edit_product_description" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>URL</label>
                            <input type="text" id="edit_product_url" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Image</label>
                            <input type="file" id="edit_product_image" name="image" class="form-control"
                                accept="image/*">
                            <small class="form-text text-muted">Leave empty to keep existing image</small>
                            <div id="edit_product_image_preview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
   <script>
document.addEventListener('DOMContentLoaded', function() {
    const regionSelect = document.getElementById('region_id');
    const videoId = "{{ $video->id }}";
    const tableBody = document.querySelector('#existingProductsTable tbody');
    const imagePreview = document.getElementById('edit_product_image_preview');

    function fetchSimilarProducts(regionId) {
        fetch(`/admin/similar-products/${videoId}/region/${regionId}`)
            .then(res => res.json())
            .then(data => {
                tableBody.innerHTML = '';

                if (!data.length) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        `<td colspan="6" class="text-center">No similar products for this region.</td>`;
                    tableBody.appendChild(tr);
                    return;
                }

                data.forEach((p, i) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${i + 1}</td>
                        <td>${p.name || ''}</td>
                        <td>${p.short_description || ''}</td>
                        <td>${p.url ? `<a href="${p.url}" target="_blank">${p.url}</a>` : ''}</td>
                        <td>${p.image ? `<img src="${p.image}" style="max-height:50px;">` : ''}</td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-btn" 
                                data-id="${p.id}" data-name="${p.name}" data-description="${p.short_description}" 
                                data-url="${p.url}" data-image="${p.image}">Edit</button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${p.id}">Delete</button>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });

                // Edit buttons
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        document.getElementById('edit_product_id').value = id;
                        document.getElementById('edit_product_name').value = this.dataset.name;
                        document.getElementById('edit_product_description').value = this.dataset.description;
                        document.getElementById('edit_product_url').value = this.dataset.url;

                        // Show preview of existing image
                        imagePreview.innerHTML = this.dataset.image 
                            ? `<img src="${this.dataset.image}" style="max-height:100px;">` 
                            : '';
                        
                        // Clear file input
                        document.getElementById('edit_product_image').value = '';

                        new bootstrap.Modal(document.getElementById('editProductModal')).show();
                    });
                });

                // Delete buttons
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this product?')) return;
                        const id = this.dataset.id;
                        fetch(`/admin/similar-products/${id}/delete`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => fetchSimilarProducts(regionSelect.value));
                    });
                });
            });
    }

    // Initial load
    fetchSimilarProducts(regionSelect.value);

    // On region change
    regionSelect.addEventListener('change', function() {
        fetchSimilarProducts(this.value);
    });

    // Add New Product button
    document.getElementById('addSimilarProduct').addEventListener('click', function() {
        document.getElementById('edit_product_id').value = ''; // new product
        document.getElementById('edit_product_name').value = '';
        document.getElementById('edit_product_description').value = '';
        document.getElementById('edit_product_url').value = '';
        document.getElementById('edit_product_image').value = '';
        imagePreview.innerHTML = '';

        new bootstrap.Modal(document.getElementById('editProductModal')).show();
    });

    // Submit modal (Add/Edit)
    document.getElementById('editProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('edit_product_id').value;

        const formData = new FormData();
        formData.append('name', document.getElementById('edit_product_name').value);
        formData.append('short_description', document.getElementById('edit_product_description').value);
        formData.append('url', document.getElementById('edit_product_url').value);
        formData.append('region_id', regionSelect.value);
        formData.append('_token', '{{ csrf_token() }}');

        const fileInput = document.getElementById('edit_product_image');
        if (fileInput.files.length > 0) {
            formData.append('image', fileInput.files[0]);
        }

        const url = id
            ? `/admin/similar-products/${id}/update`
            : `/admin/similar-products/${videoId}/create`;

        fetch(url, {
            method: 'POST',
            body: formData
        }).then(res => res.json())
          .then(() => {
              bootstrap.Modal.getInstance(document.getElementById('editProductModal')).hide();
              fetchSimilarProducts(regionSelect.value);
          });
    });
});
</script>

@endpush
