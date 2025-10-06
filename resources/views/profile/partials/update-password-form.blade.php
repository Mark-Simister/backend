<section class="mb-5">
    <div class="mb-3">
        <p class="text-muted small">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" id="password-update-form">
        @csrf
        @method('put')

        <!-- Current Password -->
        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">{{ __('Current Password') }}</label>
            <input
                type="password"
                class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                id="update_password_current_password"
                name="current_password"
                autocomplete="current-password"
                required
            >
            @error('current_password', 'updatePassword')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <!-- New Password -->
        <div class="mb-3">
            <label for="update_password_password" class="form-label">{{ __('New Password') }}</label>
            <input
                type="password"
                class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                id="update_password_password"
                name="password"
                autocomplete="new-password"
                required
            >
            @error('password', 'updatePassword')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="update_password_password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input
                type="password"
                class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                id="update_password_password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                required
            >
            @error('password_confirmation', 'updatePassword')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <!-- Save Button & Flash Message -->
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>

            @if (session('status') === 'password-updated')
                <span class="text-success small ms-3">
                    {{ __('Saved.') }}
                </span>
            @endif
        </div>
    </form>

    <script>
        // Add event listener to the form submit event
        document.getElementById('password-update-form').addEventListener('submit', function(event) {
            const password = document.getElementById('update_password_password').value;
            const confirmPassword = document.getElementById('update_password_password_confirmation').value;

            // Check if the password or confirmation contains spaces
            if (/\s/.test(password)) {
                event.preventDefault();  // Prevent form submission
                alert("Password should not contain any spaces.");
            } else if (/\s/.test(confirmPassword)) {
                event.preventDefault();  // Prevent form submission
                alert("Confirm Password should not contain any spaces.");
            }
        });
    </script>
</section>
