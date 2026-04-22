<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @stack('meta')

    <title>@yield('title', 'PhpMyLager')</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
    @stack('styles')
</head>
<body>

    @auth
    <nav>
        <div class="nav-brand-wrap">
            <a href="{{ route('dashboard') }}" class="nav-brand">📦 PhpMyLager</a>
            @hasSection('page-title')
                <span class="nav-sep">/</span>
                <span class="nav-page">@yield('page-title')</span>
            @endif
        </div>
        <div class="nav-user">
            <span class="role-badge role-{{ Auth::user()->role }}">
                {{ strtoupper(Auth::user()->role) }}
            </span>
            <span>{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>
    @endauth

    @yield('content')

    @stack('scripts')
</body>
</html>