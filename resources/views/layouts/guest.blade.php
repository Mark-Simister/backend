<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-qI1uwIFgUvZK9qKzCBz6uQz4iyQ8ehR5qLuEmy0sU35+9zUu6KxmcWbIMfO88I6f" crossorigin="anonymous">

    <!-- Optional Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

    <div class="container min-vh-100 d-flex flex-column justify-content-center align-items-center">
        <div class="mb-4 text-center">
            <a href="/">
                {{-- Replace with actual logo if needed --}}
                <x-application-logo class="img-fluid" style="width: 80px; height: 80px;" />
            </a>
        </div>

        <div class="card shadow w-100" style="max-width: 500px;">
            <div class="card-body">
                {{ $slot }}
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-V2sPnyMRA58hdcuZ4HgXR+Q4eb6FCfAT5X7xE4STZb/jmvaz2cGy4XzCkKSoGcc2" crossorigin="anonymous"></script>
</body>
</html>
