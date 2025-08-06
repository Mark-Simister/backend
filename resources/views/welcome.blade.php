<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to Laravel</title>

    <!-- Fonts -->
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e3e9f7);
            min-height: 100vh;
        }

        .hero {
            max-width: 1000px;
            border-radius: 1rem;
            background-color: white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 3rem;
        }

        .hero h1 {
            font-weight: 700;
            font-size: 2.5rem;
        }

        .hero p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        .btn-glow {
            background-color: #dc3545;
            color: white;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
            transition: all 0.3s ease;
        }

        .btn-glow:hover {
            background-color: #c82333;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.8);
        }

        .nav-link {
            color: #343a40;
        }

        .logo-box {
            background: radial-gradient(circle, rgba(220,53,69,0.1), transparent);
            border: 2px dashed #dc3545;
            padding: 3rem;
            text-align: center;
            border-radius: 1rem;
        }

        .logo-box span {
            font-size: 2rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center text-dark">

    <div class="container">
        <header class="d-flex justify-content-end pt-4">
            @if (Route::has('login'))
                <nav class="d-flex gap-2">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn-outline-dark btn-sm">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-light btn-sm">
                            Log in
                        </a>
                        {{-- @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline-dark btn-sm">
                                Register
                            </a>
                        @endif --}}
                    @endauth
                </nav>
            @endif
        </header>

        <main class="hero d-flex flex-column flex-lg-row justify-content-between align-items-center gap-4 mt-5 mx-auto">
            <div class="text-center text-lg-start">
                <h1 class="mb-3">Welcome to Laravel 🚀</h1>
                <p>Laravel is a web application framework with expressive, elegant syntax. Let’s get you started with everything Laravel has to offer.</p>
                <ul class="list-unstyled my-4">
                    <li class="mb-2">
                        <span class="text-danger">➤</span>
                        <a href="https://laravel.com/docs" class="text-danger fw-semibold text-decoration-underline" target="_blank">Laravel Documentation</a>
                    </li>
                    <li>
                        <span class="text-danger">➤</span>
                        <a href="https://laracasts.com" class="text-danger fw-semibold text-decoration-underline" target="_blank">Watch Laracasts Tutorials</a>
                    </li>
                </ul>
                <a href="https://cloud.laravel.com" class="btn btn-glow px-4 py-2" target="_blank">🚀 Deploy Now</a>
            </div>
            <div class="logo-box">
                <span>Laravel</span>
            </div>
        </main>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>