<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Admin Panel') }}</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f8f9fa;
        }
        .auth-container {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .auth-box {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            border-radius: 12px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.08);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-logo img {
            max-height: 60px;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <img src="{{ asset('admin/assets/images/logo.svg') }}" alt="Logo">
            </div>

            <!-- Main content injected from child -->
            @yield('content')
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>