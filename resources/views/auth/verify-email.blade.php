<x-guest-layout>
    <h4 class="text-center fw-bold mb-3">Verify Your Email ✉️</h4>
    <p class="text-muted text-center mb-4">
        Thanks for signing up! Please verify your email by clicking the link we just sent. <br>
        Didn’t receive it? Click below to resend.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success text-center mb-4" role="alert">
            A new verification link has been sent to your email address.
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <!-- Resend Email Form -->
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                Resend Verification Email
            </button>
        </form>

        <!-- Logout Form -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link text-danger text-decoration-none">
                Log Out
            </button>
        </form>
    </div>
</x-guest-layout>