<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') · PhpMyLager</title>
    {{-- Inline styles only: error pages must render even when Vite/assets/DB are unavailable. --}}
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 1.5rem;
        }
        .error-card { text-align: center; max-width: 28rem; }
        .error-code { font-size: 4.5rem; font-weight: 700; line-height: 1; margin: 0; color: #38bdf8; }
        .error-title { font-size: 1.5rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
        .error-message { margin: 0 0 1.75rem; color: #94a3b8; line-height: 1.5; }
        .error-link {
            display: inline-block;
            padding: 0.6rem 1.25rem;
            border-radius: 0.5rem;
            background: #38bdf8;
            color: #0f172a;
            font-weight: 600;
            text-decoration: none;
        }
        .error-link:hover { background: #0ea5e9; }
    </style>
</head>
<body>
    <main class="error-card">
        <p class="error-code">@yield('code')</p>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-message">@yield('message')</p>
        <a class="error-link" href="{{ url('/') }}">Back to safety</a>
    </main>
</body>
</html>
