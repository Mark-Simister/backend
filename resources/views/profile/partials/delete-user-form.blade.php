<section class="mb-5">
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
        <div>
            {{ __('Once your account is deleted, all of its resources and data will be permanently removed.') }}
        </div>
    </div>

    <!-- Trigger Delete Modal -->
    <div class="text-center">
        <button type="button" class="btn btn-outline-danger px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#confirmUserDeletionModal">
            <i class="bi bi-trash3-fill me-1"></i> {{ __('Delete My Account') }}
        </button>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="confirmUserDeletionModal" tabindex="-1" aria-labelledby="confirmUserDeletionLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('profile.destroy') }}" class="modal-content border-0 shadow-sm rounded-4">
                @csrf
                @method('delete')

                <div class="modal-header bg-danger text-white rounded-top-4">
                    <h5 class="modal-title" id="confirmUserDeletionLabel">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i> {{ __('Confirm Deletion') }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-2 fw-semibold text-danger">
                        {{ __('Are you sure you want to delete your account?') }}
                    </p>
                    <p class="text-muted small">
                        {{ __('All your data will be permanently deleted. This action is irreversible.') }}
                    </p>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('Confirm Password') }}</label>
                        <input
                            type="password"
                            class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                            id="password"
                            name="password"
                            placeholder="{{ __('Enter your password') }}"
                            required
                        >
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3-fill me-1"></i> {{ __('Yes, Delete My Account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>