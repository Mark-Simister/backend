@extends('layouts.admin.master')

@section('title', 'Product Message Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Product Message Details</h2>
        <a href="{{ route('admin.product-messages.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left-circle me-2"></i> Back to List
        </a>
    </div>

    <!-- Card for displaying product message details -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="row">
                <!-- Left Section: Name, Email, and Message -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong class="text-primary">Name:</strong>
                        <p>{{ $productMessage->name }}</p>
                    </div>

                    <div class="mb-3">
                        <strong class="text-primary">Email:</strong>
                        <p>{{ $productMessage->email }}</p>
                    </div>

                    <div class="mb-3">
                        <strong class="text-primary">Message:</strong>
                        <p>{{ $productMessage->message }}</p>
                    </div>
                </div>

                <!-- Right Section: Date and Actions (Edit/Delete) -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <strong class="text-primary">Created At:</strong>
                        <p>{{ $productMessage->created_at->format('d M Y, h:i A') }}</p>
                    </div>

                    <!-- Actions (Edit and Delete) -->
                    <div class="mb-3">
                        <strong class="text-primary">Actions:</strong>
                        <div class="d-flex">
                            
                            @can('video.delete')
                                <form action="{{ route('admin.product-messages.delete', $productMessage) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger delete-btn">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
 
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
