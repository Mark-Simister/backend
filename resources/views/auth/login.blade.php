<x-guest-layout>
    @push('styles')
        <!-- Skydash CSS -->
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/feather/feather.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/ti-icons/css/themify-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/font-awesome/css/font-awesome.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/css/vertical-layout-light/style.css') }}">
        <link rel="shortcut icon" href="{{ asset('admin/assets/images/favicon.png') }}" />
    @endpush

    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-start py-5 px-4 px-sm-5">
                            <div class="brand-logo text-center">
                                {{-- <img src="{{ asset('admin/assets/images/logo.svg') }}" alt="logo"> --}}
                                <img src="{{ asset('admin/assets/images/beastierated_logo.png') }}" alt="logo">
                            </div>

                            <!-- Session Status -->
                            @if (session('status'))
                                <div class="alert alert-success mb-4">
                                    {{ session('status') }}
                                </div>
                            @endif

                            <h4>Hello! Let's get started</h4>
                            <h6 class="fw-light">Sign in to continue.</h6>

                            <form method="POST" action="{{ route('login') }}" class="pt-3">
                                @csrf

                                <!-- Email -->
                                <div class="form-group">
                                    <input id="email" name="email" type="email" class="form-control form-control-lg" placeholder="Email" value="{{ old('email') }}" required autofocus>
                                    @error('email')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <input id="password" name="password" type="password" class="form-control form-control-lg" placeholder="Password" required>
                                    @error('password')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Remember Me -->
                                <div class="my-2 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" class="form-check-input" name="remember" id="remember_me">
                                            Keep me signed in
                                        </label>
                                    </div>
                                    @if (Route::has('password.request'))
                                        <a class="auth-link text-black" href="{{ route('password.request') }}">
                                            Forgot password?
                                        </a>
                                    @endif
                                </div>

                                <!-- Submit -->
                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit" class="btn btn-block btn-primary btn-lg fw-medium auth-form-btn">
                                        SIGN IN
                                    </button>
                                </div>

                                <!-- Optional Social login or register -->
                                <div class="text-center mt-4 fw-light">
                                    Don’t have an account?
                                    <a href="{{ route('register') }}" class="text-primary">Create</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- Skydash JS -->
        <script src="{{ asset('admin/assets/vendors/js/vendor.bundle.base.js') }}"></script>
        <script src="{{ asset('admin/assets/js/off-canvas.js') }}"></script>
        <script src="{{ asset('admin/assets/js/hoverable-collapse.js') }}"></script>
        <script src="{{ asset('admin/assets/js/template.js') }}"></script>
        <script src="{{ asset('admin/assets/js/settings.js') }}"></script>
        <script src="{{ asset('admin/assets/js/todolist.js') }}"></script>
    @endpush
</x-guest-layout>