<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #f3f4f6, #dee2e6);
            min-height: 100vh;
        }

        .card-custom {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .logo img {
            height: 80px;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="w-100" style="max-width: 480px;">
            <div class="text-center mb-4 logo">
                <a href="/">
                    <img src="{{ asset('admin/assets/images/beastierated_logo.png') }}" alt="BeastieRated Logo" class="img-fluid">
                </a>
            </div>

            <div class="card card-custom bg-white">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>