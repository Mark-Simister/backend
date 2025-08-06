<x-guest-layout>
    <h4 class="text-center fw-bold mb-3">Forgot Password? 🔐</h4>
    <p class="text-muted text-center mb-4">No worries! Enter your email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="alert alert-success text-center">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-control form-control-lg" 
                placeholder="Enter your email" 
                value="{{ old('email') }}" 
                required 
                autofocus
            >
            @error('email')
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>

        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-decoration-none text-primary">← Back to Sign In</a>
        </div>
    </form>
</x-guest-layout>