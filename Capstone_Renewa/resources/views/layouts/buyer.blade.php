<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Buyer Dashboard') - Renewa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        @yield('content')
    </main>

    <footer class="text-center py-8 mt-12 text-gray-500 text-sm">
        © {{ date('Y') }} Renewa Indonesia. Seluruh hak dilindungi undang-undang.
    </footer>
</body>
</html>