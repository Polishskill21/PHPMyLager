<div>
    <!-- When there is no desire, all things are at peace. - Laozi -->
</div>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – PhpMyLager</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
        }

        nav {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 0 2rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-user span {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .btn-logout {
            padding: 0.4rem 1rem;
            background: transparent;
            border: 1px solid #475569;
            border-radius: 6px;
            color: #94a3b8;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        .alert-success {
            background: #052e16;
            border: 1px solid #16a34a;
            color: #4ade80;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .card-icon { font-size: 1.8rem; margin-bottom: 0.75rem; }
        .card-title { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .card-value { font-size: 1.4rem; font-weight: 700; color: #f1f5f9; }

        .role-badge {
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .role-admin  { background: #1e3a5f; color: #60a5fa; border: 1px solid #3b82f6; }
        .role-writer { background: #1a3a2a; color: #4ade80; border: 1px solid #16a34a; }
        .role-viewer { background: #2d2a1a; color: #facc15; border: 1px solid #ca8a04; }
    </style>
</head>
<body>

    <nav>
        <div class="nav-brand">📦 PhpMyLager</div>
        <div class="nav-user">
            {{-- Role badge --}}
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

    <div class="container">

        @if(session('success'))
            <div class="alert-success">✓ {{ session('success') }}</div>
        @endif

        @if(Auth::user()->canWrite())
            <button class="btn-action">+ Add Product</button>
        @endif

        @if(Auth::user()->canDelete())
            <button class="btn-danger">Delete</button>
        @endif

        @if(Auth::user()->isViewer())
            <p class="viewer-note">You have read-only access.</p>
        @endif

        <h2>Dashboard</h2>
        <p class="subtitle">Welcome to the Warehouse Management System.</p>

        <div class="grid">
            <div class="card">
                <div class="card-icon">📦</div>
                <div class="card-title">Products</div>
                <div class="card-value">API Ready</div>
            </div>
            <div class="card">
                <div class="card-icon">🗂️</div>
                <div class="card-title">Orders</div>
                <div class="card-value">API Ready</div>
            </div>
            <div class="card">
                <div class="card-icon">👤</div>
                <div class="card-title">Logged in as</div>
                <div class="card-value">{{ Auth::user()->name }}</div>
            </div>
            <div class="card">
                <div class="card-icon">🟢</div>
                <div class="card-title">System</div>
                <div class="card-value">Operational</div>
            </div>
        </div>

    </div>
</body>
</html>