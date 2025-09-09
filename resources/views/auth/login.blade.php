<x-guest-layout>
    <h4 class="text-center fw-bold mb-3">
        Welcome Back <i class="bi bi-hand-thumbs-up"></i>
    </h4>
    <p class="text-muted text-center mb-4">Please sign in to your account</p>

    {{-- Show success or error messages --}}
    @if (session('status'))
        <div class="alert alert-success text-center">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger text-center">
            {{ session('error') }}
        </div>
    @endif

    {{-- Show validation errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

       <div class="mb-3">
    <label for="email" class="form-label">Email address</label>
    <input type="email" id="email" name="email"
           class="form-control form-control-lg @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

    <div class="mb-3">
    <label for="password" class="form-label">Password</label>
    <div class="input-group">
        <input type="password" id="password" name="password" class="form-control form-control-lg @error('password') is-invalid @enderror" required>
        <button class="btn btn-outline-secondary" type="button" id="togglePassword">👁️</button>
        @error('password')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
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

    <!-- Password toggle script -->
    
    <script>
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("password");
    const toggleBtn = document.getElementById("togglePassword");

    toggleBtn.addEventListener("click", function () {
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            toggleBtn.textContent = "👁️‍🗨"; // eye with slash
        } else {
            passwordInput.type = "password";
            toggleBtn.textContent = "👁️"; // normal eye
        }
    });
});
</script>
</x-guest-layout>
