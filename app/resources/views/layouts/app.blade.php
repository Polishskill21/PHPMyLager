<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @stack('meta')

    <title>@yield('title', 'Storage Management System')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
    @auth
        <div class="app-shell">
            <x-sidebar />
            <main class="app-main">
                @yield('content')
            </main>
        </div>
    @else
        @yield('content')
    @endauth

    @stack('scripts')
</body>
</html>
