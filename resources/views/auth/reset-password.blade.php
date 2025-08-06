<x-guest-layout>
    <h4 class="text-center fw-bold mb-3">Reset Password 🔑</h4>
    <p class="text-muted text-center mb-4">
        Please enter your new password below. Make sure it’s strong and secure.
    </p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-control form-control-lg" 
                placeholder="Enter your email" 
                value="{{ old('email', $request->email) }}" 
                required 
                autofocus 
                autocomplete="username"
            >
            @error('email')
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control form-control-lg" 
                placeholder="Enter new password" 
                required 
                autocomplete="new-password"
            >
            @error('password')
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input 
                type="password" 
                id="password_confirmation" 
                name="password_confirmation" 
                class="form-control form-control-lg" 
                placeholder="Confirm new password" 
                required 
                autocomplete="new-password"
            >
            @error('password_confirmation')
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>

        <!-- Submit Button -->
        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-primary btn-lg">
                Reset Password
            </button>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-decoration-none text-primary">← Back to Sign In</a>
        </div>
    </form>
</x-guest-layout>