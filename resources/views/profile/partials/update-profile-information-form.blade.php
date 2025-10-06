<section class="container py-4">
    <div class="mb-3">
        <p class="text-muted">{{ __("Update your account's profile information and email address.") }}</p>
    </div>

    <!-- Email Verification Resend Form -->
    <form id="send-verification" method="POST" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <!-- Profile Update Form -->
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <!-- Name Field -->
        <div class="mb-3">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input
                type="text"
                class="form-control @error('name') is-invalid @enderror"
                id="name"
                name="name"
                value="{{ old('name', $user->name) }}"
                required
                autofocus
                autocomplete="name"
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Email Field -->
        {{-- <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <!-- Email Verification Prompt -->
            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="form-text mt-2 text-warning">
                    {{ __('Your email address is unverified.') }}
                    <button
                        form="send-verification"
                        class="btn btn-link p-0 m-0 align-baseline"
                    >
                        {{ __('Click here to re-send the verification email.') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <div class="text-success mt-2">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </div>
                    @endif
                </div>
            @endif
        </div> --}}

        <!-- Save Button + Status -->
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>

            @if (session('status') === 'profile-updated')
                <span class="text-success small">
                    {{ __('Saved.') }}
                </span>
            @endif
        </div>
    </form>
</section>