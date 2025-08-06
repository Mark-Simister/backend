<x-guest-layout>
    <h4 class="text-center fw-bold mb-3">Welcome Back 👋</h4>
    <p class="text-muted text-center mb-4">Please sign in to your account</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" id="email" name="email" class="form-control form-control-lg" required autofocus>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control form-control-lg" required>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="remember" id="remember_me">
                <label class="form-check-label" for="remember_me">Keep me signed in</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-decoration-none">Forgot password?</a>
        </div>

        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
        </div>

        {{-- <p class="text-center text-muted">Don’t have an account? <a href="{{ route('register') }}" class="text-decoration-none">Create</a></p> --}}
    </form>
</x-guest-layout>