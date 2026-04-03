<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – PhpMyLager</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #f1f5f9;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .logo p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .alert-success {
            background: #052e16;
            border: 1px solid #16a34a;
            color: #4ade80;
        }

        .alert-error {
            background: #2d0a0a;
            border: 1px solid #dc2626;
            color: #f87171;
        }

        label {
            display: block;
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.7rem 0.9rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            margin-bottom: 1.1rem;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        input.is-invalid {
            border-color: #dc2626;
        }

        .error-msg {
            color: #f87171;
            font-size: 0.8rem;
            margin-top: -0.8rem;
            margin-bottom: 0.8rem;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem;
        }

        .remember input { width: auto; margin-bottom: 0; }
        .remember span { color: #94a3b8; font-size: 0.875rem; }

        button[type="submit"] {
            width: 100%;
            padding: 0.75rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button[type="submit"]:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <h1>📦 PhpMyLager</h1>
            <p>Warehouse Management System</p>
        </div>

        @if($errors->any())
            <div class="alert alert-error">✗ {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Sign In</button>
        </form>
    </div>
    <script>
    const emailInput = document.getElementById('email');
    const form = document.querySelector('form');

    const savedEmail = localStorage.getItem('lastEmail');
    if (savedEmail && !emailInput.value) {
        emailInput.value = savedEmail;
        emailInput.style.color = '#94a3b8';
    }

    emailInput.addEventListener('input', function () {
        this.style.color = '#f1f5f9';
    });

    form.addEventListener('submit', function () {
        if (emailInput.value) {
            localStorage.setItem('lastEmail', emailInput.value);
        }
    });
    </script>

</body>
</html>