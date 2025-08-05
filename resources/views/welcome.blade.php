<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-qI1uwIFgUvZK9qKzCBz6uQz4iyQ8ehR5qLuEmy0sU35+9zUu6KxmcWbIMfO88I6f" crossorigin="anonymous">
</head>
<body class="bg-light text-dark d-flex flex-column align-items-center justify-content-center min-vh-100 p-4">

    <header class="w-100 mb-4 text-end container">
        @if (Route::has('login'))
            <nav class="d-flex justify-content-end gap-2">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-outline-dark btn-sm">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-light btn-sm">
                        Log in
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-outline-dark btn-sm">
                            Register
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>

    <main class="container d-flex flex-column flex-lg-row gap-4 shadow rounded p-4 bg-white w-100" style="max-width: 960px;">
        <div class="flex-grow-1">
            <h1 class="mb-3">Let's get started</h1>
            <p class="text-muted">Laravel has an incredibly rich ecosystem. We suggest starting with the following:</p>
            <ul class="list-unstyled mb-4">
                <li class="mb-3">
                    <span class="me-2">•</span>
                    Read the
                    <a href="https://laravel.com/docs" target="_blank" class="text-danger text-decoration-underline">Documentation</a>
                </li>
                <li>
                    <span class="me-2">•</span>
                    Watch tutorials on
                    <a href="https://laracasts.com" target="_blank" class="text-danger text-decoration-underline">Laracasts</a>
                </li>
            </ul>
            <a href="https://cloud.laravel.com" target="_blank" class="btn btn-dark">
                Deploy now
            </a>
        </div>

        <div class="flex-shrink-0 w-100 w-lg-50 bg-danger bg-opacity-10 rounded d-flex align-items-center justify-content-center p-4">
            <!-- Laravel Logo or Image Placeholder -->
            <span class="text-danger fw-bold">Laravel</span>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-V2sPnyMRA58hdcuZ4HgXR+Q4eb6FCfAT5X7xE4STZb/jmvaz2cGy4XzCkKSoGcc2" crossorigin="anonymous"></script>
</body>
</html>
