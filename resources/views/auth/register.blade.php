<x-guest-layout>

    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0 justify-content-center">
                    <div class="auth-form-light text-start py-5 px-4 px-sm-5">

                        {{-- Flash messages --}}
                        @if (session('status'))
                            <div class="alert alert-success mb-4">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger mb-4">
                                {{ session('error') }}
                            </div>
                        @endif

                        {{-- Global validation errors --}}
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="form-heading text-center mb-4">
                            <h4>New here?</h4>
                            <h6 class="fw-light">Creating an account is quick and easy.</h6>
                        </div>

                        <form method="POST" action="{{ route('register') }}" class="pt-3">
                            @csrf

                            <!-- Name -->
                            <div class="form-group mb-3">
                                <input id="name" name="name" type="text"
                                    class="form-control form-control-lg @error('name') is-invalid @enderror"
                                    placeholder="Full Name" value="{{ old('name') }}" required autofocus>
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="form-group mb-3">
                                <input id="email" name="email" type="email"
                                    class="form-control form-control-lg @error('email') is-invalid @enderror"
                                    placeholder="Email" value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div class="form-group mb-3">
                                <input id="password" name="password" type="password"
                                    class="form-control form-control-lg @error('password') is-invalid @enderror"
                                    placeholder="Password" required>
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group mb-4">
                                <input id="password_confirmation" name="password_confirmation" type="password"
                                    class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                                    placeholder="Confirm Password" required>
                                @error('password_confirmation')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Submit button (uncomment if you want to use it now) --}}
                            {{-- 
                            <div class="d-grid">
                                <button type="submit" class="btn btn-block btn-primary btn-lg fw-medium auth-form-btn">
                                    SIGN UP
                                </button>
                            </div> 
                            --}}

                            <div class="text-center mt-4 fw-light">
                                Already have an account?
                                <a href="{{ route('login') }}" class="text-primary">Sign in</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-guest-layout>
